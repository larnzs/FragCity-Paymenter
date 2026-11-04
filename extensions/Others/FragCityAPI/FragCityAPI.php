<?php

namespace Paymenter\Extensions\Others\FragCityAPI;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[ExtensionMeta(
    name: 'FragCity API',
    description: 'Private signed FragCity bridge for durable customer identity, CS2 service lifecycle, Client API, Console and File Manager.',
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
                'description' => 'Optional comma- or newline-separated exact source IP allowlist. Leave empty only when the hosting provider cannot guarantee a stable egress IP; HMAC authentication remains mandatory.',
                'required' => false,
                'default' => '',
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

        // Keep Paymenter Core untouched. The extension owns the cleanup
        // policy and blocks the Eloquent delete if the corresponding remote
        // CS2 server cannot be safely terminated.
        Service::deleting(function (Service $service): void {
            $service->loadMissing(['product.server']);

            if ($service->product?->server?->extension !== 'FragCityCS2') {
                return;
            }

            try {
                ExtensionHelper::terminateServer($service);
            } catch (Throwable $e) {
                Log::error('FragCity service deletion blocked because remote cleanup failed.', [
                    'service_id' => (int) $service->id,
                    'paymenter_user_id' => (int) $service->user_id,
                    'exception' => get_class($e),
                ]);

                throw $e;
            }
        });

        // A Paymenter customer account can own several independent CS2
        // services. Terminate each one before the user row is removed; the
        // identity registry deliberately has no cascading foreign key.
        User::deleting(function (User $user): void {
            $services = Service::query()
                ->where('user_id', (int) $user->id)
                ->with('product.server')
                ->get();

            foreach ($services as $service) {
                if ($service->product?->server?->extension !== 'FragCityCS2') {
                    continue;
                }

                ExtensionHelper::terminateServer($service);
            }
        });

        User::deleted(function (User $user): void {
            if (!Schema::hasTable('fragcity_customer_identities')) {
                return;
            }

            DB::table('fragcity_customer_identities')
                ->where('paymenter_user_id', (int) $user->id)
                ->update([
                    'paymenter_user_id' => null,
                    'status' => 'detached',
                    'updated_at' => now(),
                ]);
        });
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
            'password' => ['required', 'string', 'min:8', 'max:255'],
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

                $customerUuid = $this->syncCustomerIdentity($user, $steamId);

                return [
                    'user_id' => (int) $user->id,
                    'customer_uuid' => $customerUuid,
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
                'paymenter_user_identity_conflict',
                'customer_identity_storage_not_ready',
            ];

            if (in_array($e->getMessage(), $safeErrors, true)) {
                $statusCode = $e->getMessage() === 'customer_identity_storage_not_ready' ? 503 : 409;

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], $statusCode);
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
     * Return the authenticated customer's FragCity CS2 services and
     * allow-listed Pterodactyl access details. No password is ever returned.
     */
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
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue'])
                ->orderByDesc('id')
                ->get();

            $clientApiConnected = Schema::hasTable('fragcity_client_api_credentials')
                && DB::table('fragcity_client_api_credentials')
                    ->where('user_id', (int) $user->id)
                    ->exists();

            $servers = [];

            foreach ($services as $service) {
                $serverExtension = $service->product?->server;
                if (!$serverExtension || $serverExtension->extension !== 'FragCityCS2') {
                    continue;
                }

                try {
                    $summary = ExtensionHelper::callService($service, 'getCustomerPanelSummary');
                    if (is_array($summary)) {
                        if (is_array($summary['panel'] ?? null)) {
                            $summary['panel']['client_api_connected'] = $clientApiConnected;
                        }
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
                        'state' => 'unavailable',
                        'panel' => null,
                        'server' => null,
                    ];
                }
            }

            $paymenterMissingMappings = $this->markPaymenterMissingMappings(
                $steamId,
                (int) $user->id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'servers' => $servers,
                    'lifecycle_alerts' => [
                        'paymenter_missing' => $paymenterMissingMappings,
                    ],
                ],
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
     * Create or recover a durable customer identity independent of either
     * platform's auto-increment user IDs. The registry intentionally survives
     * Paymenter user deletion and re-registration.
     */
    private function syncCustomerIdentity(User $user, string $steamId): string
    {
        if (!Schema::hasTable('fragcity_customer_identities')) {
            throw new RuntimeException('customer_identity_storage_not_ready');
        }

        return DB::transaction(function () use ($user, $steamId): string {
            $identity = DB::table('fragcity_customer_identities')
                ->where('steamid64', $steamId)
                ->lockForUpdate()
                ->first();

            $identityByPaymenterUser = DB::table('fragcity_customer_identities')
                ->where('paymenter_user_id', (int) $user->id)
                ->lockForUpdate()
                ->first();

            if (
                $identityByPaymenterUser
                && (string) $identityByPaymenterUser->steamid64 !== $steamId
            ) {
                throw new RuntimeException('paymenter_user_identity_conflict');
            }

            $customerUuid = $identity
                ? (string) $identity->customer_uuid
                : (string) Str::uuid();

            if (!preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
                $customerUuid
            )) {
                throw new RuntimeException('customer_identity_registry_invalid');
            }

            if ($identity) {
                $previousPaymenterUserId = (int) ($identity->paymenter_user_id ?? 0);

                if ($previousPaymenterUserId > 0 && $previousPaymenterUserId !== (int) $user->id) {
                    $previousUserStillExists = User::query()
                        ->where('id', $previousPaymenterUserId)
                        ->exists();

                    if ($previousUserStillExists) {
                        throw new RuntimeException('paymenter_user_identity_conflict');
                    }
                }

                DB::table('fragcity_customer_identities')
                    ->where('id', (int) $identity->id)
                    ->update([
                        'paymenter_user_id' => (int) $user->id,
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('fragcity_customer_identities')->insert([
                    'customer_uuid' => $customerUuid,
                    'steamid64' => $steamId,
                    'paymenter_user_id' => (int) $user->id,
                    'pterodactyl_user_id' => null,
                    'status' => 'active',
                    'last_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $user->properties()->updateOrCreate(
                ['key' => 'fragcity_customer_uuid'],
                ['name' => 'FragCity Customer UUID', 'value' => $customerUuid]
            );

            return $customerUuid;
        }, 3);
    }

    /**
     * Detect Paymenter service rows removed outside the supported Eloquent
     * lifecycle. This records an orphan for admin review but never deletes a
     * Pterodactyl server based on an incomplete or transient observation.
     */
    private function markPaymenterMissingMappings(string $steamId, int $paymenterUserId): int
    {
        if (!Schema::hasTable('fragcity_customer_identities')
            || !Schema::hasTable('fragcity_service_servers')) {
            return 0;
        }

        $identity = DB::table('fragcity_customer_identities')
            ->where('steamid64', $steamId)
            ->where('paymenter_user_id', $paymenterUserId)
            ->first();

        if (!$identity) {
            return 0;
        }

        $mappings = DB::table('fragcity_service_servers')
            ->where('customer_uuid', (string) $identity->customer_uuid)
            ->whereNull('deleted_at')
            ->get(['id', 'paymenter_service_id', 'lifecycle_state']);

        $missing = 0;

        foreach ($mappings as $mapping) {
            $exists = Service::query()
                ->where('id', (int) $mapping->paymenter_service_id)
                ->where('user_id', $paymenterUserId)
                ->exists();

            if ($exists || in_array((string) $mapping->lifecycle_state, ['deleted', 'deleting'], true)) {
                continue;
            }

            if ((string) $mapping->lifecycle_state !== 'paymenter_missing') {
                DB::table('fragcity_service_servers')
                    ->where('id', (int) $mapping->id)
                    ->update([
                        'desired_state' => 'review',
                        'lifecycle_state' => 'paymenter_missing',
                        'last_error' => 'Paymenter service record is missing; external cleanup requires admin review.',
                        'updated_at' => now(),
                    ]);

                if (Schema::hasTable('fragcity_service_lifecycle_events')) {
                    DB::table('fragcity_service_lifecycle_events')->insert([
                        'paymenter_service_id' => (int) $mapping->paymenter_service_id,
                        'customer_uuid' => (string) $identity->customer_uuid,
                        'event_key' => 'paymenter_service_missing',
                        'from_state' => (string) $mapping->lifecycle_state,
                        'to_state' => 'paymenter_missing',
                        'details' => json_encode([
                            'source' => 'website_reconciliation',
                            'action' => 'admin_review_required',
                        ], JSON_THROW_ON_ERROR),
                        'occurred_at' => now(),
                    ]);
                }
            }

            $missing++;
        }

        if ($missing > 0) {
            Log::warning('FragCity found Paymenter services missing outside the lifecycle hook.', [
                'paymenter_user_id' => $paymenterUserId,
                'customer_uuid' => (string) $identity->customer_uuid,
                'missing_services' => $missing,
            ]);
        }

        return $missing;
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

    /**
     * Store a customer's Client API key only after Pterodactyl confirms that
     * the key belongs to the owner of the selected FragCity server.
     */
    public function connectClientApi(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
            'api_token' => ['required', 'string', 'min:20', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'validation_failed'], 422);
        }

        $data = $validator->validated();
        $steamId = (string) $data['steamid64'];
        $serviceId = (int) $data['service_id'];
        $apiToken = trim((string) $data['api_token']);

        if (preg_match('/[\r\n\x00-\x20\x7F]/', $apiToken)) {
            return response()->json(['success' => false, 'error' => 'client_api_key_invalid'], 422);
        }

        $rateLimitKey = 'fragcity_client_api_connect:' . hash('sha256', $steamId);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json(['success' => false, 'error' => 'client_api_connect_rate_limited'], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        try {
            $resolved = $this->resolveCustomerService($steamId, $serviceId, true);
            if (!$resolved) {
                return response()->json(['success' => false, 'error' => 'service_not_found'], 404);
            }

            /** @var User $user */
            $user = $resolved['user'];
            /** @var Service $service */
            $service = $resolved['service'];

            ExtensionHelper::callService(
                $service,
                'verifyCustomerClientApiToken',
                [$apiToken]
            );

            if (!Schema::hasTable('fragcity_client_api_credentials')) {
                return response()->json([
                    'success' => false,
                    'error' => 'client_api_storage_not_ready',
                ], 503);
            }

            $now = now();
            DB::table('fragcity_client_api_credentials')->updateOrInsert(
                ['user_id' => (int) $user->id],
                [
                    'encrypted_token' => Crypt::encryptString($apiToken),
                    'token_fingerprint' => hash('sha256', $apiToken),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            RateLimiter::clear($rateLimitKey);

            return response()->json([
                'success' => true,
                'data' => ['client_api_connected' => true],
            ]);
        } catch (Throwable $e) {
            Log::warning('FragCity customer Client API connection failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'service_id' => $serviceId,
                'exception' => get_class($e),
            ]);

            $message = $e->getMessage();
            $safeError = in_array($message, [
                'client_api_key_invalid',
                'client_api_key_must_belong_to_server_owner',
                'client_api_server_verification_failed',
                'client_api_invalid_response',
                'service_not_active',
            ], true) ? $message : 'client_api_connection_failed';

            $status = match ($safeError) {
                'service_not_active' => 409,
                'client_api_key_must_belong_to_server_owner' => 403,
                default => 422,
            };

            return response()->json([
                'success' => false,
                'error' => $safeError,
            ], $status);
        }
    }

    /**
     * Disconnect the stored Client API credential. The customer can separately
     * revoke the key from Pterodactyl Account -> API Credentials.
     */
    public function disconnectClientApi(Request $request): JsonResponse
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
            return response()->json(['success' => false, 'error' => 'validation_failed'], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];
        $link = DB::table('fragcity_user_links')->where('steamid64', $steamId)->first();
        if (!$link) {
            return response()->json(['success' => false, 'error' => 'service_not_found'], 404);
        }

        if (Schema::hasTable('fragcity_client_api_credentials')) {
            DB::table('fragcity_client_api_credentials')
                ->where('user_id', (int) $link->user_id)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'data' => ['client_api_disconnected' => true],
        ]);
    }

    /**
     * Issue a fresh, short-lived WebSocket credential for one customer-owned
     * CS2 server. The customer's long-lived Client API key stays in Paymenter.
     */
    public function customerConsole(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'validation_failed'], 422);
        }

        $data = $validator->validated();

        try {
            $resolved = $this->resolveCustomerService((string) $data['steamid64'], (int) $data['service_id'], true);
            if (!$resolved) {
                return response()->json(['success' => false, 'error' => 'service_not_found'], 404);
            }

            $token = $this->getStoredCustomerClientApiToken((int) $resolved['user']->id);
            $credentials = ExtensionHelper::callService(
                $resolved['service'],
                'getCustomerConsoleCredentials',
                [$token]
            );

            return response()->json([
                'success' => true,
                'data' => $credentials,
            ]);
        } catch (Throwable $e) {
            Log::warning('FragCity customer console token request failed.', [
                'service_id' => (int) $data['service_id'],
                'exception' => get_class($e),
            ]);

            $message = $e->getMessage();
            $safeError = in_array($message, [
                'client_api_not_connected',
                'client_api_permission_denied',
                'client_api_key_must_belong_to_server_owner',
                'service_not_active',
            ], true) ? $message : 'customer_console_unavailable';

            $status = match ($safeError) {
                'client_api_not_connected', 'service_not_active' => 409,
                'client_api_permission_denied', 'client_api_key_must_belong_to_server_owner' => 403,
                default => 502,
            };

            return response()->json([
                'success' => false,
                'error' => $safeError,
            ], $status);
        }
    }

    /**
     * Proxy approved Client API file actions through the signed server-to-server
     * bridge. File contents are bounded before they reach the Panel.
     */
    public function customerFiles(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request, 300000);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
            'file_action' => ['required', 'string', 'in:list,read,write,create_folder,rename,delete,download,upload_url'],
            'directory' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'string', 'max:2048'],
            'content' => ['nullable', 'string', 'max:262144'],
            'name' => ['nullable', 'string', 'max:128'],
            'from' => ['nullable', 'string', 'max:2048'],
            'to' => ['nullable', 'string', 'max:255'],
            'files' => ['nullable', 'array', 'max:50'],
            'files.*' => ['string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        try {
            $resolved = $this->resolveCustomerService((string) $data['steamid64'], (int) $data['service_id'], true);
            if (!$resolved) {
                return response()->json(['success' => false, 'error' => 'service_not_found'], 404);
            }

            $token = $this->getStoredCustomerClientApiToken((int) $resolved['user']->id);
            $result = ExtensionHelper::callService(
                $resolved['service'],
                'customerFileAction',
                [$token, $data]
            );

            return response()->json([
                'success' => true,
                'data' => is_array($result) ? $result : [],
            ]);
        } catch (Throwable $e) {
            Log::warning('FragCity customer file action failed.', [
                'service_id' => (int) $data['service_id'],
                'file_action' => (string) $data['file_action'],
                'exception' => get_class($e),
            ]);

            $message = $e->getMessage();
            $safeError = in_array($message, [
                'client_api_not_connected',
                'client_api_permission_denied',
                'client_api_key_must_belong_to_server_owner',
                'service_not_active',
                'client_api_file_too_large_to_edit',
                'client_api_file_not_editable',
                'client_api_invalid_file_content',
                'client_api_invalid_file_name',
                'client_api_invalid_file_list',
                'client_api_invalid_file_path',
                'client_api_invalid_file_action',
            ], true) ? $message : 'customer_file_action_failed';

            $status = match ($safeError) {
                'client_api_not_connected', 'service_not_active' => 409,
                'client_api_permission_denied', 'client_api_key_must_belong_to_server_owner' => 403,
                'client_api_file_too_large_to_edit' => 413,
                'client_api_file_not_editable',
                'client_api_invalid_file_content',
                'client_api_invalid_file_name',
                'client_api_invalid_file_list',
                'client_api_invalid_file_path',
                'client_api_invalid_file_action' => 422,
                default => 502,
            };

            return response()->json([
                'success' => false,
                'error' => $safeError,
            ], $status);
        }
    }

    /**
     * Resolve a non-cancelled FragCity CS2 service owned by the linked Steam
     * account. No client-supplied Paymenter user ID is ever trusted.
     *
     * @return array{user:User,service:Service}|null
     */
    private function resolveCustomerService(string $steamId, int $serviceId, bool $requireActive = false): ?array
    {
        $link = DB::table('fragcity_user_links')
            ->where('steamid64', $steamId)
            ->first();

        if (!$link) {
            return null;
        }

        $user = User::query()->find((int) $link->user_id);
        if (!$user) {
            return null;
        }

        $service = Service::query()
            ->where('id', $serviceId)
            ->where('user_id', (int) $user->id)
            ->where('status', '<>', Service::STATUS_CANCELLED)
            ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue'])
            ->first();

        if (!$service || $service->product?->server?->extension !== 'FragCityCS2') {
            return null;
        }

        if ($requireActive && $service->status !== Service::STATUS_ACTIVE) {
            throw new RuntimeException('service_not_active');
        }

        return ['user' => $user, 'service' => $service];
    }

    private function getStoredCustomerClientApiToken(int $userId): string
    {
        if (!Schema::hasTable('fragcity_client_api_credentials')) {
            throw new RuntimeException('client_api_not_connected');
        }

        $row = DB::table('fragcity_client_api_credentials')
            ->where('user_id', $userId)
            ->first();

        if (!$row || !is_string($row->encrypted_token ?? null) || $row->encrypted_token === '') {
            throw new RuntimeException('client_api_not_connected');
        }

        try {
            $token = Crypt::decryptString($row->encrypted_token);
        } catch (Throwable $e) {
            Log::error('FragCity stored Client API credential could not be decrypted.', [
                'paymenter_user_id' => $userId,
                'exception' => get_class($e),
            ]);
            throw new RuntimeException('client_api_not_connected', 0, $e);
        }

        if ($token === '' || preg_match('/[\r\n\x00-\x20\x7F]/', $token)) {
            throw new RuntimeException('client_api_not_connected');
        }

        return $token;
    }

    private function authenticateBridgeRequest(Request $request, int $maxBodyBytes = 16384): ?JsonResponse
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

        if ($maxBodyBytes < 1 || strlen($rawBody) > $maxBodyBytes) {
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
