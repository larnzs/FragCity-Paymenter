<?php

namespace Paymenter\Extensions\Servers\FragCityCS2;

use App\Attributes\ExtensionMeta;
use App\Helpers\ExtensionHelper;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl as PterodactylExtension;
use RuntimeException;
use Throwable;

/**
 * FragCity's CS2 checkout and Egg contract layer.
 *
 * Paymenter/Pterodactyl remains responsible for server lifecycle and API calls.
 * This extension owns the CS2 manifest, validates its field contract, and makes
 * sure UI labels can never be passed as Egg environment values.
 */
#[ExtensionMeta(
    name: 'FragCity CS2',
    description: 'Pterodactyl provisioning with validated settings generated from the FragCity CS2 Egg.',
    version: '1.6.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCityCS2 extends PterodactylExtension
{
    /**
     * Keep Paymenter's standard provisioning credentials separate from the
     * dashboard's dedicated read-only Application API key.
     */
    public function getConfig($values = []): array
    {
        return array_merge(parent::getConfig($values), [
            [
                'name' => 'customer_api_key',
                'label' => 'FragCity Customer Dashboard API Key (Read Only)',
                'type' => 'password',
                'description' => 'Dedicated Pterodactyl Application API key used only to read customer server details. Grant read-only access to Servers, Allocations, Nodes, Locations, Nests and Eggs. The key is encrypted by Paymenter and is never sent to the customer website. During migration, an empty value temporarily falls back to the existing Pterodactyl key.',
                'required' => false,
                'encrypted' => true,
                'validation' => 'nullable|string|min:20|max:255',
            ],
        ]);
    }

    public function getProductConfig($values = []): array
    {
        return array_merge(
            parent::getProductConfig($values),
            $this->loadManifest()['paymenter']['product_fields'] ?? []
        );
    }

    public function getCheckoutConfig(Product $product, $values = [], $settings = []): array
    {
        $fields = $this->loadManifest()['paymenter']['checkout_fields'] ?? [];

        // Paymenter v1.5.9's Checkout component and Blade view expect
        // select options as [egg_value => display_label]. Product settings
        // use FilamentInput, which supports explicit [{value, label}] pairs.
        // Keep the manifest canonical and adapt only this Checkout boundary.
        foreach ($fields as &$field) {
            if (!in_array($field['type'] ?? null, ['select', 'radio'], true)) {
                continue;
            }

            $options = [];
            foreach ($field['options'] ?? [] as $option) {
                $options[(string) $option['value']] = (string) $option['label'];
            }

            $field['options'] = $options;
        }
        unset($field);

        return $fields;
    }

    /**
     * Normalize and validate all FragCity select values before delegating
     * provisioning to Paymenter's official Pterodactyl implementation. Before
     * delegation, ensure the Pterodactyl client account uses FragCity's
     * validated username and the native Paymenter first/last name fields.
     *
     * Older product/service records may contain a display label (for example
     * "غیرفعال") because numeric option keys were previously interpreted as a
     * sequential PHP array by Paymenter 1.5.9. Such legacy labels are mapped
     * back to their explicit Egg value ("0") here. Unknown values are rejected.
     */
    public function createServer(Service $service, $settings, $properties)
    {
        $manifest = $this->loadManifest();

        $settings = $this->normalizeSelectEnvironmentValues(
            $settings,
            $manifest,
            $service->id
        );

        $properties = $this->normalizeSelectEnvironmentValues(
            $properties,
            $manifest,
            $service->id
        );

        $this->assertLifecycleStorageReady();

        return $this->withServiceLifecycleLock($service, function () use ($service, $settings, $properties): array {
            $mapping = DB::table('fragcity_service_servers')
                ->where('paymenter_service_id', (int) $service->id)
                ->first();

            if (
                $mapping
                && (
                    (string) $mapping->lifecycle_state === 'deleted'
                    || $mapping->deleted_at !== null
                )
            ) {
                throw new RuntimeException('This Paymenter service is already deleted in the FragCity lifecycle registry; create a new service instead.');
            }

            // Retry-safe recovery for a Server that exists but whose previous
            // Paymenter job lost its response. Never create a second Client.
            $existing = $this->findPterodactylServerByExternalId((int) $service->id);
            if ($existing !== null) {
                $clientUser = $this->ensurePterodactylClientUser($service, false);
                $clientUserId = (int) ($clientUser['id'] ?? 0);

                if ($clientUserId < 1 || (int) ($existing['user'] ?? 0) !== $clientUserId) {
                    throw new RuntimeException('The existing Pterodactyl server is linked to a different client account.');
                }

                $this->storeServiceLifecycle(
                    $service,
                    $clientUserId,
                    $existing,
                    'active',
                    !empty($existing['suspended']) ? 'suspended' : 'active',
                    'ready'
                );

                $identifier = trim((string) ($existing['identifier'] ?? ''));
                return [
                    'server' => (int) ($existing['id'] ?? 0),
                    'link' => rtrim((string) $this->config('host'), '/') . '/server/' . rawurlencode($identifier),
                ];
            }

            $wasPreviouslyProvisioned = $mapping && (
                (int) ($mapping->pterodactyl_server_id ?? 0) > 0
                || $mapping->last_seen_at !== null
            );
            $recreationAuthorized = $mapping
                && (string) $mapping->desired_state === 'recreate_authorized';

            if ($wasPreviouslyProvisioned && !$recreationAuthorized) {
                $this->storeServiceLifecycle(
                    $service,
                    (int) ($mapping->pterodactyl_user_id ?? 0),
                    null,
                    'active',
                    'missing',
                    'missing_external',
                    'Pterodactyl server is missing. Automatic recreation is blocked; admin approval is required.'
                );

                throw new RuntimeException('The Pterodactyl server for this service was previously provisioned but is now missing. Automatic recreation is blocked pending admin approval.');
            }

            if ($wasPreviouslyProvisioned && $recreationAuthorized) {
                DB::table('fragcity_service_lifecycle_events')->insert([
                    'paymenter_service_id' => (int) $service->id,
                    'customer_uuid' => $mapping->customer_uuid,
                    'event_key' => 'admin_recreation_authorized',
                    'from_state' => 'missing_external',
                    'to_state' => 'provisioning',
                    'details' => json_encode([
                        'source' => 'admin_approved_mapping_flag',
                        'approved' => true,
                    ], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(),
                ]);
            }

            $clientUser = $this->ensurePterodactylClientUser($service, true);
            $clientUserId = (int) ($clientUser['id'] ?? 0);

            if ($clientUserId < 1) {
                throw new RuntimeException('Pterodactyl client identity could not be verified.');
            }

            $this->storeServiceLifecycle(
                $service,
                $clientUserId,
                null,
                'active',
                'unknown',
                'provisioning'
            );

            $created = parent::createServer($service, $settings, $properties);
            $serverId = (int) ($created['server'] ?? 0);

            if ($serverId < 1) {
                throw new RuntimeException('Paymenter returned an invalid Pterodactyl server ID.');
            }

            // Persist the server ID returned by the official provisioner
            // before a second verification request. If that request times out
            // or the process exits, a retry can recover this Server or block
            // unsafe automatic recreation if it is later manually deleted.
            $this->storeServiceLifecycle(
                $service,
                $clientUserId,
                [
                    'id' => $serverId,
                    'user' => $clientUserId,
                ],
                'active',
                'unknown',
                'provisioning'
            );

            $serverResponse = $this->requestApplicationApi(
                'GET',
                '/api/application/servers/' . $serverId
            );
            $attributes = $serverResponse['attributes'] ?? null;

            if (
                !is_array($attributes)
                || (string) ($attributes['external_id'] ?? '') !== (string) $service->id
                || (int) ($attributes['user'] ?? 0) !== $clientUserId
            ) {
                throw new RuntimeException('Pterodactyl server creation could not be verified against the Paymenter service.');
            }

            $this->storeServiceLifecycle(
                $service,
                $clientUserId,
                $attributes,
                'active',
                !empty($attributes['suspended']) ? 'suspended' : 'active',
                'ready'
            );

            return $created;
        });
    }


    /**
     * Create or update the Pterodactyl client identity before the official
     * Paymenter provisioner looks it up by email and creates the server.
     *
     * The username is stored on the Paymenter user as the FragCity-owned
     * "fragcity_username" property because Paymenter has no native username
     * field. The two client names and email use Paymenter's native user fields.
     * Pterodactyl allows a nullable password on Application API user creation;
     * we intentionally do not invent or persist a second plaintext password.
     */
    /**
     * Resolve the Pterodactyl client owned by this Paymenter account and
     * align its native username/name/email fields with FragCity.
     *
     * When called during server provisioning, a missing Pterodactyl client
     * may be created. Customer account-management actions must pass false so
     * they can never create an orphan panel account.
     *
     * @return array<string,mixed> Pterodactyl user attributes.
     */
    private function ensurePterodactylClientUser(Service $service, bool $allowCreate): array
    {
        $orderUser = $service->user;
        if (!$orderUser) {
            throw new RuntimeException('FragCity CS2 provisioning requires a Paymenter user.');
        }

        $paymenterUserId = (int) $orderUser->id;
        $email = mb_strtolower(trim((string) $orderUser->email));
        $firstName = trim((string) $orderUser->first_name);
        $lastName = trim((string) $orderUser->last_name);
        $panelUsername = trim((string) $orderUser->properties()
            ->where('key', 'fragcity_panel_username')
            ->value('value'));
        if ($panelUsername === '') {
            $panelUsername = trim((string) $orderUser->properties()
                ->where('key', 'fragcity_username')
                ->value('value'));
        }
        $username = strtolower($panelUsername);

        if ($paymenterUserId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('FragCity CS2 provisioning found an invalid Paymenter client identity.');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/D', $username)) {
            throw new RuntimeException('FragCity username is missing or is not valid for Pterodactyl.');
        }

        if ($firstName === '' || mb_strlen($firstName) > 191) {
            throw new RuntimeException('FragCity client first name is missing or invalid.');
        }

        if ($lastName === '' || mb_strlen($lastName) > 191) {
            throw new RuntimeException('FragCity client last name is missing or invalid.');
        }

        $this->assertLifecycleStorageReady();

        $identity = DB::table('fragcity_customer_identities')
            ->where('paymenter_user_id', $paymenterUserId)
            ->first();

        if (!$identity) {
            $steamId = trim((string) $orderUser->properties()
                ->where('key', 'fragcity_steamid64')
                ->value('value'));

            if (!preg_match('/^[0-9]{17}$/D', $steamId)) {
                throw new RuntimeException('FragCity customer identity is not registered and no verified SteamID64 is available.');
            }

            // The Bridge remains the single owner of identity generation and
            // persistence; the CS2 extension never invents its own UUID.
            $identityBridge = ExtensionHelper::getExtension('other', 'FragCityAPI');
            if (!method_exists($identityBridge, 'ensureCustomerIdentity')) {
                throw new RuntimeException('FragCityAPI identity bridge is unavailable.');
            }

            $identityBridge->ensureCustomerIdentity($orderUser, $steamId);

            $identity = DB::table('fragcity_customer_identities')
                ->where('paymenter_user_id', $paymenterUserId)
                ->first();
        }

        $customerUuid = trim((string) ($identity->customer_uuid ?? ''));
        $knownPterodactylUserId = (int) ($identity->pterodactyl_user_id ?? 0);

        if (!preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
            $customerUuid
        )) {
            throw new RuntimeException('FragCity customer identity is not registered. Sign in to FragCity again and retry.');
        }

        // The durable UUID, not Paymenter's mutable auto-increment ID, is the
        // Pterodactyl Client external_id. Each server still uses its own
        // Paymenter service ID as the Pterodactyl Server external_id.
        $externalId = $customerUuid;
        $attributes = null;

        if ($knownPterodactylUserId > 0) {
            $known = $this->requestApplicationApi(
                'GET',
                '/api/application/users/' . $knownPterodactylUserId
            );
            $knownAttributes = $known['attributes'] ?? null;

            if (is_array($knownAttributes)) {
                $attributes = $knownAttributes;
            }
        }

        if (!is_array($attributes)) {
            $byUuid = $this->request('/api/application/users', 'get', [
                'filter' => ['external_id' => $externalId],
            ]);
            $uuidRows = $byUuid['data'] ?? [];

            if (is_array($uuidRows) && count($uuidRows) > 1) {
                throw new RuntimeException('The FragCity customer UUID is assigned to multiple Pterodactyl accounts.');
            }

            $attributes = $uuidRows[0]['attributes'] ?? null;
        }

        if (!is_array($attributes)) {
            $byEmail = $this->request('/api/application/users', 'get', [
                'filter' => ['email' => $email],
            ]);
            $emailRows = $byEmail['data'] ?? [];

            if (is_array($emailRows) && count($emailRows) > 1) {
                throw new RuntimeException('Multiple Pterodactyl client accounts use this customer email.');
            }

            $attributes = $emailRows[0]['attributes'] ?? null;
        }

        $clientData = [
            'external_id' => $externalId,
            'email' => $email,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];

        if (!is_array($attributes)) {
            if (!$allowCreate) {
                throw new RuntimeException('The Pterodactyl client account for this service does not exist.');
            }

            $created = $this->request('/api/application/users', 'post', $clientData);
            $attributes = $created['attributes'] ?? null;

            if (!is_array($attributes)) {
                throw new RuntimeException('Pterodactyl did not return the created client account.');
            }
        }

        $pterodactylUserId = (int) ($attributes['id'] ?? 0);
        $existingExternalId = trim((string) ($attributes['external_id'] ?? ''));

        if ($pterodactylUserId < 1) {
            throw new RuntimeException('Pterodactyl returned a client user without a valid ID.');
        }

        if (
            $knownPterodactylUserId > 0
            && $knownPterodactylUserId !== $pterodactylUserId
        ) {
            throw new RuntimeException('FragCity identity registry and Pterodactyl client mapping do not match.');
        }

        $explicitlyMapped = $knownPterodactylUserId === $pterodactylUserId;
        $legacyPaymenterLink = $existingExternalId === (string) $paymenterUserId;

        if (
            $existingExternalId !== $externalId
            && !$explicitlyMapped
            && !$legacyPaymenterLink
        ) {
            throw new RuntimeException(
                'The Pterodactyl email is already linked to a different external account. Admin verification is required.'
            );
        }

        $needsUpdate =
            $existingExternalId !== $externalId
            || strtolower((string) ($attributes['email'] ?? '')) !== $email
            || strtolower((string) ($attributes['username'] ?? '')) !== $username
            || trim((string) ($attributes['first_name'] ?? '')) !== $firstName
            || trim((string) ($attributes['last_name'] ?? '')) !== $lastName;

        if ($needsUpdate) {
            $updated = $this->request(
                '/api/application/users/' . $pterodactylUserId,
                'patch',
                $clientData
            );
            $attributes = $updated['attributes'] ?? array_merge($attributes, $clientData);
        }

        DB::table('fragcity_customer_identities')
            ->where('customer_uuid', $customerUuid)
            ->update([
                'pterodactyl_user_id' => $pterodactylUserId,
                'last_verified_at' => now(),
                'updated_at' => now(),
            ]);

        return $attributes;
    }


    /**
     * Verify the lifecycle registry is installed before provisioning or
     * mutating a remote resource. This avoids creating untracked Pterodactyl
     * servers during an incomplete deployment.
     */
    private function assertLifecycleStorageReady(): void
    {
        if (
            !Schema::hasTable('fragcity_customer_identities')
            || !Schema::hasTable('fragcity_service_servers')
        ) {
            throw new RuntimeException('FragCity lifecycle migrations are not installed.');
        }
    }

    /**
     * Serialize all lifecycle mutations for one Paymenter service.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function withServiceLifecycleLock(Service $service, callable $callback)
    {
        return Cache::lock('fragcity:service-lifecycle:' . (int) $service->id, 120)
            ->block(20, $callback);
    }

    /**
     * Call Pterodactyl's official Application API while preserving real HTTP
     * status codes. Only an actual 404 is represented as null; network errors,
     * authentication errors and 5xx responses remain failures.
     *
     * @return array<string,mixed>|null
     */
    private function requestApplicationApi(string $method, string $path, array $payload = []): ?array
    {
        $baseUrl = rtrim(trim((string) $this->config('host')), '/');
        $apiKey = trim((string) $this->config('api_key'));
        $parts = parse_url($baseUrl);

        if (
            !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)
            || empty($parts['host'])
            || $apiKey === ''
        ) {
            throw new RuntimeException('FragCity Pterodactyl Application API configuration is invalid.');
        }

        $url = $baseUrl . '/' . ltrim($path, '/');
        $request = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(25)
            ->withOptions(['allow_redirects' => false]);

        $method = strtoupper($method);
        if ($method === 'GET') {
            $response = $request->get($url, $payload);
        } elseif ($method === 'DELETE' || $method === 'POST') {
            $response = $request->send($method, $url, $payload === [] ? [] : ['json' => $payload]);
        } else {
            throw new RuntimeException('Unsupported Pterodactyl Application API method.');
        }

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'Pterodactyl Application API request failed with HTTP ' . $response->status() . '.'
            );
        }

        if ($response->status() === 204 || trim($response->body()) === '') {
            return [];
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new RuntimeException('Pterodactyl returned an invalid Application API response.');
        }

        return $decoded;
    }

    /**
     * Return a server only when Pterodactyl confirms the exact Paymenter
     * service external_id. Missing is distinct from API failure.
     *
     * @return array<string,mixed>|null
     */
    private function findPterodactylServerByExternalId(int $serviceId): ?array
    {
        $response = $this->requestApplicationApi(
            'GET',
            '/api/application/servers/external/' . $serviceId
        );

        if ($response === null) {
            return null;
        }

        $attributes = $response['attributes'] ?? null;
        if (
            !is_array($attributes)
            || (string) ($attributes['external_id'] ?? '') !== (string) $serviceId
            || (int) ($attributes['id'] ?? 0) < 1
        ) {
            throw new RuntimeException('Pterodactyl returned a server that does not match this Paymenter service.');
        }

        return $attributes;
    }

    /**
     * Persist one service-to-server mapping without foreign keys to Paymenter
     * rows. Existing server identifiers are retained when a manually deleted
     * Pterodactyl resource can no longer be read.
     *
     * @param array<string,mixed>|null $server
     */
    private function storeServiceLifecycle(
        Service $service,
        int $pterodactylUserId,
        ?array $server,
        string $desiredState,
        string $observedState,
        string $lifecycleState,
        ?string $lastError = null
    ): void {
        $this->assertLifecycleStorageReady();

        $existing = DB::table('fragcity_service_servers')
            ->where('paymenter_service_id', (int) $service->id)
            ->first();

        $identity = DB::table('fragcity_customer_identities')
            ->where('paymenter_user_id', (int) $service->user_id)
            ->first();

        $serverId = (int) ($server['id'] ?? 0);
        $identifier = trim((string) ($server['identifier'] ?? ''));
        $previousLifecycleState = (string) ($existing->lifecycle_state ?? '');
        $previousDesiredState = (string) ($existing->desired_state ?? '');
        $previousObservedState = (string) ($existing->observed_state ?? '');

        $values = [
            'paymenter_user_id' => (int) $service->user_id,
            'customer_uuid' => $identity->customer_uuid ?? ($existing->customer_uuid ?? null),
            'pterodactyl_user_id' => $pterodactylUserId > 0
                ? $pterodactylUserId
                : ($existing->pterodactyl_user_id ?? null),
            'pterodactyl_server_id' => $serverId > 0
                ? $serverId
                : ($existing->pterodactyl_server_id ?? null),
            'server_identifier' => $identifier !== ''
                ? $identifier
                : ($existing->server_identifier ?? null),
            'desired_state' => $desiredState,
            'observed_state' => $observedState,
            'lifecycle_state' => $lifecycleState,
            'last_seen_at' => $server !== null ? now() : ($existing->last_seen_at ?? null),
            'next_reconcile_at' => in_array($lifecycleState, ['deleted', 'missing_external'], true)
                ? null
                : now()->addMinutes(5),
            'deleted_at' => $lifecycleState === 'deleted'
                ? ($existing->deleted_at ?? now())
                : null,
            'last_error' => $lastError,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('fragcity_service_servers')
                ->where('id', (int) $existing->id)
                ->update($values);
        } else {
            DB::table('fragcity_service_servers')->insert(array_merge(
                ['paymenter_service_id' => (int) $service->id, 'created_at' => now()],
                $values
            ));
        }

        $stateChanged = $previousLifecycleState !== $lifecycleState
            || $previousDesiredState !== $desiredState
            || $previousObservedState !== $observedState;

        if ($stateChanged && Schema::hasTable('fragcity_service_lifecycle_events')) {
            DB::table('fragcity_service_lifecycle_events')->insert([
                'paymenter_service_id' => (int) $service->id,
                'customer_uuid' => $values['customer_uuid'],
                'event_key' => 'service_state_changed',
                'from_state' => $previousLifecycleState !== '' ? $previousLifecycleState : null,
                'to_state' => $lifecycleState,
                'details' => json_encode([
                    'desired_state' => $desiredState,
                    'observed_state' => $observedState,
                    'pterodactyl_server_id' => $values['pterodactyl_server_id'],
                    'last_error' => $lastError,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
            ]);
        }
    }

    /**
     * Verify that an external Server belongs to the durable FragCity customer
     * identity associated with this Paymenter service. Never mutate a Server
     * based on external_id alone.
     *
     * @param array<string,mixed> $server
     */
    private function assertPterodactylServerOwner(Service $service, array $server): int
    {
        $clientUser = $this->ensurePterodactylClientUser($service, false);
        $expectedUserId = (int) ($clientUser['id'] ?? 0);
        $actualUserId = (int) ($server['user'] ?? 0);

        if ($expectedUserId < 1 || $actualUserId !== $expectedUserId) {
            throw new RuntimeException(
                'Pterodactyl server ownership does not match the verified FragCity customer identity; lifecycle operation refused.'
            );
        }

        return $expectedUserId;
    }

    /**
     * Suspend the exact Pterodactyl server associated with this service.
     * Repeated calls are safe; a confirmed missing server is recorded rather
     * than recreated.
     */
    public function suspendServer(Service $service, $settings, $properties): bool
    {
        return $this->withServiceLifecycleLock($service, function () use ($service): bool {
            $mapping = DB::table('fragcity_service_servers')
                ->where('paymenter_service_id', (int) $service->id)
                ->first();

            if (
                $mapping
                && (
                    (string) $mapping->lifecycle_state === 'deleted'
                    || $mapping->deleted_at !== null
                )
            ) {
                throw new RuntimeException('This service is permanently deleted in the FragCity lifecycle registry.');
            }

            $server = $this->findPterodactylServerByExternalId((int) $service->id);

            if ($server === null) {
                $this->storeServiceLifecycle(
                    $service,
                    0,
                    null,
                    'suspended',
                    'missing',
                    'missing_external',
                    'Pterodactyl server returned 404 during suspend.'
                );
                return true;
            }

            $expectedClientUserId = $this->assertPterodactylServerOwner($service, $server);
            $this->storeServiceLifecycle($service, $expectedClientUserId, $server, 'suspended', 'suspended', 'suspending');

            if (empty($server['suspended'])) {
                $result = $this->requestApplicationApi(
                    'POST',
                    '/api/application/servers/' . (int) $server['id'] . '/suspend'
                );

                if ($result === null) {
                    $this->storeServiceLifecycle(
                        $service,
                        0,
                        null,
                        'suspended',
                        'missing',
                        'missing_external',
                        'Pterodactyl server disappeared during suspend.'
                    );
                    return true;
                }
            }

            $server['suspended'] = true;
            $this->storeServiceLifecycle($service, $expectedClientUserId, $server, 'suspended', 'suspended', 'suspended');
            return true;
        });
    }

    /**
     * Unsuspend the existing server. This method never creates a replacement
     * if the external server was manually deleted.
     */
    public function unsuspendServer(Service $service, $settings, $properties): bool
    {
        return $this->withServiceLifecycleLock($service, function () use ($service): bool {
            $mapping = DB::table('fragcity_service_servers')
                ->where('paymenter_service_id', (int) $service->id)
                ->first();

            if (
                $mapping
                && (
                    (string) $mapping->lifecycle_state === 'deleted'
                    || $mapping->deleted_at !== null
                )
            ) {
                throw new RuntimeException('This service is permanently deleted in the FragCity lifecycle registry.');
            }

            $server = $this->findPterodactylServerByExternalId((int) $service->id);

            if ($server === null) {
                $this->storeServiceLifecycle(
                    $service,
                    0,
                    null,
                    'active',
                    'missing',
                    'missing_external',
                    'Pterodactyl server returned 404 during unsuspend.'
                );
                return true;
            }

            $expectedClientUserId = $this->assertPterodactylServerOwner($service, $server);
            $this->storeServiceLifecycle($service, $expectedClientUserId, $server, 'active', 'suspended', 'unsuspending');

            if (!empty($server['suspended'])) {
                $result = $this->requestApplicationApi(
                    'POST',
                    '/api/application/servers/' . (int) $server['id'] . '/unsuspend'
                );

                if ($result === null) {
                    $this->storeServiceLifecycle(
                        $service,
                        0,
                        null,
                        'active',
                        'missing',
                        'missing_external',
                        'Pterodactyl server disappeared during unsuspend.'
                    );
                    return true;
                }
            }

            $server['suspended'] = false;
            $this->storeServiceLifecycle($service, $expectedClientUserId, $server, 'active', 'active', 'active');
            return true;
        });
    }

    /**
     * Delete only the server whose external_id is this Paymenter service ID.
     * A 404 is an idempotent success; all other API failures abort the
     * Paymenter Eloquent deletion hook so the service row is not lost.
     */
    public function terminateServer(Service $service, $settings, $properties): array
    {
        return $this->withServiceLifecycleLock($service, function () use ($service): array {
            $existingMapping = DB::table('fragcity_service_servers')
                ->where('paymenter_service_id', (int) $service->id)
                ->first();

            if (
                $existingMapping
                && (string) $existingMapping->lifecycle_state === 'deleted'
                && $existingMapping->deleted_at !== null
            ) {
                return [
                    'terminated' => true,
                    'server_id' => (int) ($existingMapping->pterodactyl_server_id ?? 0),
                    'already_deleted' => true,
                ];
            }

            $server = $this->findPterodactylServerByExternalId((int) $service->id);
            $expectedClientUserId = $server !== null
                ? $this->assertPterodactylServerOwner($service, $server)
                : 0;

            $this->storeServiceLifecycle(
                $service,
                $expectedClientUserId,
                $server,
                'deleted',
                $server === null ? 'missing' : (!empty($server['suspended']) ? 'suspended' : 'active'),
                'deleting'
            );

            if ($server !== null) {
                $this->requestApplicationApi(
                    'DELETE',
                    '/api/application/servers/' . (int) $server['id']
                );
            }

            $this->storeServiceLifecycle(
                $service,
                $expectedClientUserId,
                null,
                'deleted',
                'deleted',
                'deleted'
            );

            return [
                'terminated' => true,
                'server_id' => (int) ($server['id'] ?? 0),
            ];
        });
    }

    /**
     * Read one server through the dedicated dashboard Application API key.
     *
     * The read key is optional only to keep existing installations online
     * during rollout. Once configured, it is used exclusively for this GET
     * request; all existing provisioning and account-management operations
     * continue to use Paymenter's inherited Pterodactyl integration key.
     *
     * @return array<string,mixed>
     */
    private function requestCustomerDashboardDetails(string $path): array
    {
        $apiKey = trim((string) $this->config('customer_api_key'));

        if ($apiKey === '') {
            $payload = $this->requestApplicationApi('GET', $path);
            if ($payload === null) {
                throw new RuntimeException('Pterodactyl server not found.');
            }

            return $payload;
        }

        if (
            strlen($apiKey) < 20
            || strlen($apiKey) > 255
            || preg_match('/[\\r\\n\\x00-\\x20\\x7F]/', $apiKey)
        ) {
            throw new RuntimeException('FragCity dashboard read-only API key is invalid.');
        }

        $baseUrl = trim((string) $this->config('host'));
        $parts = parse_url($baseUrl);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
        ) {
            throw new RuntimeException('Pterodactyl host must be a valid HTTPS origin.');
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (!$response->successful()) {
            if ($response->status() === 404) {
                throw new RuntimeException('Pterodactyl server not found.');
            }

            if (in_array($response->status(), [401, 403], true)) {
                throw new RuntimeException(
                    'FragCity dashboard API key is invalid or lacks the required read permissions.'
                );
            }

            throw new RuntimeException(
                'Pterodactyl dashboard details request failed with HTTP ' . $response->status() . '.'
            );
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('Pterodactyl returned an invalid dashboard details response.');
        }

        return $payload;
    }

    /**
     * Return an allow-listed server summary and panel login identity for the
     * authenticated owner. This also repairs legacy client usernames and
     * attaches a missing external_id, but never changes the user's password.
     *
     * @return array<string,mixed>
     */
    public function getCustomerPanelSummary(Service $service, $settings, $properties): array
    {
        return $this->withServiceLifecycleLock($service, function () use ($service): array {
            return $this->getCustomerPanelSummaryLocked($service);
        });
    }

    /**
     * Read and persist a server summary while holding the same per-service
     * lock used by create, suspend, unsuspend and terminate operations.
     */
    private function getCustomerPanelSummaryLocked(Service $service): array
    {
        try {
            $clientUser = $this->ensurePterodactylClientUser($service, false);
        } catch (RuntimeException $e) {
            if (
                (string) $service->status === Service::STATUS_PENDING
                && str_contains(strtolower($e->getMessage()), 'does not exist')
            ) {
                return [
                    'service_id' => (int) $service->id,
                    'product_name' => (string) ($service->product?->name ?? 'CS2 Server'),
                    'service_status' => (string) $service->status,
                    'state' => 'provisioning',
                    'panel' => null,
                    'server' => null,
                ];
            }

            throw $e;
        }

        $clientUserId = (int) ($clientUser['id'] ?? 0);
        if ($clientUserId < 1) {
            throw new RuntimeException('Pterodactyl client identity could not be verified.');
        }

        $previousMapping = DB::table('fragcity_service_servers')
            ->where('paymenter_service_id', (int) $service->id)
            ->first();

        if (
            $previousMapping
            && (
                (string) $previousMapping->lifecycle_state === 'deleted'
                || $previousMapping->deleted_at !== null
            )
        ) {
            return [
                'service_id' => (int) $service->id,
                'product_name' => (string) ($service->product?->name ?? 'CS2 Server'),
                'service_status' => (string) $service->status,
                'state' => 'deleted',
                'panel' => null,
                'server' => null,
            ];
        }

        try {
            $serverResponse = $this->requestCustomerDashboardDetails(
                '/api/application/servers/external/' . (int) $service->id
                . '?include=allocations,node,location,egg,nest'
            );
        } catch (Exception $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'pterodactyl server not found')) {
                // A 404 is only "missing external" when FragCity has prior
                // evidence that this Paymenter service owned a real Server.
                // A never-provisioned service remains provisioning so a
                // failed first CreateJob can be retried safely.
                $wasPreviouslyProvisioned = $previousMapping && (
                    (int) ($previousMapping->pterodactyl_server_id ?? 0) > 0
                    || $previousMapping->last_seen_at !== null
                );
                $missingState = $wasPreviouslyProvisioned ? 'missing_external' : 'provisioning';
                $desiredState = $service->status === Service::STATUS_SUSPENDED ? 'suspended' : 'active';

                $this->storeServiceLifecycle(
                    $service,
                    $clientUserId,
                    null,
                    $desiredState,
                    'missing',
                    $missingState,
                    $wasPreviouslyProvisioned
                        ? 'Pterodactyl confirmed that a previously verified service server no longer exists.'
                        : null
                );

                return [
                    'service_id' => (int) $service->id,
                    'product_name' => (string) ($service->product?->name ?? 'CS2 Server'),
                    'service_status' => (string) $service->status,
                    'state' => $missingState,
                    'panel' => null,
                    'server' => null,
                ];
            }

            throw $e;
        }

        $attributes = $serverResponse['attributes'] ?? null;
        if (!is_array($attributes)) {
            throw new RuntimeException('Pterodactyl returned an invalid server record.');
        }

        $serverExternalId = trim((string) ($attributes['external_id'] ?? ''));
        $ownerUserId = (int) ($attributes['user'] ?? 0);
        $identifier = trim((string) ($attributes['identifier'] ?? ''));
        $uuid = trim((string) ($attributes['uuid'] ?? ''));

        if ($serverExternalId !== (string) $service->id || $ownerUserId !== $clientUserId) {
            throw new RuntimeException('The Pterodactyl server ownership link does not match this Paymenter service.');
        }

        if ($identifier === '' || $uuid === '') {
            throw new RuntimeException('Pterodactyl returned incomplete server identifiers.');
        }

        $isSuspended = (bool) ($attributes['suspended'] ?? false);
        $this->storeServiceLifecycle(
            $service,
            $clientUserId,
            $attributes,
            $service->status === Service::STATUS_SUSPENDED ? 'suspended' : 'active',
            $isSuspended ? 'suspended' : 'active',
            'ready'
        );

        $limits = is_array($attributes['limits'] ?? null) ? $attributes['limits'] : [];
        $featureLimits = is_array($attributes['feature_limits'] ?? null)
            ? $attributes['feature_limits']
            : [];

        $relationships = is_array($attributes['relationships'] ?? null)
            ? $attributes['relationships']
            : [];
        $primaryAllocationId = (int) ($attributes['allocation'] ?? 0);
        $allocationRows = $relationships['allocations']['data'] ?? [];
        $primaryAllocation = null;
        if (is_array($allocationRows)) {
            foreach ($allocationRows as $allocationRow) {
                $allocationAttributes = is_array($allocationRow['attributes'] ?? null)
                    ? $allocationRow['attributes']
                    : [];
                if ((int) ($allocationAttributes['id'] ?? 0) === $primaryAllocationId) {
                    $primaryAllocation = $allocationAttributes;
                    break;
                }
            }

            if ($primaryAllocation === null) {
                foreach ($allocationRows as $allocationRow) {
                    $allocationAttributes = is_array($allocationRow['attributes'] ?? null)
                        ? $allocationRow['attributes']
                        : [];
                    if (!empty($allocationAttributes['is_default'])) {
                        $primaryAllocation = $allocationAttributes;
                        break;
                    }
                }
            }

            if ($primaryAllocation === null && isset($allocationRows[0]['attributes'])
                && is_array($allocationRows[0]['attributes'])) {
                $primaryAllocation = $allocationRows[0]['attributes'];
            }
        }
        $primaryAllocation = is_array($primaryAllocation) ? $primaryAllocation : [];

        $relationshipAttributes = static function (array $source, string $name): array {
            $relationship = $source[$name] ?? [];
            if (!is_array($relationship)) {
                return [];
            }

            $attributes = $relationship['attributes'] ?? null;
            if (is_array($attributes)) {
                return $attributes;
            }

            $dataAttributes = $relationship['data']['attributes'] ?? null;
            return is_array($dataAttributes) ? $dataAttributes : [];
        };

        $nodeAttributes = $relationshipAttributes($relationships, 'node');
        $nodeRelationships = is_array($nodeAttributes['relationships'] ?? null)
            ? $nodeAttributes['relationships']
            : [];
        $locationAttributes = $relationshipAttributes($relationships, 'location');
        if ($locationAttributes === []) {
            $locationAttributes = $relationshipAttributes($nodeRelationships, 'location');
        }
        $nestAttributes = $relationshipAttributes($relationships, 'nest');
        $eggAttributes = $relationshipAttributes($relationships, 'egg');

        $serverAllocations = [];
        if (is_array($allocationRows)) {
            foreach ($allocationRows as $allocationRow) {
                $allocationAttributes = is_array($allocationRow['attributes'] ?? null)
                    ? $allocationRow['attributes']
                    : [];
                $allocationId = (int) ($allocationAttributes['id'] ?? 0);
                $allocationIp = trim((string) ($allocationAttributes['ip'] ?? ''));
                $allocationAlias = trim((string) (
                    $allocationAttributes['alias'] ?? $allocationAttributes['ip_alias'] ?? ''
                ));
                $allocationPort = (int) ($allocationAttributes['port'] ?? 0);

                if ($allocationId < 1 || ($allocationIp === '' && $allocationAlias === '' && $allocationPort < 1)) {
                    continue;
                }

                $serverAllocations[] = [
                    'id' => $allocationId,
                    'ip' => $allocationIp,
                    'alias' => $allocationAlias,
                    'port' => $allocationPort,
                    'is_default' => $allocationId === $primaryAllocationId
                        || !empty($allocationAttributes['is_default']),
                ];
            }
        }

        $container = is_array($attributes['container'] ?? null) ? $attributes['container'] : [];

        $panelBaseUrl = rtrim((string) $this->config('host'), '/');

        return [
            'service_id' => (int) $service->id,
            'product_name' => (string) ($service->product?->name ?? 'CS2 Server'),
            'service_status' => (string) $service->status,
            'state' => 'ready',
            'panel' => [
                'base_url' => $panelBaseUrl,
                'server_url' => $panelBaseUrl . '/server/' . rawurlencode($identifier),
                'username' => (string) ($clientUser['username'] ?? ''),
                'email' => (string) ($clientUser['email'] ?? ''),
                'password_set' => (bool) $service->user?->properties()
                    ->where('key', 'fragcity_panel_password_set_at')
                    ->exists(),
            ],
            'server' => [
                'id' => (int) ($attributes['id'] ?? 0),
                'uuid' => $uuid,
                'identifier' => $identifier,
                'name' => (string) ($attributes['name'] ?? ''),
                'description' => (string) ($attributes['description'] ?? ''),
                'suspended' => (bool) ($attributes['suspended'] ?? false),
                'server_status' => (string) ($attributes['status'] ?? ''),
                'node' => (int) ($attributes['node'] ?? 0),
                'allocation' => $primaryAllocationId,
                'allocation_ip' => trim((string) ($primaryAllocation['ip'] ?? '')),
                'allocation_port' => (int) ($primaryAllocation['port'] ?? 0),
                'allocation_alias' => trim((string) ($primaryAllocation['alias'] ?? $primaryAllocation['ip_alias'] ?? '')),
                'allocations' => $serverAllocations,
                'node_name' => trim((string) ($nodeAttributes['name'] ?? '')),
                'node_maintenance_mode' => array_key_exists('maintenance_mode', $nodeAttributes)
                    ? (bool) $nodeAttributes['maintenance_mode']
                    : null,
                'location_id' => (int) ($locationAttributes['id'] ?? $nodeAttributes['location_id'] ?? 0),
                'location_short' => trim((string) ($locationAttributes['short'] ?? '')),
                'location_long' => trim((string) ($locationAttributes['long'] ?? '')),
                'nest_id' => (int) ($nestAttributes['id'] ?? $attributes['nest'] ?? 0),
                'nest_name' => trim((string) ($nestAttributes['name'] ?? '')),
                'egg_id' => (int) ($eggAttributes['id'] ?? $attributes['egg'] ?? 0),
                'egg_name' => trim((string) ($eggAttributes['name'] ?? '')),
                'docker_image' => trim((string) ($container['image'] ?? $attributes['docker_image'] ?? '')),
                'installed' => array_key_exists('installed', $container)
                    ? (bool) $container['installed']
                    : null,
                'updated_at' => (string) ($attributes['updated_at'] ?? ''),
                'limits' => [
                    'memory' => (int) ($limits['memory'] ?? 0),
                    'swap' => (int) ($limits['swap'] ?? 0),
                    'disk' => (int) ($limits['disk'] ?? 0),
                    'cpu' => (int) ($limits['cpu'] ?? 0),
                    'io' => (int) ($limits['io'] ?? 0),
                    'threads' => $limits['threads'] ?? null,
                    'oom_disabled' => (bool) ($limits['oom_disabled'] ?? false),
                ],
                'feature_limits' => [
                    'databases' => (int) ($featureLimits['databases'] ?? 0),
                    'allocations' => (int) ($featureLimits['allocations'] ?? 0),
                    'backups' => (int) ($featureLimits['backups'] ?? 0),
                ],
                'created_at' => (string) ($attributes['created_at'] ?? ''),
                'service_created_at' => $service->created_at?->toIso8601String(),
                'expires_at' => $service->expires_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Set or replace the customer-controlled Pterodactyl password.
     * Plaintext exists only for this request and is never returned or logged.
     */
    public function setCustomerPanelPassword(
        Service $service,
        $settings,
        $properties,
        string $password
    ): array {
        if (strlen($password) < 12 || strlen($password) > 128
            || preg_match('/\\s/', $password)
            || preg_match('/\\p{L}/u', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
        ) {
            throw new RuntimeException('The panel password does not meet the required policy.');
        }

        $clientUser = $this->ensurePterodactylClientUser($service, false);
        $clientUserId = (int) ($clientUser['id'] ?? 0);
        if ($clientUserId < 1) {
            throw new RuntimeException('Pterodactyl client identity could not be verified.');
        }

        $serverResponse = $this->request(
            '/api/application/servers/external/' . (int) $service->id,
            'get'
        );
        $attributes = $serverResponse['attributes'] ?? null;

        if (
            !is_array($attributes)
            || trim((string) ($attributes['external_id'] ?? '')) !== (string) $service->id
            || (int) ($attributes['user'] ?? 0) !== $clientUserId
        ) {
            throw new RuntimeException('The Pterodactyl server ownership link does not match this Paymenter service.');
        }

        // Pterodactyl v1.11.10's UpdateUserRequest still requires the
        // native identity fields on update; include them with the password.
        // The password is hashed by Pterodactyl's UserUpdateService.
        $this->request(
            '/api/application/users/' . $clientUserId,
            'patch',
            [
                'external_id' => (string) ($clientUser['external_id'] ?? ''),
                'email' => (string) ($clientUser['email'] ?? ''),
                'username' => (string) ($clientUser['username'] ?? ''),
                'first_name' => (string) ($clientUser['first_name'] ?? ''),
                'last_name' => (string) ($clientUser['last_name'] ?? ''),
                'password' => $password,
            ]
        );

        return [
            'updated' => true,
            'username' => (string) ($clientUser['username'] ?? ''),
            'email' => (string) ($clientUser['email'] ?? ''),
        ];
    }

    /**
     * Update the shared Pterodactyl client username for this FragCity account.
     * The dedicated Paymenter property keeps the editable panel username
     * independent from the user's primary FragCity username.
     */
    public function setCustomerPanelUsername(
        Service $service,
        $settings,
        $properties,
        string $username
    ): array {
        $username = strtolower(trim($username));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/D', $username)) {
            throw new RuntimeException('panel_username_invalid');
        }

        $clientUser = $this->ensurePterodactylClientUser($service, false);
        $clientUserId = (int) ($clientUser['id'] ?? 0);
        if ($clientUserId < 1) {
            throw new RuntimeException('Pterodactyl client identity could not be verified.');
        }

        $serverResponse = $this->request(
            '/api/application/servers/external/' . (int) $service->id,
            'get'
        );
        $serverAttributes = $serverResponse['attributes'] ?? null;

        if (
            !is_array($serverAttributes)
            || trim((string) ($serverAttributes['external_id'] ?? '')) !== (string) $service->id
            || (int) ($serverAttributes['user'] ?? 0) !== $clientUserId
        ) {
            throw new RuntimeException('The Pterodactyl server ownership link does not match this Paymenter service.');
        }

        try {
            $updated = $this->request(
                '/api/application/users/' . $clientUserId,
                'patch',
                [
                    'external_id' => (string) ($clientUser['external_id'] ?? ''),
                    'email' => (string) ($clientUser['email'] ?? ''),
                    'username' => $username,
                    'first_name' => (string) ($clientUser['first_name'] ?? ''),
                    'last_name' => (string) ($clientUser['last_name'] ?? ''),
                ]
            );
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            if (
                str_contains($message, 'username')
                && (
                    str_contains($message, 'taken')
                    || str_contains($message, 'already')
                    || str_contains($message, 'unique')
                )
            ) {
                throw new RuntimeException('panel_username_already_in_use', 0, $e);
            }

            throw $e;
        }

        $orderUser = $service->user;
        if (!$orderUser) {
            throw new RuntimeException('FragCity CS2 service has no linked Paymenter user.');
        }

        $orderUser->properties()->updateOrCreate(
            ['key' => 'fragcity_panel_username'],
            ['name' => 'FragCity Pterodactyl Username', 'value' => $username]
        );

        $updatedAttributes = $updated['attributes'] ?? [];
        return [
            'updated' => true,
            'username' => (string) ($updatedAttributes['username'] ?? $username),
        ];
    }

    /**
     * Verify that a customer Client API key belongs to the actual owner of
     * this exact FragCity service. A valid sub-user token is not sufficient.
     *
     * @return array{base_url:string,identifier:string,uuid:string}
     */
    private function getCustomerClientApiContext(Service $service, string $token): array
    {
        $token = trim($token);
        if (
            strlen($token) < 20
            || strlen($token) > 255
            || preg_match('/[\r\n\x00-\x20\x7F]/', $token)
        ) {
            throw new RuntimeException('client_api_key_invalid');
        }

        $baseUrl = rtrim(trim((string) $this->config('host')), '/');
        $parts = parse_url($baseUrl);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
        ) {
            throw new RuntimeException('Pterodactyl host must be a valid HTTPS origin.');
        }

        $clientUser = $this->ensurePterodactylClientUser($service, false);
        $clientUserId = (int) ($clientUser['id'] ?? 0);
        if ($clientUserId < 1) {
            throw new RuntimeException('Pterodactyl client identity could not be verified.');
        }

        // Independently bind the Paymenter Service to its Pterodactyl server
        // using the Application API. This prevents a client token for another
        // server/account from being attached to this Paymenter service.
        $applicationResponse = $this->requestCustomerDashboardDetails(
            '/api/application/servers/external/' . (int) $service->id
        );
        $applicationAttributes = $applicationResponse['attributes'] ?? null;
        if (!is_array($applicationAttributes)) {
            throw new RuntimeException('Pterodactyl returned an invalid server record.');
        }

        $identifier = trim((string) ($applicationAttributes['identifier'] ?? ''));
        $uuid = trim((string) ($applicationAttributes['uuid'] ?? ''));
        if (
            trim((string) ($applicationAttributes['external_id'] ?? '')) !== (string) $service->id
            || (int) ($applicationAttributes['user'] ?? 0) !== $clientUserId
            || $identifier === ''
            || $uuid === ''
        ) {
            throw new RuntimeException('The Pterodactyl server ownership link does not match this Paymenter service.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(12)
            ->withOptions(['allow_redirects' => false])
            ->get($baseUrl . '/api/client/servers/' . rawurlencode($identifier));

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('client_api_key_invalid');
        }
        if (!$response->successful()) {
            throw new RuntimeException('client_api_server_verification_failed');
        }

        $clientResponse = $response->json();
        $clientAttributes = $clientResponse['attributes'] ?? null;
        $meta = $clientResponse['meta'] ?? [];
        if (
            !is_array($clientAttributes)
            || !is_array($meta)
            || ($meta['is_server_owner'] ?? false) !== true
            || trim((string) ($clientAttributes['uuid'] ?? '')) !== $uuid
            || trim((string) ($clientAttributes['identifier'] ?? '')) !== $identifier
        ) {
            throw new RuntimeException('client_api_key_must_belong_to_server_owner');
        }

        return [
            'base_url' => $baseUrl,
            'identifier' => $identifier,
            'uuid' => $uuid,
        ];
    }

    /**
     * Make a request to the Pterodactyl Client API only after confirming both
     * the Application API ownership link and the Client API owner identity.
     *
     * @param array<string,mixed>|null $payload
     * @param array<string,string|int|bool> $query
     * @return array<string,mixed>|string
     */
    private function requestCustomerClientApi(
        Service $service,
        string $token,
        string $method,
        string $endpoint,
        array $query = [],
        ?array $payload = null,
        ?string $rawBody = null
    ): array|string {
        $context = $this->getCustomerClientApiContext($service, $token);
        $url = $context['base_url']
            . '/api/client/servers/'
            . rawurlencode($context['identifier'])
            . '/'
            . ltrim($endpoint, '/');

        $request = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->withOptions(['allow_redirects' => false]);

        $method = strtoupper($method);
        if ($method === 'GET') {
            $response = $request->get($url, $query);
        } elseif ($rawBody !== null) {
            if ($query !== []) {
                $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }
            $response = $request->send($method, $url, [
                'body' => $rawBody,
                'headers' => ['Content-Type' => 'text/plain; charset=UTF-8'],
            ]);
        } else {
            $response = match ($method) {
                'POST' => $request->post($url, $payload ?? []),
                'PUT' => $request->put($url, $payload ?? []),
                'DELETE' => $request->delete($url, $payload ?? []),
                default => throw new RuntimeException('Unsupported Pterodactyl Client API method.'),
            };
        }

        if ($response->status() === 204) {
            return ['success' => true];
        }

        if (!$response->successful()) {
            if (in_array($response->status(), [401, 403], true)) {
                throw new RuntimeException('client_api_permission_denied');
            }
            if ($response->status() === 404) {
                throw new RuntimeException('client_api_resource_not_found');
            }
            throw new RuntimeException('client_api_request_failed');
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = $response->json();
            if (!is_array($decoded)) {
                throw new RuntimeException('client_api_invalid_response');
            }

            return $decoded;
        }

        return $response->body();
    }

    /**
     * Validate a Client API token before it is stored by the FragCity Bridge.
     */
    public function verifyCustomerClientApiToken(
        Service $service,
        $settings,
        $properties,
        string $token
    ): array {
        $this->getCustomerClientApiContext($service, $token);

        return ['verified' => true];
    }

    /**
     * Generate a short-lived Pterodactyl WebSocket token for the customer's
     * owned server. The long-lived Client API key is never returned.
     *
     * @return array{socket:string,token:string,expires_in:int,identifier:string}
     */
    public function getCustomerConsoleCredentials(
        Service $service,
        $settings,
        $properties,
        string $token
    ): array {
        $context = $this->getCustomerClientApiContext($service, $token);
        $response = $this->requestCustomerClientApi(
            $service,
            $token,
            'GET',
            '/websocket'
        );

        $data = is_array($response) && is_array($response['data'] ?? null)
            ? $response['data']
            : [];
        $socket = trim((string) ($data['socket'] ?? ''));
        $shortLivedToken = trim((string) ($data['token'] ?? ''));
        $socketParts = parse_url($socket);

        if (
            $shortLivedToken === ''
            || !is_array($socketParts)
            || strtolower((string) ($socketParts['scheme'] ?? '')) !== 'wss'
            || empty($socketParts['host'])
            || isset($socketParts['user'])
            || isset($socketParts['pass'])
        ) {
            throw new RuntimeException('Pterodactyl returned invalid WebSocket credentials.');
        }

        return [
            'socket' => $socket,
            'token' => $shortLivedToken,
            'expires_in' => 600,
            'identifier' => $context['identifier'],
        ];
    }

    /**
     * Perform a narrowly allow-listed file-manager operation for this service.
     * Pterodactyl still enforces the owner's per-server file permissions.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function customerFileAction(
        Service $service,
        $settings,
        $properties,
        string $token,
        array $input
    ): array {
        $action = strtolower(trim((string) ($input['file_action'] ?? '')));
        $directory = $this->validateCustomerFilePath(
            (string) ($input['directory'] ?? '/'),
            true
        );

        if ($action === 'list') {
            $response = $this->requestCustomerClientApi(
                $service,
                $token,
                'GET',
                '/files/list',
                ['directory' => $directory]
            );

            $rows = is_array($response) && is_array($response['data'] ?? null)
                ? $response['data']
                : [];
            $items = [];
            foreach ($rows as $row) {
                $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
                $name = trim((string) ($attributes['name'] ?? ''));
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }

                $isFile = (bool) ($attributes['is_file'] ?? false);
                $isSymlink = (bool) ($attributes['is_symlink'] ?? false);
                $mimetype = strtolower((string) ($attributes['mimetype'] ?? ''));
                $size = max(0, (int) ($attributes['size'] ?? 0));
                $isKnownTextFile = $this->isCustomerEditableFilePath('/' . $name);

                $items[] = [
                    'name' => $name,
                    'is_file' => $isFile,
                    'is_symlink' => $isSymlink,
                    'is_editable' => $isFile && !$isSymlink && $size <= 262144 && $isKnownTextFile,
                    'mimetype' => $mimetype,
                    'size' => $size,
                    'mode' => (string) ($attributes['mode'] ?? ''),
                    'modified_at' => (string) ($attributes['modified_at'] ?? ''),
                ];
            }

            return ['directory' => $directory, 'items' => $items];
        }

        if ($action === 'read') {
            $file = $this->validateCustomerFilePath((string) ($input['file'] ?? ''), false);
            if (!$this->isCustomerEditableFilePath($file)) {
                throw new RuntimeException('client_api_file_not_editable');
            }

            $body = $this->requestCustomerClientApi(
                $service,
                $token,
                'GET',
                '/files/contents',
                ['file' => $file]
            );

            if (!is_string($body) || strlen($body) > 262144) {
                throw new RuntimeException('client_api_file_too_large_to_edit');
            }

            return ['file' => $file, 'content' => $body];
        }

        if ($action === 'write') {
            $file = $this->validateCustomerFilePath((string) ($input['file'] ?? ''), false);
            if (!$this->isCustomerEditableFilePath($file)) {
                throw new RuntimeException('client_api_file_not_editable');
            }

            $content = $input['content'] ?? null;
            if (!is_string($content) || strlen($content) > 262144) {
                throw new RuntimeException('client_api_invalid_file_content');
            }

            $this->requestCustomerClientApi(
                $service,
                $token,
                'POST',
                '/files/write',
                ['file' => $file],
                null,
                $content
            );

            return ['written' => true, 'file' => $file];
        }

        if ($action === 'create_folder') {
            $name = trim((string) ($input['name'] ?? ''));
            if (
                $name === ''
                || strlen($name) > 128
                || str_contains($name, '/')
                || str_contains($name, '\\')
                || $name === '.'
                || $name === '..'
                || preg_match('/[\x00-\x1F\x7F]/', $name)
            ) {
                throw new RuntimeException('client_api_invalid_file_name');
            }

            $this->requestCustomerClientApi(
                $service,
                $token,
                'POST',
                '/files/create-folder',
                [],
                ['root' => $directory, 'name' => $name]
            );

            return ['created' => true, 'directory' => $directory, 'name' => $name];
        }

        if ($action === 'rename') {
            $from = $this->validateCustomerFilePath((string) ($input['from'] ?? ''), false);
            $to = trim((string) ($input['to'] ?? ''));
            if (
                $to === ''
                || strlen($to) > 255
                || str_contains($to, '/')
                || str_contains($to, '\\')
                || $to === '.'
                || $to === '..'
                || preg_match('/[\x00-\x1F\x7F]/', $to)
            ) {
                throw new RuntimeException('client_api_invalid_file_name');
            }

            $this->requestCustomerClientApi(
                $service,
                $token,
                'PUT',
                '/files/rename',
                [],
                ['root' => $directory, 'files' => [['from' => basename($from), 'to' => $to]]]
            );

            return ['renamed' => true];
        }

        if ($action === 'delete') {
            $files = $input['files'] ?? null;
            if (!is_array($files) || $files === [] || count($files) > 50) {
                throw new RuntimeException('client_api_invalid_file_list');
            }

            $safeFiles = [];
            foreach ($files as $file) {
                $safePath = $this->validateCustomerFilePath((string) $file, false);
                $safeFiles[] = ltrim($safePath, '/');
            }

            $this->requestCustomerClientApi(
                $service,
                $token,
                'POST',
                '/files/delete',
                [],
                ['root' => $directory, 'files' => $safeFiles]
            );

            return ['deleted' => true, 'count' => count($safeFiles)];
        }

        if ($action === 'download') {
            $file = $this->validateCustomerFilePath((string) ($input['file'] ?? ''), false);
            $response = $this->requestCustomerClientApi(
                $service,
                $token,
                'GET',
                '/files/download',
                ['file' => $file]
            );

            $downloadUrl = trim((string) ($response['attributes']['url'] ?? ''));
            $parts = parse_url($downloadUrl);
            if (
                !is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                throw new RuntimeException('Pterodactyl returned an invalid download URL.');
            }

            return ['url' => $downloadUrl];
        }

        if ($action === 'upload_url') {
            $response = $this->requestCustomerClientApi(
                $service,
                $token,
                'GET',
                '/files/upload'
            );
            $uploadUrl = trim((string) ($response['attributes']['url'] ?? ''));
            $parts = parse_url($uploadUrl);
            if (
                !is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                throw new RuntimeException('Pterodactyl returned an invalid upload URL.');
            }

            return ['url' => $uploadUrl, 'directory' => $directory];
        }

        throw new RuntimeException('client_api_invalid_file_action');
    }

    private function isCustomerEditableFilePath(string $path): bool
    {
        $name = strtolower(basename($path));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $editableExtensions = [
            'cfg', 'conf', 'ini', 'json', 'js', 'mjs', 'cjs', 'ts',
            'css', 'scss', 'html', 'htm', 'xml', 'yaml', 'yml',
            'toml', 'txt', 'log', 'md', 'php', 'sh', 'bash', 'sql',
            'vdf', 'kv', 'res', 'properties', 'bat', 'ps1',
        ];

        return in_array($extension, $editableExtensions, true)
            || in_array($name, ['.env', '.htaccess', '.gitignore', 'autoexec'], true);
    }

    private function validateCustomerFilePath(string $path, bool $allowRoot): string
    {
        if (
            strlen($path) > 2048
            || str_contains($path, "\0")
            || str_contains($path, "\\")
            || preg_match('/[\r\n\x7F]/', $path)
        ) {
            throw new RuntimeException('client_api_invalid_file_path');
        }

        $path = trim($path);
        if ($path === '' && $allowRoot) {
            return '/';
        }
        if ($path === '' || preg_match('~(^|/)\.\.(/|$)~', $path)) {
            throw new RuntimeException('client_api_invalid_file_path');
        }

        return '/' . ltrim($path, '/');
    }

    private function normalizeSelectEnvironmentValues(
        array $values,
        array $manifest,
        int $serviceId
    ): array {
        $fields = array_merge(
            $manifest['paymenter']['checkout_fields'] ?? [],
            $manifest['paymenter']['product_fields'] ?? []
        );

        foreach ($fields as $field) {
            if (($field['type'] ?? null) !== 'select') {
                continue;
            }

            $name = $field['name'] ?? null;
            if (!is_string($name) || $name === '' || !array_key_exists($name, $values)) {
                continue;
            }

            $currentValue = $values[$name];

            // A null value should not override the Egg's own default.
            if ($currentValue === null) {
                unset($values[$name]);
                continue;
            }

            if (!is_scalar($currentValue)) {
                throw new RuntimeException(
                    "Invalid value type for Egg environment variable {$name}."
                );
            }

            $currentValue = (string) $currentValue;
            $validValues = [];
            $matchingLabels = [];

            foreach ($field['options'] as $option) {
                $optionValue = (string) $option['value'];
                $optionLabel = (string) $option['label'];

                $validValues[$optionValue] = true;

                if ($optionLabel === $currentValue) {
                    $matchingLabels[] = $optionValue;
                }
            }

            // The exact Egg value always wins over any display-label match.
            if (array_key_exists($currentValue, $validValues)) {
                $values[$name] = $currentValue;
                continue;
            }

            $matchingLabels = array_values(array_unique($matchingLabels));

            // Backward compatibility for values persisted before this fix.
            if (count($matchingLabels) === 1) {
                $values[$name] = $matchingLabels[0];

                Log::warning(
                    'FragCityCS2 converted a legacy select label to its Egg value before provisioning.',
                    [
                        'service_id' => $serviceId,
                        'environment_variable' => $name,
                    ]
                );

                continue;
            }

            throw new RuntimeException(
                "Invalid value for Egg environment variable {$name}; "
                . 'the value does not match any declared Egg option.'
            );
        }

        return $values;
    }

    private function loadManifest(): array
    {
        $manifestPath = __DIR__ . '/resources/cs2-egg-variables.json';

        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            throw new RuntimeException(
                'FragCity CS2 Egg manifest is missing or unreadable: ' . $manifestPath
            );
        }

        $manifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($manifest) || !isset($manifest['paymenter'], $manifest['variables'])) {
            throw new RuntimeException('FragCity CS2 Egg manifest is invalid.');
        }

        $this->validateManifestContract($manifest);

        return $manifest;
    }

    /**
     * Fail closed if the manifest drifts from its own Egg-variable contract.
     * The manifest stores Select options as explicit value/label records.
     * Product settings pass these records to FilamentInput; Checkout adapts
     * them to the keyed map required by Paymenter v1.5.9's Livewire and Blade.
     */
    private function validateManifestContract(array $manifest): void
    {
        $variables = $manifest['variables'];
        $variableMap = [];

        foreach ($variables as $variable) {
            $env = $variable['env_variable'] ?? null;

            if (!is_string($env) || $env === '' || isset($variableMap[$env])) {
                throw new RuntimeException('FragCity CS2 manifest contains an invalid or duplicate Egg variable.');
            }

            $variableMap[$env] = $variable;
        }

        $checkoutFields = $manifest['paymenter']['checkout_fields'] ?? [];
        $productFields = $manifest['paymenter']['product_fields'] ?? [];

        if (!is_array($checkoutFields) || !is_array($productFields)) {
            throw new RuntimeException('FragCity CS2 manifest field lists are invalid.');
        }

        $fieldMap = [];

        foreach ([
            'checkout' => $checkoutFields,
            'product_setting' => $productFields,
        ] as $expectedStatus => $fields) {
            foreach ($fields as $field) {
                $name = $field['name'] ?? null;

                if (!is_string($name) || $name === '' || isset($fieldMap[$name])) {
                    throw new RuntimeException('FragCity CS2 manifest contains a duplicate or invalid Paymenter field.');
                }

                if (!isset($variableMap[$name])) {
                    throw new RuntimeException(
                        "Paymenter field {$name} does not match an Egg environment variable."
                    );
                }

                $variableStatus = $variableMap[$name]['paymenter']['status'] ?? null;
                if ($variableStatus !== $expectedStatus) {
                    throw new RuntimeException(
                        "Paymenter field {$name} is assigned to the wrong manifest scope."
                    );
                }

                $fieldMap[$name] = $field;

                if (($field['type'] ?? null) !== 'select') {
                    continue;
                }

                $options = $field['options'] ?? null;
                if (!is_array($options) || !array_is_list($options)) {
                    throw new RuntimeException(
                        "Select field {$name} must use an explicit list of value/label options."
                    );
                }

                $optionValues = [];

                foreach ($options as $option) {
                    if (
                        !is_array($option)
                        || !array_key_exists('value', $option)
                        || !array_key_exists('label', $option)
                        || !is_scalar($option['value'])
                        || !is_scalar($option['label'])
                    ) {
                        throw new RuntimeException(
                            "Select field {$name} contains an invalid option; expected value and label."
                        );
                    }

                    $optionValues[] = (string) $option['value'];
                }

                if (count($optionValues) !== count(array_unique($optionValues))) {
                    throw new RuntimeException("Select field {$name} contains duplicate Egg values.");
                }

                $default = (string) ($field['default'] ?? '');
                if (!in_array($default, $optionValues, true)) {
                    throw new RuntimeException(
                        "Default value for select field {$name} is not one of its Egg options."
                    );
                }

                $eggVariable = $variableMap[$name];
                $expectedValues = $eggVariable['storefront_allowed_values']
                    ?? $eggVariable['allowed_values']
                    ?? null;

                if (is_array($expectedValues)) {
                    $expectedValues = array_map('strval', $expectedValues);

                    // Nullable Egg variables may intentionally expose an empty
                    // default in addition to their explicit "in:" values.
                    $eggDefault = $eggVariable['default_value'] ?? null;
                    if (
                        $eggDefault !== null
                        && str_contains((string) ($eggVariable['rules'] ?? ''), 'nullable')
                    ) {
                        $eggDefault = (string) $eggDefault;
                        if (!in_array($eggDefault, $expectedValues, true)) {
                            $expectedValues[] = $eggDefault;
                        }
                    }

                    // Option order is a UI concern, not part of the Egg
                    // value contract. Compare exact values as sets so an empty
                    // nullable default can appear first in the UI without being
                    // incorrectly rejected here.
                    $actualValueSet = $optionValues;
                    $expectedValueSet = $expectedValues;
                    sort($actualValueSet, SORT_STRING);
                    sort($expectedValueSet, SORT_STRING);

                    if ($actualValueSet !== $expectedValueSet) {
                        throw new RuntimeException(
                            "Select options for {$name} do not match the declared Egg/storefront values."
                        );
                    }
                } elseif (str_contains((string) ($eggVariable['rules'] ?? ''), 'boolean')) {
                    if ($optionValues !== ['0', '1']) {
                        throw new RuntimeException(
                            "Boolean Egg variable {$name} must expose exactly the values 0 and 1."
                        );
                    }
                }
            }
        }

        foreach ($variableMap as $name => $variable) {
            $status = $variable['paymenter']['status'] ?? null;

            if ($status === 'not_exposed') {
                if (isset($fieldMap[$name])) {
                    throw new RuntimeException(
                        "Hidden Egg variable {$name} must not be exposed as a Paymenter field."
                    );
                }

                continue;
            }

            if (!in_array($status, ['checkout', 'product_setting'], true) || !isset($fieldMap[$name])) {
                throw new RuntimeException(
                    "Egg variable {$name} is missing its declared Paymenter field."
                );
            }
        }

        $summary = $manifest['summary'] ?? [];

        if (
            (int) ($summary['total_egg_variables'] ?? -1) !== count($variableMap)
            || (int) ($summary['checkout_fields'] ?? -1) !== count($checkoutFields)
            || (int) ($summary['product_admin_fields'] ?? -1) !== count($productFields)
        ) {
            throw new RuntimeException('FragCity CS2 manifest summary does not match its actual variable mapping.');
        }
    }
}
