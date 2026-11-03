<?php

namespace Paymenter\Extensions\Servers\FragCityCS2;

use App\Attributes\ExtensionMeta;
use App\Models\Product;
use Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl as PterodactylExtension;
use RuntimeException;

/**
 * FragCity's CS2 checkout layer.
 *
 * Server lifecycle and Pterodactyl API behavior are inherited unchanged.
 * This class adds product-level Egg settings and customer checkout fields.
 */
#[ExtensionMeta(
    name: 'FragCity CS2',
    description: 'Pterodactyl provisioning with settings generated from the FragCity CS2 Egg.',
    version: '1.0.1',
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

    private function loadManifest(): array
    {
        $manifestPath = __DIR__ . '/resources/cs2-egg-variables.json';

        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            throw new RuntimeException('FragCity CS2 Egg manifest is missing or unreadable: ' . $manifestPath);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($manifest) || !isset($manifest['paymenter'])) {
            throw new RuntimeException('FragCity CS2 Egg manifest is invalid.');
        }

        return $manifest;
    }
}
