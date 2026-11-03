# FragCity CS2 — Paymenter Server Extension

Compatibility target: **Paymenter v1.5.9**.

This extension inherits Paymenter's built-in Pterodactyl server implementation. It adds FragCity-specific Product settings and customer Checkout fields from `resources/cs2-egg-variables.json`.

## Egg coverage

The manifest is audited against the source Egg:

- **32 Egg environment variables** are represented in the manifest.
- **11 variables** are routed to customer Checkout.
- **18 variables** are routed to Product admin settings.
- **3 variables** are intentionally not exposed: fixed `SRCDS_APPID`, secret `AUTHKEY`, and development-only `GDB_DEBUG_PORT`.

The manifest preserves the Egg variable definitions and allowed values. The only deliberate source-data exception is that the secret `AUTHKEY` default is redacted from the FragCity repository.

## Field routing

### Customer Checkout (11)

`STEAM_ACC`, `SRCDS_MAP`, `SRCDS_MAXPLAYERS`, `TICKRATE`, `MAPGROUP`, `COOLDOWN`, `PASSWORD`, `WORKSHOP`, `CONSOLE_LOG_LEVEL`, `ENABLE_GUARD`, `GUARD_BAN_MINUTES`.

### Product admin settings (18)

`ALLOW_TOKENLESS`, `SRCDS_STOP_UPDATE`, `SRCDS_VALIDATE`, `GAME_TYPE`, `GAME_MODE`, `INSTALL_METAMOD`, `INSTALL_CSS`, `INSTALL_SWIFTLY`, `INSTALL_MODSHARP`, `PRERELEASE`, `GUARD_ACTION`, `GUARD_WHITELIST_STEAMIDS`, `GUARD_WHITELIST_IPS`, `ENABLE_FILTER`, `CLEANUP_ENABLED`, `ENABLE_LOG_FILES`, `CUSTOM_PARAMS`, `EXEC`.

`EXEC` is an admin-only selector populated from the Egg's exact 233 allowed CFG values. Its default is `comp.cfg`. It is currently only passed as an Egg environment value: the present Egg startup command does not contain `+exec {{EXEC}}`, so selecting a CFG will not execute it until the Egg startup is intentionally updated.

### Intentionally not exposed (3)

- `SRCDS_APPID`: fixed to CS2 AppID 730.
- `AUTHKEY`: secret Workshop download key; its source default is redacted in this repository.
- `GDB_DEBUG_PORT`: development/debug-only port.

## Pricing contract

`ENABLE_GUARD` is a paid add-on toggle. Paymenter captures its selection as a service property; it does **not** calculate the surcharge. The FragCity Raw PHP checkout owns that pricing using the manifest's `fragcity_pricing` metadata.

`TICKRATE` is customer-selectable. The FragCity storefront should treat 64 as the base option and 128 as a priced upgrade. Changing a Checkout value alone does not modify an already-provisioned server; a future customer dashboard must update that server's Egg environment through the relevant control flow.

## Installation

Copy this folder into the Paymenter installation:

```text
extensions/Servers/FragCityCS2/
```

Then rebuild Composer autoload and clear Laravel caches:

```bash
cd /var/www/paymenter
composer dump-autoload
php artisan optimize:clear
```

Open **Paymenter Admin → Extensions → Servers**, enable **FragCity CS2**, and configure its Pterodactyl URL and Application API key. Paymenter stores settings separately for each server extension, so use the same host and API key as the built-in Pterodactyl connection.

For a test Product, select **FragCity CS2** as its server extension. Inherited Pterodactyl settings (node, location, nest, egg, allocations, resource limits, etc.) remain available alongside the FragCity CS2 Product settings.

**Do not switch a production Product until a separate test Product has successfully provisioned a server.**

No rows need to be manually created in Paymenter's native Config Options screen.

## Important behavior

- `CUSTOM_PARAMS` is admin-only because it inserts free-form arguments into the startup command.
- `EXEC` is restricted to the Egg's declared allow-list in the Product selector, but does not run until the Egg startup references it.
- `ENABLE_GUARD` and `TICKRATE` pricing are not calculated by the Paymenter extension.
- The current extension does not encrypt saved service-property values for masked Checkout fields at rest. Restrict database, backups, and admin access accordingly.

## Source

- Egg: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/pterodactyl/egg-kitsune-lab-c-s2-go--k4ryuu-m-o-d-i-f-i-e-d.json
- Paymenter compatibility: v1.5.9
- Manifest: `resources/cs2-egg-variables.json`
