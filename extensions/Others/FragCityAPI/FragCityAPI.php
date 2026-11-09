<?php

namespace Paymenter\Extensions\Others\FragCityAPI;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

#[ExtensionMeta(
    name: 'FragCity API',
    description: 'Private signed account, checkout SSO and customer service bridge for FragCity.',
    version: '1.6.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCityAPI extends Extension
{
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'shared_secret',
                'label' => 'FragCity Bridge HMAC Secret',
                'type' => 'password',
                'description' => 'A private 32-byte random secret (64 hexadecimal characters). Must match integrations.paymenter_backend.shared_secret in FragCity runtime settings.',
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
            [
                'name' => 'cs2_product_id',
                'label' => 'FragCity CS2 Checkout Product ID',
                'type' => 'number',
                'description' => 'Enabled Paymenter Product ID using the FragCityCS2 server extension. Its native Plans and Config Options are used by Paymenter Checkout.',
                'required' => true,
                'validation' => 'required|integer|min:1',
            ],
        ];
    }

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/FragCityAPI/database/migrations');
    }

    public function upgraded($oldVersion = null): void
    {
        ExtensionHelper::runMigrations('extensions/Others/FragCityAPI/database/migrations');
    }

    public function boot(): void
    {
        require __DIR__ . '/routes.php';
    }

    public function ping(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        return response()->json([
            'success' => true,
            'service' => 'FragCityAPI',
            'version' => '1.6.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function syncUser(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_json_body',
            ], 400);
        }

        // The payload is decoded from the raw HMAC-signed body. Normalize
        // client names here so whitespace-only values cannot pass as names.
        $payload['username'] = is_string($payload['username'] ?? null)
            ? strtolower(trim($payload['username']))
            : $payload['username'] ?? null;
        $payload['first_name'] = is_string($payload['first_name'] ?? null)
            ? trim($payload['first_name'])
            : $payload['first_name'] ?? null;
        $payload['last_name'] = is_string($payload['last_name'] ?? null)
            ? trim($payload['last_name'])
            : $payload['last_name'] ?? null;

        $validator = Validator::make($payload, [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^09[0-9]{9}$/', 'max:15'],
            'first_name' => ['required', 'string', 'min:1', 'max:191'],
            'last_name' => ['required', 'string', 'min:1', 'max:191'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/^(?=.*\\p{L})(?=.*[0-9])[\\s\\S]+$/u',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $steamId = $data['steamid64'];
        $username = $data['username'];
        $email = mb_strtolower(trim($data['email']));
        $phone = trim($data['phone']);
        $firstName = trim($data['first_name']);
        $lastName = trim((string) $data['last_name']);
        $password = $data['password'];

        try {
            $result = DB::transaction(function () use (
                $steamId,
                $username,
                $email,
                $phone,
                $firstName,
                $lastName,
                $password
            ): array {
                $link = DB::table('fragcity_user_links')
                    ->where('steamid64', $steamId)
                    ->lockForUpdate()
                    ->first();

                $userByEmail = User::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                $created = false;

                if ($link) {
                    $user = User::query()->lockForUpdate()->find($link->user_id);
                    if (!$user) {
                        throw new RuntimeException('linked_paymenter_user_missing');
                    }

                    if ($userByEmail && (int) $userByEmail->id !== (int) $user->id) {
                        throw new RuntimeException('email_already_linked_to_another_paymenter_user');
                    }
                } elseif ($userByEmail) {
                    $existingLink = DB::table('fragcity_user_links')
                        ->where('user_id', $userByEmail->id)
                        ->lockForUpdate()
                        ->first();

                    if ($existingLink && (string) $existingLink->steamid64 !== $steamId) {
                        throw new RuntimeException('paymenter_user_already_linked_to_another_steam_account');
                    }

                    // Do not silently claim an existing Paymenter account based
                    // only on an email address. The supplied password must also
                    // prove control of that existing account.
                    if (!Hash::check($password, (string) $userByEmail->password)) {
                        throw new RuntimeException('email_already_in_use');
                    }

                    $user = $userByEmail;
                    DB::table('fragcity_user_links')->insert([
                        'user_id' => $user->id,
                        'steamid64' => $steamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $user = User::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName !== '' ? $lastName : null,
                        'email' => $email,
                        'password' => $password,
                    ]);

                    DB::table('fragcity_user_links')->insert([
                        'user_id' => $user->id,
                        'steamid64' => $steamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created = true;
                }

                // FragCity is the identity source of truth. A successful sync
                // intentionally refreshes the Paymenter password to the same
                // current password supplied during the authenticated FragCity
                // registration/login flow.
                $usernameTaken = User::query()
                    ->where('id', '<>', (int) $user->id)
                    ->whereHas('properties', function ($query) use ($username): void {
                        $query->where('key', 'fragcity_username')
                            ->where('value', $username);
                    })
                    ->exists();

                if ($usernameTaken) {
                    throw new RuntimeException('username_already_in_use');
                }

                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->email = $email;
                $user->password = $password;
                $user->save();

                $user->properties()->updateOrCreate(
                    ['key' => 'fragcity_username'],
                    ['name' => 'FragCity Username', 'value' => $username]
                );

                $user->properties()->updateOrCreate(
                    ['key' => 'fragcity_phone'],
                    ['name' => 'FragCity Phone', 'value' => $phone]
                );

                $user->properties()->updateOrCreate(
                    ['key' => 'fragcity_steamid64'],
                    ['name' => 'FragCity SteamID64', 'value' => $steamId]
                );

                return [
                    'user_id' => (int) $user->id,
                    'created' => $created,
                    'email' => (string) $user->email,
                ];
            }, 3);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (RuntimeException $e) {
            $safeErrors = [
                'username_already_in_use',
                'linked_paymenter_user_missing',
                'email_already_linked_to_another_paymenter_user',
                'paymenter_user_already_linked_to_another_steam_account',
                'email_already_in_use',
            ];

            if (in_array($e->getMessage(), $safeErrors, true)) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 409);
            }

            Log::error('FragCity user synchronization failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'account_sync_failed',
            ], 500);
        } catch (Throwable $e) {
            Log::error('FragCity user synchronization failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'account_sync_failed',
            ], 500);
        }
    }

    /**
     * Accept one paid CS2 checkout from the authenticated FragCity website.
     *
     * FragCity verifies the ZarinPal callback before calling this endpoint.
     * Paymenter records the paid invoice and pending service, then its native
     * InvoiceObserver -> ProcessPaidInvoiceService -> CreateJob flow invokes
     * FragCityCS2 and the inherited Pterodactyl provisioner.
     *
     * This method never calls Pterodactyl directly and never trusts browser data.
     */

    /**
     * Return the authenticated customer's FragCity CS2 services and
     * allow-listed Pterodactyl access details. No password is ever returned.
     */
    /**
     * Create a short-lived, single-use URL that signs the authenticated
     * FragCity Steam account into Paymenter and opens its native CS2 Checkout.
     *
     * This endpoint does not create Orders, Invoices, Services, Config rows or
     * server records. Paymenter's own Checkout and Cart own that lifecycle.
     */
    public function createCheckoutUrl(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
            ], 422);
        }

        try {
            $steamId = (string) $validator->validated()['steamid64'];
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'error' => 'fragcity_user_not_linked',
                ], 404);
            }

            $user = User::query()->find((int) $link->user_id);
            $product = Product::query()
                ->with(['category', 'server'])
                ->find((int) $this->config('cs2_product_id'));

            if (
                !$user
                || !$product
                || !(bool) $product->enabled
                || $product->server?->extension !== 'FragCityCS2'
                || !$product->category
            ) {
                return response()->json([
                    'success' => false,
                    'error' => 'paymenter_checkout_unavailable',
                ], 503);
            }

            $token = bin2hex(random_bytes(32));
            $cacheKey = 'fragcity_checkout_login:' . hash('sha256', $token);

            Cache::put(
                $cacheKey,
                [
                    'user_id' => (int) $user->id,
                    'product_id' => (int) $product->id,
                ],
                now()->addMinutes(2)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_url' => route('extensions.fragcity-api.checkout-login', [
                        'token' => $token,
                    ]),
                    'expires_in' => 120,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('FragCity native checkout URL creation failed.', [
                'steamid_hash' => substr(hash('sha256', (string) ($payload['steamid64'] ?? '')), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'paymenter_checkout_unavailable',
            ], 503);
        }
    }

    /**
     * Consume a one-time FragCity SSO token, establish the native Paymenter
     * session, and redirect to the configured Product's native Checkout.
     */
    public function checkoutLogin(string $token)
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            abort(404);
        }

        $cacheKey = 'fragcity_checkout_login:' . hash('sha256', $token);
        $context = Cache::pull($cacheKey);

        if (
            !is_array($context)
            || (int) ($context['user_id'] ?? 0) < 1
            || (int) ($context['product_id'] ?? 0) < 1
        ) {
            abort(410, 'This checkout link has expired or has already been used.');
        }

        $user = User::query()->find((int) $context['user_id']);
        $product = Product::query()
            ->with(['category', 'server'])
            ->find((int) $context['product_id']);

        if (
            !$user
            || !$product
            || !(bool) $product->enabled
            || $product->server?->extension !== 'FragCityCS2'
            || !$product->category
        ) {
            abort(404);
        }

        Auth::guard('web')->login($user, false);
        request()->session()->regenerate();

        return redirect()->route('products.checkout', [
            'category' => $product->category->slug,
            'product' => $product->slug,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function myServers(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
            ], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => true,
                    'data' => ['servers' => []],
                ]);
            }

            $user = User::query()->with('properties')->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $services = Service::query()
                ->where('user_id', (int) $user->id)
                ->where('status', '<>', Service::STATUS_CANCELLED)
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue', 'upgrade'])
                ->orderByDesc('id')
                ->get();

            $servers = [];

            foreach ($services as $service) {
                $serverExtension = $service->product?->server;
                if (!$serverExtension || $serverExtension->extension !== 'FragCityCS2') {
                    continue;
                }

                try {
                    $summary = ExtensionHelper::callService($service, 'getCustomerPanelSummary');
                    if (is_array($summary)) {
                        $summary['service_upgrade_ids'] = $service->upgrade
                            ->pluck('id')
                            ->map(static fn ($id): int => (int) $id)
                            ->values()
                            ->all();
                        $servers[] = $summary;
                    }
                } catch (Throwable $e) {
                    Log::warning('FragCity customer server summary failed.', [
                        'paymenter_user_id' => (int) $user->id,
                        'service_id' => (int) $service->id,
                        'exception' => get_class($e),
                    ]);

                    $servers[] = [
                        'service_id' => (int) $service->id,
                        'product_name' => (string) ($service->product?->name ?? 'CS2 Server'),
                        'service_status' => (string) $service->status,
                        'service_upgrade_ids' => $service->upgrade
                            ->pluck('id')
                            ->map(static fn ($id): int => (int) $id)
                            ->values()
                            ->all(),
                        'state' => 'unavailable',
                        'panel' => null,
                        'server' => null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['servers' => $servers],
            ]);
        } catch (Throwable $e) {
            Log::error('FragCity customer server list failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'customer_servers_unavailable',
            ], 500);
        }
    }

    /**
     * Change only the Pterodactyl password for one of the caller's CS2
     * services. Ownership is established from the signed SteamID link and
     * Paymenter service owner; the browser never receives an Application API key.
     */
    public function setPanelPassword(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:128',
                'regex:/^(?=.*\\p{L})(?=.*[0-9])\\S+$/u',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $steamId = (string) $data['steamid64'];
        $serviceId = (int) $data['service_id'];
        $password = (string) $data['password'];

        // Bridge traffic originates from the website server, so the route's
        // IP throttle is only a coarse ceiling. Apply the meaningful limit to
        // the signed customer identity to avoid coupling different customers.
        $rateLimitKey = 'fragcity_panel_password:' . hash('sha256', $steamId);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'success' => false,
                'error' => 'panel_password_rate_limited',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            $user = User::query()->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $service = Service::query()
                ->where('id', $serviceId)
                ->where('user_id', (int) $user->id)
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue'])
                ->first();

            if (!$service || $service->status === Service::STATUS_CANCELLED
                || $service->product?->server?->extension !== 'FragCityCS2'
            ) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            ExtensionHelper::callService(
                $service,
                'setCustomerPanelPassword',
                [$password]
            );

            // Store only a timestamp marker, never the password or its hash.
            $user->properties()->updateOrCreate(
                ['key' => 'fragcity_panel_password_set_at'],
                ['name' => 'FragCity Panel Password Set At', 'value' => now()->toIso8601String()]
            );

            RateLimiter::clear($rateLimitKey);

            return response()->json([
                'success' => true,
                'data' => ['password_updated' => true],
            ]);
        } catch (Throwable $e) {
            Log::warning('FragCity Pterodactyl password update failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'service_id' => $serviceId,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'panel_password_update_failed',
            ], 502);
        }
    }

    /**
     * Update the customer's shared Pterodactyl client username.
     * Ownership is checked through the signed SteamID link and service owner.
     */
    public function setPanelUsername(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        if (is_array($payload) && is_string($payload['username'] ?? null)) {
            $payload['username'] = strtolower(trim($payload['username']));
        }

        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $steamId = (string) $data['steamid64'];
        $serviceId = (int) $data['service_id'];
        $username = (string) $data['username'];

        $rateLimitKey = 'fragcity_panel_username:' . hash('sha256', $steamId);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'success' => false,
                'error' => 'panel_username_rate_limited',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            $user = User::query()->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $service = Service::query()
                ->where('id', $serviceId)
                ->where('user_id', (int) $user->id)
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue'])
                ->first();

            if (
                !$service
                || $service->status === Service::STATUS_CANCELLED
                || $service->product?->server?->extension !== 'FragCityCS2'
            ) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            $result = ExtensionHelper::callService(
                $service,
                'setCustomerPanelUsername',
                [$username]
            );

            RateLimiter::clear($rateLimitKey);

            return response()->json([
                'success' => true,
                'data' => [
                    'username_updated' => true,
                    'username' => (string) ($result['username'] ?? $username),
                ],
            ]);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'panel_username_already_in_use') {
                return response()->json([
                    'success' => false,
                    'error' => 'panel_username_already_in_use',
                ], 409);
            }

            if ($e->getMessage() === 'panel_username_invalid') {
                return response()->json([
                    'success' => false,
                    'error' => 'validation_failed',
                ], 422);
            }

            Log::warning('FragCity Pterodactyl username update failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'service_id' => $serviceId,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'panel_username_update_failed',
            ], 502);
        }
    }

    private function authenticateBridgeRequest(Request $request): ?JsonResponse
    {
        $secret = (string) $this->config('shared_secret');
        if (!preg_match('/^[a-f0-9]{64,256}$/iD', $secret)) {
            return response()->json([
                'success' => false,
                'error' => 'bridge_not_configured',
            ], 503);
        }

        $allowedIps = preg_split('/[,\s]+/', trim((string) ($this->config('allowed_ips') ?? ''))) ?: [];
        $allowedIps = array_values(array_filter($allowedIps, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP)));

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

        if (strlen($rawBody) > 16384) {
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

        $nonceKey = 'fragcity_api_nonce:' . hash('sha256', $nonce);
        if (!Cache::add($nonceKey, true, now()->addMinutes(10))) {
            return response()->json([
                'success' => false,
                'error' => 'request_replayed',
            ], 409);
        }

        if ($rawBody !== '') {
            try {
                $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_json_body',
                ], 400);
            }

            if (!is_array($payload)) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_json_body',
                ], 400);
            }
        } else {
            $payload = [];
        }

        $request->attributes->set('fragcity_bridge_payload', $payload);

        return null;
    }
}
