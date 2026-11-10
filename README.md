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
- themes/fragcity: FragCity's Laravel Blade customer-facing theme. This is a Theme, not a Paymenter Extension.

## Versioned Releases and ready-to-deploy packages

Every relevant push to main that changes a FragCity Extension or the Paymenter theme automatically builds a new [GitHub Release](https://github.com/larnzs/FragCity-Paymenter/releases/latest).

Each Release contains:
- Four separate Paymenter Extension ZIPs for native Extension Uploader installation.
- One deploy-ready FragCity Theme overlay ZIP with both Laravel Blade source and precompiled Vite assets.
- SHA-256 checksums and package manifests.

Install order for Extensions:

1. FragCityAPI under **Others**.
2. FragCitySupportAPI under **Others**.
3. FragCityCS2 under **Servers**.
4. ZarinPal under **Gateways**.

### Installing the Theme on shared hosting

The theme ZIP is **not** uploaded through Admin → Extensions. It contains these two paths:

- `themes/fragcity/`
- `public/fragcity/` (including `manifest.json` and compiled JS/CSS)

Upload the ZIP through cPanel File Manager to the Paymenter application root and extract it there, allowing the `themes` and `public` directories to merge. The package is built in GitHub Actions against upstream Paymenter v1.5.9, so the hosting account does not need Node.js, npm, Composer or SSH.

Full instructions and rollback notes: [Theme installation guide](docs/INSTALL-THEME.md).

Previous Releases remain available for rollback. The Actions artifact is retained only as a secondary 30-day copy.

ZarinPal is packaged as a native Paymenter Gateway. It verifies payments server-to-server and records successful payments through Paymenter's invoice transaction flow. Paymenter's built-in Credits feature is used as the FragCity wallet; wallet top-ups are ordinary native Paymenter invoices.

Full extension instructions, configuration and update safeguards: [Plug-and-play installation guide](docs/INSTALL-EXTENSIONS.md).

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
