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
- themes/fragcity: retained FragCity theme assets. This is a theme, not a Paymenter Extension.

## Plug-and-play ZIP packages

Every relevant push to main builds a fresh package set automatically:

[Open the latest extension package builds](https://github.com/larnzs/FragCity-Paymenter/actions/workflows/package-extensions.yml)

Download the latest successful **Package Paymenter Extensions** artifact and extract it. It contains one independently uploadable ZIP per extension plus checksums. In Paymenter, use **Admin → Extensions → Upload Extension** and upload the three ZIPs individually.

Install order:

1. FragCityAPI under **Others**.
2. FragCitySupportAPI under **Others**.
3. FragCityCS2 under **Servers**.

The ZIP structure is validated against Paymenter v1.5.9's native uploader. The combined GitHub Actions artifact is only a download bundle; do not upload that outer ZIP to Paymenter.

The ZarinPal Gateway is intentionally not included in main-branch packages. ZarinPal verification and payment remain on the FragCity website; Paymenter accepts only the signed, verified order from the backend bridge.

Full instructions, configuration and update safeguards: [Plug-and-play installation guide](docs/INSTALL-EXTENSIONS.md).

## Design identity

- Background: #0D0D0D
- Surface: #171717
- Primary: #F97316
- Accent: #FFB347

## Product boundary

FragCity's Raw PHP website owns the public storefront, customer dashboard, administrator dashboard, checkout and payment experience. Customers are not directed to Paymenter or Pterodactyl.

Paymenter is used privately as the commercial/service supervisor and provisioning coordinator. Pterodactyl/Wings remain responsible for actual game-server records and runtime lifecycle.

## Upstream integrity

- Install and update Paymenter from its official upstream repository.
- Keep the pinned Paymenter version compatible with these add-ons.
- Do not copy Paymenter core into this repository.
- Do not replace upstream language files or maintain a private translation layer here.
- Deploy only the specific FragCity-owned theme/extension files that are required.
- Never overwrite Paymenter core, .env, vendor, database contents or runtime storage.

The retained theme is based on Paymenter's default theme. See LICENSES/Paymenter-MIT.txt.
