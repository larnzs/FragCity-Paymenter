<?php

namespace Paymenter\Extensions\Others\FragCitySupportAPI;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

#[ExtensionMeta(
    name: 'FragCity Support API',
    description: 'Private SteamID identity bridge and small compatibility endpoints for Paymenter support.',
    version: '1.1.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCitySupportAPI extends Extension
{
    private const VERSION = '1.1.0';

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'shared_secret',
                'label' => 'FragCity Support API HMAC Secret',
                'type' => 'password',
                'description' => 'A dedicated 32-byte random secret (64 hexadecimal characters). Do not reuse the FragCity server bridge secret.',
                'required' => true,
                'encrypted' => true,
                'validation' => 'required|string|min:64|max:256',
            ],
            [
                'name' => 'allowed_ips',
                'label' => 'Allowed Source IPs',
                'type' => 'textarea',
                'description' => 'Optional comma- or newline-separated exact source IP allowlist. HMAC authentication remains mandatory.',
                'required' => false,
                'default' => '',
            ],
        ];
    }

    public function boot(): void
    {
        require __DIR__ . '/routes.php';
    }

    /**
     * Health check. It authenticates the bridge but does not require a customer.
     */
    public function ping(Request $request): JsonResponse
    {
        $authError = $this->authenticateRequest($request);
        if ($authError) {
            return $authError;
        }

        return response()->json([
            'success' => true,
            'service' => 'FragCitySupportAPI',
            'version' => self::VERSION,
            'identity_bridge' => true,
            'paymenter_native_api' => true,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Resolve a FragCity SteamID64 to its linked Paymenter user ID.
     *
     * FragCity's backend uses the returned ID with Paymenter's official
     * /api/v1/admin endpoints. The API key must never be sent to the browser.
     */
    public function resolveIdentity(Request $request): mixed
    {
        return $this->handle($request, function (array $payload, User $user): JsonResponse {
            return response()->json([
                'success' => true,
                'data' => [
                    'steamid64' => (string) $payload['steamid64'],
                    'paymenter_user_id' => (int) $user->id,
                    'linked' => true,
                ],
            ]);
        });
    }

    /**
     * Paymenter does not expose its ticket department configuration through
     * the Admin API, so this small endpoint returns the current server options.
     */
    public function ticketOptions(Request $request): mixed
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportBridgeService::class)->options($user)
        );
    }

    /**
     * Paymenter's Admin API has no ticket-message attachment upload route.
     * This endpoint only attaches a file to an existing message owned by
     * the signed-in FragCity customer.
     */
    public function attachmentUpload(Request $request): mixed
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportBridgeService::class)->upload($payload, $user)
        );
    }

    public function attachmentDownload(Request $request): mixed
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportBridgeService::class)->download($payload, $user)
        );
    }

    /**
     * Paymenter v1.5.9 exposes invoice and invoice-item endpoints but no
     * invoice-transaction endpoint in its Admin API, so keep this read-only
     * compatibility endpoint here.
     */
    public function transactionsList(Request $request): mixed
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportAccountingService::class)->transactions($payload, $user)
        );
    }

    private function handle(Request $request, Closure $callback): mixed
    {
        $authError = $this->authenticateRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_support_payload', []);
        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_json_body',
            ], 400);
        }

        $user = null;

        try {
            $user = $this->resolveLinkedUser($payload);
            if ($user instanceof JsonResponse) {
                return $user;
            }

            return $callback($payload, $user);
        } catch (Throwable $exception) {
            Log::error('FragCity Support API request failed.', [
                'paymenter_user_id' => $user instanceof User ? $user->id : null,
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'internal_error',
            ], 500);
        }
    }

    private function resolveLinkedUser(array $payload): User|JsonResponse
    {
        $validator = Validator::make($payload, [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];

        $link = DB::table('fragcity_user_links')
            ->where('steamid64', $steamId)
            ->first();

        if (!$link) {
            return response()->json([
                'success' => false,
                'error' => 'fragcity_account_not_linked',
            ], 404);
        }

        $user = User::query()->find((int) $link->user_id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'linked_paymenter_user_missing',
            ], 404);
        }

        return $user;
    }

    private function authenticateRequest(Request $request): ?JsonResponse
    {
        $secret = (string) $this->config('shared_secret');
        if (!preg_match('/^[a-f0-9]{64,256}$/iD', $secret)) {
            return response()->json([
                'success' => false,
                'error' => 'support_api_not_configured',
            ], 503);
        }

        $rawAllowedIps = trim((string) ($this->config('allowed_ips') ?? ''));
        $allowedIps = $rawAllowedIps === ''
            ? []
            : (preg_split('/[,\s]+/', $rawAllowedIps) ?: []);

        foreach ($allowedIps as $allowedIp) {
            if (!filter_var($allowedIp, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_ip_allowlist_configuration',
                ], 503);
            }
        }

        if ($allowedIps && !in_array((string) $request->ip(), $allowedIps, true)) {
            return response()->json([
                'success' => false,
                'error' => 'source_ip_not_allowed',
            ], 403);
        }

        $timestamp = (string) $request->header('X-FragCity-Timestamp', '');
        $nonce = (string) $request->header('X-FragCity-Nonce', '');
        $signature = strtolower((string) $request->header('X-FragCity-Signature', ''));
        $rawBody = $request->getContent();

        // Files are transferred as base64 JSON. This hard request limit
        // leaves room for base64 overhead over the 10 MiB decoded file cap.
        if (strlen($rawBody) > 15 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'error' => 'request_too_large',
            ], 413);
        }

        if (
            !preg_match('/^[0-9]{10}$/D', $timestamp)
            || !preg_match('/^[a-f0-9]{32,64}$/Di', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/D', $signature)
            || abs(time() - (int) $timestamp) > 300
        ) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_signature_headers',
            ], 401);
        }

        $signedMessage = $timestamp . "\n" . $nonce . "\n" . $rawBody;
        $expectedSignature = hash_hmac('sha256', $signedMessage, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_signature',
            ], 401);
        }

        $nonceKey = 'fragcity_support_api_nonce:' . hash('sha256', $nonce);
        if (!Cache::add($nonceKey, true, now()->addMinutes(10))) {
            return response()->json([
                'success' => false,
                'error' => 'request_replayed',
            ], 409);
        }

        if ($rawBody === '') {
            $payload = [];
        } else {
            try {
                $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_json_body',
                ], 400);
            }
        }

        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_json_body',
            ], 400);
        }

        $request->attributes->set('fragcity_support_payload', $payload);

        return null;
    }
}
