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
    description: 'Private signed bridge for Paymenter customer tickets and read-only accounting.',
    version: '1.0.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCitySupportAPI extends Extension
{
    private const VERSION = '1.0.0';

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
            'paymenter_ticketing' => true,
            'accounting_mode' => 'read_only',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function ticketsList(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->index($payload, $user)
        );
    }

    public function ticketOptions(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->options($payload, $user)
        );
    }

    public function ticketShow(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->show($payload, $user)
        );
    }

    public function ticketCreate(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->create($payload, $user)
        );
    }

    public function ticketReply(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->reply($payload, $user)
        );
    }

    public function ticketClose(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->close($payload, $user)
        );
    }

    public function ticketReopen(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->reopen($payload, $user)
        );
    }

    public function attachmentDownload(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportTicketService::class)->download($payload, $user)
        );
    }

    public function accountingSummary(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportAccountingService::class)->summary($payload, $user)
        );
    }

    public function invoicesList(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportAccountingService::class)->invoices($payload, $user)
        );
    }

    public function invoiceShow(Request $request)
    {
        return $this->handle($request, fn (array $payload, User $user) =>
            app(Services\SupportAccountingService::class)->invoice($payload, $user)
        );
    }

    public function transactionsList(Request $request)
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

        $identity = null;

        try {
            $identity = $this->resolveLinkedUser($payload);
            if ($identity instanceof JsonResponse) {
                return $identity;
            }

            return $callback($payload, $identity);
        } catch (Throwable $exception) {
            Log::error('FragCity Support API request failed.', [
                'paymenter_user_id' => $identity instanceof User ? $identity->id : null,
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

        // Attachments are transported as base64 inside JSON. Keep a hard
        // request limit so a signed request cannot exhaust PHP memory.
        if (strlen($rawBody) > 30 * 1024 * 1024) {
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
