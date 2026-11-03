# FragCity CS2 — Paymenter Server Extension

Compatibility target: **Paymenter v1.5.9**.

This extension inherits Paymenter's built-in Pterodactyl server implementation. It adds FragCity-specific Product settings and customer Checkout fields from `resources/cs2-egg-variables.json`.

## Egg coverage

The manifest is audited against the source Egg:

- **32 Egg environment variables** are represented.
- **12 variables** are routed to customer Checkout.
- **17 variables** are routed to Product admin settings.
- **3 variables** are intentionally not exposed: fixed `SRCDS_APPID`, secret `AUTHKEY`, and development-only `GDB_DEBUG_PORT`.

The secret `AUTHKEY` default is redacted from the FragCity repository.

## Customer Checkout — Game Mode selection

The customer-facing Game Mode controls are sourced from:

`game/csgo/addons/counterstrikesharp/configs/plugins/GameModeManager/GameModeManager.json`

Only `GameModes.Default` and `GameModes.List` are used.

- `EXEC`: a selector containing **34 unique CFG files** from GameModeManager's game-mode definitions.
- `MAPGROUP`: a selector containing **32 unique map groups** referenced by those definitions.

The selectors are placed next to each other in Checkout. The CFG list deliberately excludes the `Settings` folder, `Warmup` CFGs, `custom_*` files, map-specific CFGs, and other Egg-allowed CFGs that are not listed as GameModes in GameModeManager.

`EXEC` and `MAPGROUP` remain separate Egg environment variables. The Paymenter Checkout currently presents them as two separate selectors; a future FragCity storefront/admin workflow can make one Game Mode preset automatically populate both values and edit the related JSON configuration.

**Startup dependency:** the current Egg startup line does not contain `+exec {{EXEC}}`. Until that is added to the Egg startup command, selecting an `EXEC` value only stores/passes the environment variable and does not execute the CFG. This change intentionally does not modify the Egg startup.

## Field routing

### Customer Checkout (12)

`STEAM_ACC`, `SRCDS_MAP`, `SRCDS_MAXPLAYERS`, `TICKRATE`, `EXEC`, `MAPGROUP`, `COOLDOWN`, `PASSWORD`, `WORKSHOP`, `CONSOLE_LOG_LEVEL`, `ENABLE_GUARD`, `GUARD_BAN_MINUTES`.

### Product admin settings (17)

`ALLOW_TOKENLESS`, `SRCDS_STOP_UPDATE`, `SRCDS_VALIDATE`, `GAME_TYPE`, `GAME_MODE`, `INSTALL_METAMOD`, `INSTALL_CSS`, `INSTALL_SWIFTLY`, `INSTALL_MODSHARP`, `PRERELEASE`, `GUARD_ACTION`, `GUARD_WHITELIST_STEAMIDS`, `GUARD_WHITELIST_IPS`, `ENABLE_FILTER`, `CLEANUP_ENABLED`, `ENABLE_LOG_FILES`, `CUSTOM_PARAMS`.

### Intentionally not exposed (3)

- `SRCDS_APPID`: fixed CS2 AppID 730.
- `AUTHKEY`: secret Workshop download key.
- `GDB_DEBUG_PORT`: development/debug-only port.

## Pricing contract

`ENABLE_GUARD` is a paid add-on toggle. Paymenter captures its selection as a service property; it does **not** calculate the surcharge. FragCity Raw PHP checkout owns that pricing using the manifest's `fragcity_pricing` metadata.

`TICKRATE` is customer-selectable. The FragCity storefront should treat 64 as the base option and 128 as a priced upgrade. Changing a Checkout value alone does not modify an already-provisioned server; a future customer dashboard must update that server's Egg environment through the appropriate control flow.

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

- `CUSTOM_PARAMS` remains an admin-only free-form startup argument field.
- The Game Mode CFG selector is curated from GameModeManager, not from the Egg's full `EXEC` allow-list.
- The Game Mode CFG selector does not enable the Egg's `+exec` startup argument by itself.
- `ENABLE_GUARD` and `TICKRATE` surcharges are not calculated by the Paymenter extension.
- The current extension does not encrypt saved service-property values for masked Checkout fields at rest. Restrict database, backups, and admin access accordingly.

## Source

- Egg: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/pterodactyl/egg-kitsune-lab-c-s2-go--k4ryuu-m-o-d-i-f-i-e-d.json
- GameModeManager: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/game/csgo/addons/counterstrikesharp/configs/plugins/GameModeManager/GameModeManager.json
- Paymenter compatibility: v1.5.9
- Manifest: `resources/cs2-egg-variables.json`
