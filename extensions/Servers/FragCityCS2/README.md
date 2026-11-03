# FragCity CS2 — Paymenter Server Extension

Compatibility target: **Paymenter v1.5.9** (the version pinned by FragCity).

This extension inherits Paymenter's built-in Pterodactyl server implementation and reads its CS2 field definitions from `resources/cs2-egg-variables.json`.

## What is generated

- **11 customer checkout fields**: GSLT, map, maximum players, tickrate, map group, cooldown policy, password, Workshop Collection, console log level, Bot Guard toggle and Bot Guard ban duration.
- **17 admin product settings**: tokenless policy, update/validation controls, Game Type and Game Mode, plugin frameworks, prerelease policy, Bot Guard action and allow-lists, console filtering, automatic cleanup, log persistence and custom startup parameters.
- A manifest containing all **32 Egg variables**, their defaults, Egg validation rules, source access flags, allowed values, and explicit Paymenter routing.
- **4 variables are intentionally not exposed**: fixed CS2 AppID, AUTHKEY, development-only GDB port and the unused EXEC variable.

`ENABLE_GUARD` is a paid add-on toggle. Its selection is captured as a service property; the extra charge must be calculated by the FragCity Raw PHP checkout using the manifest's `fragcity_pricing` contract. Paymenter's checkout extension hook does not add a surcharge by itself.

`TICKRATE` is customer-selectable. The FragCity storefront should treat 64 as the base option and 128 as a priced upgrade using the same pricing contract. A future customer dashboard must also update the existing service's Egg environment when changing tickrate; changing a checkout value alone does not modify an already-provisioned server.

No rows need to be manually created in Paymenter's native Config Options screen.
## Install

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

Open Paymenter Admin → Extensions → Servers and enable **FragCity CS2**.

Configure its Pterodactyl URL and Application API key. Paymenter stores settings separately for each server extension, so enter the same host and API key currently used by the built-in Pterodactyl extension.

For a test product, select **FragCity CS2** as its server extension. Its inherited Pterodactyl product settings (node, location, nest, egg, allocations, resource limits, etc.) remain available. The CS2 admin settings appear alongside them.

**Do not switch a production product until a separate test product has successfully provisioned a server.** The extension implementation is inherited, but the FragCityCS2 extension record has its own credentials/settings record.

## Field routing

- Customer checkout: GSLT, map, game type/mode, selected frameworks, prerelease preference, console log level, console filter, cleanup, console log persistence, tickrate, map group, cooldown, server password, Workshop Collection.
- Product admin settings: tokenless policy, max players, update/validation controls, SwiftlyS2, Egg Bot Guard and its action/allow-lists, and custom startup parameters.
- Not exposed: fixed CS2 AppID, the secret `AUTHKEY` (its actual default is redacted), GDB development port, and `EXEC` because the current Egg startup command does not reference it.

`CUSTOM_PARAMS` is deliberately an **admin-only product setting** because it inserts free-form arguments into the startup command.

Optional checkout inputs use Paymenter's server-extension checkout hook; they are not native Config Options. In Paymenter 1.5.9, native text/number Config Options are validated as required, which is unsuitable for optional GSLT/password/Workshop fields.

**Credential storage note:** `STEAM_ACC` and `PASSWORD` are rendered as masked password inputs, but this extension does not encrypt their saved service-property values at rest. Restrict database, backup, and admin access accordingly. The GSLT is also stored by Pterodactyl as the Egg environment value.

## Source

- Egg: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/pterodactyl/egg-kitsune-lab-c-s2-go--k4ryuu-m-o-d-i-f-i-e-d.json
- Paymenter source compatibility: v1.5.9
- Manifest: `resources/cs2-egg-variables.json`
