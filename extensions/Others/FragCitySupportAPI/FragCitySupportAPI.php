<?php

namespace Paymenter\\Extensions\\Others\\FragCitySupportAPI;

use App\\Attributes\\ExtensionMeta;
use App\\Classes\\Extension\\Extension;
use App\\Models\\User;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Cache;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Log;
use Throwable;

#[ExtensionMeta(
    name: 'FragCity Support API',
    description: 'Private SteamID64 identity bridge for the FragCity website and Paymenter native Admin API.',
    version: '1.2.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCitySupportAPI extends Extension
{
    private const VERSION = '1.2.0';

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'shared_secret',
                'label' => 'FragCity Support API HMAC Secret',
                'type' => 'password',
                'description' => 'Dedicated random secret (at least 32 bytes / 64 hexadecimal characters). Never reuse the FragCity server bridge secret.',
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
     * Authenticated health check. No customer identity is required.
     */
    public function ping(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateRequest($request)) {
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
     * Resolve the SteamID64 of the authenticated FragCity customer to the
     * linked Paymenter user ID. Ticket and invoice operations stay on the
     * official Paymenter Admin API and are not implemented by this extension.
     */
    public function resolveIdentity(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateRequest($request)) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_support_payload', []);
        $steamId = $payload['steamid64'] ?? null;

        if (!is_string($steamId) || !preg_match('/^[0-9]{17}$/D', $steamId)) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => ['steamid64'],
            ], 422);
        }

        try {
            $paymenterUserId = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->value('user_id');

            if (!$paymenterUserId) {
                return response()->json([
                    'success' => false,
                    'error' => 'fragcity_account_not_linked',
                ], 404);
            }

            $user = User::query()->find((int) $paymenterUserId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'steamid64' => $steamId,
                    'paymenter_user_id' => (int) $user->id,
                    'linked' => true,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('FragCity Support API identity lookup failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'identity_lookup_failed',
            ], 500);
        }
    }

    /**
     * Verify HMAC, enforce the optional source IP allowlist and reject
     * repeated nonces. The exact raw request body is part of the signature.
     */
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
            : (preg_split('/[,\\s]+/', $rawAllowedIps) ?: []);

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

        if (strlen($rawBody) > 16 * 1024) {
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

        $signedMessage = $timestamp . "\\n" . $nonce . "\\n" . $rawBody;
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
