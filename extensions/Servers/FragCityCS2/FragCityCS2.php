<?php

namespace Paymenter\Extensions\Servers\FragCityCS2;

use App\Attributes\ExtensionMeta;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
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
    version: '1.2.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCityCS2 extends PterodactylExtension
{
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

        $this->ensurePterodactylClientUser($service, true);

        return parent::createServer($service, $settings, $properties);
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

        $externalId = (string) $paymenterUserId;
        $users = $this->request('/api/application/users', 'get', [
            'filter' => ['email' => $email],
        ]);
        $attributes = $users['data'][0]['attributes'] ?? null;

        // Email can change after provisioning; external_id remains the stable
        // Paymenter-user-to-Pterodactyl-user link.
        if (!is_array($attributes)) {
            $byExternalId = $this->request('/api/application/users', 'get', [
                'filter' => ['external_id' => $externalId],
            ]);
            $attributes = $byExternalId['data'][0]['attributes'] ?? null;
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

            return $attributes;
        }

        $pterodactylUserId = (int) ($attributes['id'] ?? 0);
        $existingExternalId = trim((string) ($attributes['external_id'] ?? ''));

        if ($pterodactylUserId < 1) {
            throw new RuntimeException('Pterodactyl returned a client user without a valid ID.');
        }

        if ($existingExternalId !== '' && $existingExternalId !== $externalId) {
            throw new RuntimeException(
                'The Pterodactyl email is already linked to a different external account.'
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

        return $attributes;
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

        try {
            $serverResponse = $this->request(
                '/api/application/servers/external/' . (int) $service->id . '?include=allocations',
                'get'
            );
        } catch (Exception $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'not found') || str_contains($message, 'no query results')) {
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

        $limits = is_array($attributes['limits'] ?? null) ? $attributes['limits'] : [];
        $featureLimits = is_array($attributes['feature_limits'] ?? null)
            ? $attributes['feature_limits']
            : [];

        $primaryAllocationId = (int) ($attributes['allocation'] ?? 0);
        $allocationRows = $attributes['relationships']['allocations']['data'] ?? [];
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
                'limits' => [
                    'memory' => (int) ($limits['memory'] ?? 0),
                    'swap' => (int) ($limits['swap'] ?? 0),
                    'disk' => (int) ($limits['disk'] ?? 0),
                    'cpu' => (int) ($limits['cpu'] ?? 0),
                    'io' => (int) ($limits['io'] ?? 0),
                    'threads' => $limits['threads'] ?? null,
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
