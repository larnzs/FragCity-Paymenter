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

Extension version: **1.8.3**.

The extension exposes two narrow methods for the signed FragCity API bridge:

- `getCustomerPanelSummary`: finds the Pterodactyl server by the Paymenter service ID, verifies both server `external_id` and owner user ID, and returns only the server metadata needed by the customer's FragCity "My Servers" page. It also aligns legacy client username/email/name fields and attaches the stable Paymenter user `external_id`.
- `setCustomerPanelPassword`: validates the customer-selected password, verifies that the Pterodactyl server belongs to the Paymenter service owner, and updates that user's password through the Application API.

The panel password is independent from FragCity and Paymenter credentials. Pterodactyl hashes it; the extension never saves it in Paymenter properties, logs, or API responses. No Paymenter Core or Pterodactyl Core file is modified.

## Egg coverage

The manifest is audited against the source Egg:

- **32 Egg environment variables** are represented.
- **6 variables** are routed to customer Checkout.
- **2 variables** are routed to native Product Config Options.
- **20 variables** are routed to Product admin settings.
- **3 variables** are intentionally not exposed: fixed `SRCDS_APPID`, secret `AUTHKEY`, and development-only `GDB_DEBUG_PORT`.

The secret `AUTHKEY` default is redacted from the FragCity repository.

## Customer Checkout (6)

- `SRCDS_MAP`: initial map.
- `EXEC`: GameModeManager Game Mode CFG selector, sourced only from `GameModes.Default` and `GameModes.List`.
- `MAPGROUP`: GameModeManager Map Group selector.
- `COOLDOWN`: cooldown policy.
- `ENABLE_GUARD`: optional paid Bot Guard add-on toggle.
- `GUARD_BAN_MINUTES`: Bot Guard block duration.

The `EXEC` selector contains only the **34 unique CFG files** referenced by GameModeManager's game-mode definitions. It excludes `settings/`, `warmup/`, `custom_*`, map-specific and unrelated Egg CFG files. The `MAPGROUP` selector contains the **32 unique map groups** referenced by those definitions.

**Startup dependency:** the current Egg startup command does not contain `+exec {{EXEC}}`. Selecting a CFG stores/passes the environment value but does not execute the CFG until the Egg startup is intentionally updated.

## Product admin settings (20)

`ALLOW_TOKENLESS`, `SRCDS_STOP_UPDATE`, `SRCDS_VALIDATE`, `GAME_TYPE`, `GAME_MODE`, `INSTALL_METAMOD`, `INSTALL_CSS`, `INSTALL_SWIFTLY`, `INSTALL_MODSHARP`, `PRERELEASE`, `GUARD_ACTION`, `GUARD_WHITELIST_STEAMIDS`, `GUARD_WHITELIST_IPS`, `ENABLE_FILTER`, `CLEANUP_ENABLED`, `ENABLE_LOG_FILES`, `CUSTOM_PARAMS`, `PASSWORD`, `WORKSHOP`, `CONSOLE_LOG_LEVEL`.

The remaining shared Product-level fields include:

- `PASSWORD`: masked password input, optional.
- `WORKSHOP`: optional numeric Collection ID.
- `CONSOLE_LOG_LEVEL`: DEBUG/INFO/WARNING/ERROR or the logging.json default.

**Product-level scope:** `PASSWORD`, `WORKSHOP` and the other listed settings are shared by services on this Product. GSLT is deliberately not in this group.

## Intentionally not exposed (3)

- `SRCDS_APPID`: fixed to CS2 AppID 730.
- `AUTHKEY`: secret Workshop download key.
- `GDB_DEBUG_PORT`: development/debug-only port.

## Native Product Config Options

Attach these native Paymenter Config Options to the CS2 Product:

| Config Option | Parent Environment Variable | Type | Child Environment Variables |
| --- | --- | --- | --- |
| RAM | `memory_mb` | Select | Numeric MB values from `1` to `1048576` |
| SSD Storage | `disk_mb` | Select | Numeric MB values from `1` to `1048576` |
| CPU | `cpu_percent` | Select | Numeric percentage values from `1` to `6400` |
| Player Slots | `SRCDS_MAXPLAYERS` | Select | Every integer from `5` through `64` |
| Tickrate | `TICKRATE` | Select | `64`, `128` |
| Databases | `databases` | Select | `0`, `1`, `2`, `3`, `5`, `10` |
| Additional Allocations | `additional_allocations` | Select | `0`, `1`, `2`, `3`, `5` |
| Backups | `backups` | Select | `0`, `1`, `2`, `3`, `5`, `10` |

The signed FragCity API resolves and stores all eight selections as native `ServiceConfig` rows. Paymenter's `ExtensionHelper::getServiceProperties()` merges their selected child Environment Variables into the provisioning property map. The inherited Pterodactyl provisioner receives RAM/SSD/CPU as resource limits, Player Slots/Tickrate as Egg environment variables, and the three feature-limit selections as Pterodactyl `feature_limits` values. The same mapping is applied by `upgradeServer()` before Paymenter's inherited Pterodactyl updater changes build limits and Egg environment values. Because Paymenter's updater preserves existing values for already-populated Egg variables, FragCityCS2 then sends a narrow second Startup API update for manifest-declared native Egg Config Options (currently `SRCDS_MAXPLAYERS` and `TICKRATE`). It preserves unrelated environment values, the startup command, Egg, Docker image and skip-scripts setting, then reads the server back to verify the selected values were saved. This updates the panel's saved startup configuration; a running CS2 process still needs a restart before the new `-maxplayers` or `-tickrate` arguments take effect.

Use the exact numeric value as each child Environment Variable. The values shown in the Checkout slider come from the Product's live Config Option children; the example values above are not a hard-coded allow-list. FragCity's quote/order API validates that the selected child belongs to the correct Product option and has a valid Plan/currency price. Provisioning enforces the same safety ceilings: RAM and disk up to 1,048,576 MB, CPU up to 6,400. For Player Slots, the 5-slot choice is the included base capacity and larger values represent the additional-slot surcharge. Both Tickrate children (`64` and `128`) are free. Databases, Additional Allocations and Backups are also free at this stage; create zero-price rows for all their values under every supported Plan/currency. The Egg Startup command passes the selected Tickrate through `-tickrate {{TICKRATE}}`.

**Enable `Upgradable` for all eight Config Options.** Paymenter 1.5.9 removes service Config rows that are not included in the native Service Upgrade selection. With `Upgradable` enabled, the current selections are carried into the upgrade record; FragCityCS2 then resolves all eight rows again and forwards the updated limits/environment to Pterodactyl. This also lets the customer change an option through Paymenter's native upgrade flow, where Paymenter calculates the price from the selected child and the configured recurring price table. Keep Tickrate, Databases, Additional Allocations and Backups at zero price until FragCity checkout begins charging for them; keep every child priced for each supported Plan/currency. Do not perform a product/plan upgrade until the Product has all eight options attached, all eight are marked upgradable, and every selected child has a valid price row.

## Customer text Config Options

Attach two additional native Paymenter Config Options to the Product (both must use the **Text** type):

| Config Option | Parent Environment Variable | Type | Rules |
| --- | --- | --- | --- |
| Server Name / نام سرور | `servername` | Text | `required|string|max:64|regex:/^[A-Za-z0-9_. -]+$/` |
| Game Server Login Token (GSLT) | `STEAM_ACC` | Text | `nullable|string|max:32` |

`servername` is a Pterodactyl server-create field, not an Egg variable. Paymenter's native Pterodactyl adapter reads it from the Service properties and sends it as the server's name. `STEAM_ACC` is per-Service and is passed to the Egg startup variable; it must never be stored as a Product-wide setting.

The theme displays GSLT as a masked password input and redacts it from the checkout summary and cart. Paymenter v1.5.9 normally synchronizes all `configOptions` into the URL; apply `deployment/patches/paymenter-1.5.9-sensitive-config-options.patch` before enabling GSLT so its Livewire value uses `sensitiveConfigOptions`, outside URL synchronization. FragCityAPI also checks that both Product options exist with the exact parent environment-variable keys and Text type.

## Pricing contract

`ENABLE_GUARD` is a paid add-on toggle. Paymenter captures the selection as a service property; the extension does **not** calculate the surcharge. FragCity Raw PHP checkout owns that pricing using the manifest's `fragcity_pricing` metadata.

`TICKRATE` is selected only through its native Paymenter Config Option; both `64` and `128` are currently free. Changing a Config Option selection alone does not modify an already-provisioned server.

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

Configure the inherited Node, Location, Nest, Egg, allocation, Swap, IO and other non-resource deployment settings as usual. RAM, Storage, CPU, Databases, Additional Allocations and Backups are intentionally removed from the inherited Product-level Pterodactyl fields because they are customer/service selections, not static Node defaults.

Create and attach three native Paymenter Config Options to the CS2 Product:

| Resource | Recommended parent `env_variable` | Allowed child `env_variable` values |
| --- | --- | --- |
| RAM | `memory_mb` | Numeric child values in MB, from `1` through `1048576` |
| Storage / SSD | `disk_mb` | Numeric child values in MB, from `1` through `1048576` |
| CPU | `cpu_percent` | Numeric child values in percent, from `1` through `6400` |

Use a selectable Config Option type (not free-form `text` or `number`), attach each parent option to the Product, and set a price for every child under each supported billing Plan and currency. Child `env_variable` values must be numeric and exact; labels are display-only. The checkout slider is populated from the Product's live Config Option children, so supported tiers are managed in Paymenter rather than duplicated as a hard-coded list in FragCityCS2. Provisioning applies the same upper bounds enforced by the quote and paid-order API: RAM/disk up to 1,048,576 MB and CPU up to 6,400. The FragCity API creates the native ServiceConfig rows from these values. At provisioning and upgrade time, this extension translates those rows into the `memory`, `disk` and `cpu` keys expected by Paymenter's inherited Pterodactyl implementation. If a required option is missing, duplicated or has an unsupported value, provisioning stops rather than creating an incorrectly limited server.

## Egg value safety

All select options are stored in the manifest as explicit `value` / `label` records. The `value` is the exact Egg environment value (for example, `"0"` or `"1"`); the Persian/English `label` is display-only and must never be sent to Pterodactyl.

The extension adapts this canonical structure for each Paymenter v1.5.9 consumer:
- Product settings pass explicit records to `FilamentInput`, which converts each record to its intended value/label option.
- Customer Checkout converts the records to the keyed `egg_value => display_label` map expected by Paymenter's Livewire validator and default Blade template.

This separation is required because Paymenter's Product settings and Checkout use different option consumers. It also prevents PHP's numeric-string array-key behavior from turning labels into stored values.

Before provisioning, FragCityCS2 validates every select value against the manifest. Legacy values previously saved as labels are translated back to their exact Egg values. Unknown or invalid values stop provisioning before the inherited Pterodactyl API request. No Paymenter Core files are modified.

The manifest is also validated at runtime: variable coverage, scope assignment, select value uniqueness, defaults, allowed Egg/storefront values, and boolean values (`0` / `1`) must all match the declared contract. The Checkout adapter output is checked against Paymenter v1.5.9's associative option-map contract.

## Important behavior

- `CUSTOM_PARAMS` remains an admin-only free-form startup argument field.
- `EXEC` and `MAPGROUP` are separate customer Checkout selectors and separate Egg environment variables.
- `STEAM_ACC`, `PASSWORD`, `WORKSHOP`, and `CONSOLE_LOG_LEVEL` are currently Product-level settings shared by services on the Product.
- Optional Product values use empty defaults; they are not mandatory for the admin to enter.
- The current extension does not encrypt saved service-property values for masked fields at rest. Restrict database, backups, and admin access accordingly.
- `ENABLE_GUARD` pricing is owned by FragCity checkout. Tickrate has no surcharge while both native Config Option child values remain free.
- Databases, Additional Allocations and Backups are included/free for now; every selected child needs a zero-price and zero-setup-fee row under each supported Plan and Rial currency.

## Source

- Egg: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/pterodactyl/egg-kitsune-lab-c-s2-go--k4ryuu-m-o-d-i-f-i-e-d.json
- GameModeManager: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/game/csgo/addons/counterstrikesharp/configs/plugins/GameModeManager/GameModeManager.json
- Paymenter compatibility: v1.5.9
- Manifest: `resources/cs2-egg-variables.json`


## Per-service text Config Options

Attach these two native Paymenter Config Options to the CS2 Product as **Text** fields:

| Customer label | Paymenter parent Environment Variable | Target | Rules |
| --- | --- | --- | --- |
| نام سرور / Server Name | `servername` | Pterodactyl server resource name (not an Egg variable) | Optional; max 64; letters, numbers, spaces, `_`, `-`, `.` |
| Game Server Login Token (GSLT) | `STEAM_ACC` | Egg environment variable | Optional; `max:32|nullable` |

Paymenter stores Text Config Options as per-service properties. The official Pterodactyl adapter consumes `servername` as the server's API `name` field; it is not sent in the Egg environment. `STEAM_ACC` is passed to the Egg and consumed by the startup argument `+sv_setsteamaccount {{STEAM_ACC}}`. The token is per service, is never stored as a Product-wide setting, and is masked in the customer's cart summary.

The GSLT text value is validated by the signed FragCity order quote and paid-order bridge, and again by FragCityCS2 at provisioning. It is not upgradable through Paymenter's text Config Option flow; update it through the service property/order workflow rather than assuming it is a Select upgrade.
