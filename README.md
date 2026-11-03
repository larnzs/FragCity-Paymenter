# FragCity Paymenter Add-ons

Private repository for the small set of FragCity-owned add-ons that integrate with an unmodified Paymenter installation.

This repository is **not** a Paymenter fork or a full Paymenter installation. Upstream Paymenter core files, official language files, Laravel application files, Composer dependencies and runtime data stay in the original Paymenter project and are not copied or patched here.

## Compatibility target

- Paymenter: v1.5.9
- Laravel: 12.x
- PHP: 8.3 or 8.4 (upstream constraint: `^8.3 || ^8.4`)

## Kept components

- `extensions/Servers/FragCityCS2`: FragCity CS2 server extension. It inherits Paymenter's Pterodactyl server implementation and supplies Egg-derived product and checkout configuration.
- `themes/fragcity`: retained FragCity theme assets. Keeping this theme does not mean the Paymenter customer UI is part of the FragCity product.
- `docs`: architecture, development and integration contracts.
- `deployment`: deployment guidance for FragCity-owned add-ons only.
- `LICENSES`: required upstream license notices for files derived from Paymenter's default theme.

A separate `FragCityAPI` extension will be added when the authenticated server-to-server Bridge is implemented. It will be independent of the CS2 provisioning extension and will not contain locale registration or translation code.

## Design identity

- Background: `#0D0D0D`
- Surface: `#171717`
- Primary: `#F97316`
- Accent: `#FFB347`

## Product boundary

FragCity's Raw PHP website owns the public storefront, customer dashboard, administrator dashboard, checkout and payment experience. Customers are not directed to Paymenter or Pterodactyl.

Paymenter is used privately as the commercial/service supervisor and provisioning coordinator. Pterodactyl/Wings remain responsible for actual game-server records and runtime lifecycle.

## Upstream integrity

- Install and update Paymenter from its official upstream repository.
- Keep the pinned Paymenter version compatible with these add-ons.
- Do not copy Paymenter core into this repository.
- Do not replace upstream language files or maintain a private translation layer here.
- Deploy only the specific FragCity theme/extension files that are required.
- Never overwrite Paymenter core, `.env`, `vendor`, database contents or runtime storage.

The retained theme is based on Paymenter's default theme. See `LICENSES/Paymenter-MIT.txt`.
