# FragCity CS2 — Paymenter Server Extension

Compatibility target: **Paymenter v1.5.9**.

This extension inherits Paymenter's built-in Pterodactyl server implementation. FragCity-specific Product settings and customer Checkout fields are generated from `resources/cs2-egg-variables.json`.

## FragCity client identity provisioning

Before delegating server creation to Paymenter's official Pterodactyl implementation, this extension ensures the corresponding Pterodactyl client user exists and has FragCity's chosen identity:

- `fragcity_username` is read from the Paymenter User Property written by the signed FragCity API bridge.
- Paymenter's native `first_name`, `last_name` and `email` are sent to Pterodactyl's native `first_name`, `last_name` and `email` fields.
- Pterodactyl's `external_id` is set to the Paymenter user ID, providing a stable cross-system identity.
- If a Pterodactyl user already exists for that email, the extension updates the username and client identity only when its `external_id` is empty or matches the Paymenter user ID. A conflicting external identity stops provisioning rather than silently claiming another account.
- The extension then delegates server creation to the unchanged Paymenter Pterodactyl implementation, which finds the client by email and assigns the server.

If an existing client is found by email with an empty `external_id`, the extension safely attaches it to the current Paymenter user before provisioning. A non-empty conflicting `external_id` is never overwritten. The same verified identity resolver is used by the customer server-management methods.

## Customer server management

Extension version: **1.2.0**.

The extension exposes two narrow methods for the signed FragCity API bridge:

- `getCustomerPanelSummary`: finds the Pterodactyl server by the Paymenter service ID, verifies both server `external_id` and owner user ID, and returns only the server metadata needed by the customer's FragCity "My Servers" page. It also aligns legacy client username/email/name fields and attaches the stable Paymenter user `external_id`.
- `setCustomerPanelPassword`: validates the customer-selected password, verifies that the Pterodactyl server belongs to the Paymenter service owner, and updates that user's password through the Application API.

The panel password is independent from FragCity and Paymenter credentials. Pterodactyl hashes it; the extension never saves it in Paymenter properties, logs, or API responses. No Paymenter Core or Pterodactyl Core file is modified.

## Egg coverage

The manifest is audited against the source Egg:

- **32 Egg environment variables** are represented.
- **6 variables** are routed to extension-defined customer Checkout fields.
- **2 variables** (`SRCDS_MAXPLAYERS` and `TICKRATE`) are native Paymenter Product Config Options.
- **21 variables** are routed to Product admin settings.
- **3 variables** are intentionally not exposed: fixed `SRCDS_APPID`, secret `AUTHKEY`, and development-only `GDB_DEBUG_PORT`.

The secret `AUTHKEY` default is redacted from the FragCity repository.

## Extension-defined customer Checkout fields (6)

- `SRCDS_MAP`: initial map.
- `EXEC`: GameModeManager Game Mode CFG selector, sourced only from `GameModes.Default` and `GameModes.List`.
- `MAPGROUP`: GameModeManager Map Group selector.
- `COOLDOWN`: cooldown policy.
- `ENABLE_GUARD`: optional paid Bot Guard add-on toggle.
- `GUARD_BAN_MINUTES`: Bot Guard block duration.

The `EXEC` selector contains only the **34 unique CFG files** referenced by GameModeManager's game-mode definitions. It excludes `settings/`, `warmup/`, `custom_*`, map-specific and unrelated Egg CFG files. The `MAPGROUP` selector contains the **32 unique map groups** referenced by those definitions.

**Startup dependency:** the current Egg startup command does not contain `+exec {{EXEC}}`. Selecting a CFG stores/passes the environment value but does not execute the CFG until the Egg startup is intentionally updated.

## Native Product Config Options (2)

These two options are deliberately **not** defined in `getCheckoutConfig()` or generated from the Egg manifest. Create and attach them through Paymenter Admin → Configuration → Config Options, then select them on the CS2 Product:

| Config Option | Parent Environment Variable | Type | Child Environment Variables |
| --- | --- | --- | --- |
| Player Slots | `SRCDS_MAXPLAYERS` | Slider | One child per supported slot count; use the exact numeric slot count as the Child Environment Variable (for example `5`, `10`, `16`, up to `64`) |
| Tickrate | `TICKRATE` | Select | `64`, `128` |

Paymenter owns the option IDs, child values, checkout controls, per-plan prices, validation and Service Config records. The selected child Environment Variable is passed to Pterodactyl as the Egg value through Paymenter's own `ExtensionHelper::getServiceProperties()` pipeline. No FragCity API mapping or UI-label conversion is required.

Set the Player Slots default child to the capacity included in the base plan. Give the 64 Tick child the base price and configure the 128 Tick child as a paid upgrade only when its surcharge is reflected in the Paymenter Plan's pricing. The Product Config Option is the source of truth for recurring pricing.

## Product admin settings (21)

`ALLOW_TOKENLESS`, `SRCDS_STOP_UPDATE`, `SRCDS_VALIDATE`, `GAME_TYPE`, `GAME_MODE`, `INSTALL_METAMOD`, `INSTALL_CSS`, `INSTALL_SWIFTLY`, `INSTALL_MODSHARP`, `PRERELEASE`, `GUARD_ACTION`, `GUARD_WHITELIST_STEAMIDS`, `GUARD_WHITELIST_IPS`, `ENABLE_FILTER`, `CLEANUP_ENABLED`, `ENABLE_LOG_FILES`, `CUSTOM_PARAMS`, `STEAM_ACC`, `PASSWORD`, `WORKSHOP`, `CONSOLE_LOG_LEVEL`.

The four fields moved out of customer Checkout for the current test phase:

- `STEAM_ACC`: masked password input, optional.
- `PASSWORD`: masked password input, optional.
- `WORKSHOP`: optional numeric Collection ID.
- `CONSOLE_LOG_LEVEL`: DEBUG/INFO/WARNING/ERROR or the logging.json default.

**Product-level scope:** these four values are configured in the Product settings, so they are shared by every service provisioned from that Product. They are not per-customer or per-service inputs in this phase. Later, per-server customer controls can be implemented in FragCity's own server dashboard.

## Intentionally not exposed (3)

- `SRCDS_APPID`: fixed to CS2 AppID 730.
- `AUTHKEY`: secret Workshop download key.
- `GDB_DEBUG_PORT`: development/debug-only port.

## Pricing contract

`ENABLE_GUARD` remains an extension-defined Checkout field. Paymenter stores its selected value as a service property; the extension does **not** calculate its surcharge. Any paid add-on price must be represented in the Paymenter product/plan pricing before activation.

`TICKRATE` is now a native Product Config Option. Paymenter calculates its price per selected Plan and saves the chosen Config Value on the Service. Do not add a parallel Tickrate surcharge in the FragCity PHP quote or in the API. Changing a Config Option on an existing service requires Paymenter's upgrade flow; it is not a retroactive edit to the already-provisioned Pterodactyl server.

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

For a test Product, select **FragCity CS2** as its server extension. Inherited Pterodactyl settings (node, location, nest, egg, allocations, resource limits, etc.) remain available alongside FragCity's CS2 settings.

Create the two native Product Config Options from the table above in Paymenter's Configuration → Config Options, then attach both options to the CS2 Product. Do not recreate them as `checkout_fields` in the Egg manifest.

## Egg value safety

Extension-defined Checkout select options are stored in the manifest as explicit `value` / `label` records. The `value` is the exact Egg environment value (for example, `"0"` or `"1"`); the Persian/English `label` is display-only and must never be sent to Pterodactyl. Native Config Option children are managed separately in Paymenter; their Child Environment Variable is the exact Egg value.

The extension adapts this canonical manifest structure for Paymenter v1.5.9:
- Product settings pass explicit records to `FilamentInput`.
- Extension-defined Checkout converts manifest select records to the keyed `egg_value => display_label` map expected by Paymenter's Livewire validator and default Blade template.
- Native Config Options do not pass through this adapter; Paymenter renders, validates, prices and persists them itself.

This separation is required because Paymenter's Product settings and Checkout use different option consumers. It also prevents PHP's numeric-string array-key behavior from turning labels into stored values.

Before provisioning, FragCityCS2 validates every select value against the manifest. Legacy values previously saved as labels are translated back to their exact Egg values. Unknown or invalid values stop provisioning before the inherited Pterodactyl API request. No Paymenter Core files are modified.

The manifest is also validated at runtime: variable coverage, scope assignment, select value uniqueness, defaults, allowed Egg/storefront values, and boolean values (`0` / `1`) must all match the declared contract. The Checkout adapter output is checked against Paymenter v1.5.9's associative option-map contract.

## Important behavior

- `CUSTOM_PARAMS` remains an admin-only free-form startup argument field.
- `EXEC` and `MAPGROUP` are separate customer Checkout selectors and separate Egg environment variables.
- `STEAM_ACC`, `PASSWORD`, `WORKSHOP`, and `CONSOLE_LOG_LEVEL` are currently Product-level settings shared by services on the Product.
- Optional Product values use empty defaults; they are not mandatory for the admin to enter.
- The current extension does not encrypt saved service-property values for masked fields at rest. Restrict database, backups, and admin access accordingly.
- `ENABLE_GUARD` and `TICKRATE` surcharges are not calculated by the Paymenter extension.

## Source

- Egg: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/pterodactyl/egg-kitsune-lab-c-s2-go--k4ryuu-m-o-d-i-f-i-e-d.json
- GameModeManager: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/game/csgo/addons/counterstrikesharp/configs/plugins/GameModeManager/GameModeManager.json
- Paymenter compatibility: v1.5.9
- Manifest: `resources/cs2-egg-variables.json`
