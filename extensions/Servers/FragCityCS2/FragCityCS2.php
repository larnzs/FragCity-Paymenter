<?php

namespace Paymenter\Extensions\Servers\FragCityCS2;

use App\Attributes\ExtensionMeta;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl as PterodactylExtension;
use RuntimeException;

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
    version: '1.0.2',
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
        return $this->loadManifest()['paymenter']['checkout_fields'] ?? [];
    }

    /**
     * Normalize and validate all FragCity select values before delegating
     * provisioning to Paymenter's unchanged Pterodactyl implementation.
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

        return parent::createServer($service, $settings, $properties);
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
     * Select options must use explicit {value, label} records so PHP cannot
     * mistake numeric Egg values ("0", "1") for sequential option indexes.
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
