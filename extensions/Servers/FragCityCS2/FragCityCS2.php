<?php

namespace Paymenter\Extensions\Servers\FragCityCS2;

use App\Attributes\ExtensionMeta;
use App\Models\Product;
use Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl as PterodactylExtension;
use RuntimeException;

/**
 * FragCity's CS2 checkout layer.
 *
 * The actual server lifecycle, Pterodactyl API calls, and Egg environment
 * construction remain inherited from Paymenter's built-in Pterodactyl extension.
 * This class only provides the customer-facing CS2 Egg fields.
 */
#[ExtensionMeta(
    name: 'FragCity CS2',
    description: 'Pterodactyl provisioning with checkout fields generated from the FragCity CS2 Egg.',
    version: '1.0.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCityCS2 extends PterodactylExtension
{
    /**
     * Return checkout fields generated from the checked-in Egg manifest.
     *
     * Paymenter 1.5.9 passes the product, current checkout values, and product
     * settings to this method. Pterodactyl API credentials remain the settings
     * of this server extension and are not exposed to customers.
     */
    public function getCheckoutConfig(Product $product, $values = [], $settings = []): array
    {
        $manifestPath = __DIR__ . '/resources/cs2-egg-variables.json';

        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            throw new RuntimeException('FragCity CS2 Egg manifest is missing or unreadable: ' . $manifestPath);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $fields = $manifest['paymenter']['checkout_fields'] ?? null;

        if (!is_array($fields)) {
            throw new RuntimeException('FragCity CS2 Egg manifest does not contain valid Paymenter checkout fields.');
        }

        return $fields;
    }
}
