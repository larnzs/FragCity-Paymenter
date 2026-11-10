# FragCity Paymenter Add-ons

Private repository for the FragCity-owned add-ons that integrate with an unmodified Paymenter installation.

This repository is **not** a Paymenter fork or a full Paymenter installation. Upstream Paymenter core files, official language files, Laravel application files, Composer dependencies and runtime data stay in the original Paymenter project and are not copied or patched here.

## Compatibility target

- Paymenter: v1.5.9
- Laravel: 12.x
- PHP: 8.3 or 8.4 (upstream constraint: ^8.3 || ^8.4)

## Extensions

- extensions/Others/FragCityAPI: private signed website-to-Paymenter bridge for account synchronization, verified CS2 orders and service coordination.
- extensions/Others/FragCitySupportAPI: separate signed identity bridge for FragCity support workflows.
- extensions/Servers/FragCityCS2: CS2 Egg contract, validation and Pterodactyl provisioning layer.
- extensions/Gateways/ZarinPal: native ZarinPal v4 invoice gateway, including Paymenter Credits top-ups.
- themes/fragcity: retained FragCity theme assets. This is a theme, not a Paymenter Extension.

## Versioned Releases and ready-to-deploy packages

Every relevant push to main that changes a FragCity Extension or the Paymenter theme automatically builds a new [GitHub Release](https://github.com/larnzs/FragCity-Paymenter/releases/latest).

Each Release contains:
- Four separate Paymenter Extension ZIPs for native Drag & Drop upload.
- A separate FragCity Paymenter Theme source ZIP for manual theme deployment.
- SHA-256 checksums and package manifests.

Install order for Extensions:

1. FragCityAPI under **Others**.
2. FragCitySupportAPI under **Others**.
3. FragCityCS2 under **Servers**.
4. ZarinPal under **Gateways**.

The theme is not an Extension: extract its fragcity/ directory into Paymenter's themes/ directory and rebuild Vite assets from the Paymenter installation root with `npm run build -- fragcity`.

Previous Releases remain available for rollback. The Actions artifact is retained only as a secondary 30-day copy.

The ZarinPal Gateway is intentionally not included. ZarinPal verification and payment remain on the FragCity website; Paymenter accepts only the signed, verified order from the backend bridge.

Full instructions, configuration and update safeguards: [Plug-and-play installation guide](docs/INSTALL-EXTENSIONS.md).

## Design identity

- Background: #0D0D0D
- Surface: #171717
- Primary: #F97316
- Accent: #FFB347

## Product boundary

FragCity's public website remains the marketing and community entry point. The shop, checkout, invoices, payments and wallet are moving into Paymenter and will be exposed through shop.fragcity.ir. Pterodactyl/Wings remain the game-server runtime and are never the customer-facing storefront.

## Upstream integrity

- Install and update Paymenter from its official upstream repository.
- Keep the pinned Paymenter version compatible with these add-ons.
- Do not copy Paymenter core into this repository.
- Do not replace upstream language files or maintain a private translation layer here.
- Deploy only the specific FragCity-owned theme/extension files that are required.
- Never overwrite Paymenter core, .env, vendor, database contents or runtime storage.

The retained theme is based on Paymenter's default theme. See LICENSES/Paymenter-MIT.txt.
