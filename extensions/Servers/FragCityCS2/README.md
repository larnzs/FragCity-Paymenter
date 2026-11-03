# FragCity CS2 — Paymenter Server Extension

This extension adds a customer-facing CS2 configuration form to Paymenter **v1.5.9** while inheriting the built-in Pterodactyl server lifecycle and API implementation.

## What it does

- Uses the custom FragCity CS2 Egg manifest as the single source of truth for checkout fields.
- Provides 17 checkout fields automatically; no manual Config Option creation is required.
- Keeps Pterodactyl API host/key in the server extension settings.
- Inherits the built-in Pterodactyl methods for node, nest, egg, allocations, server creation, suspension, unsuspension, termination, and actions.
- Leaves all 32 Egg variables documented in `resources/cs2-egg-variables.json`, including those deliberately kept internal or admin-only.

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

Open Paymenter Admin → Extensions / Servers and enable **FragCity CS2**.

Configure its Pterodactyl URL and Application API key. These settings are separate from the built-in Pterodactyl extension because Paymenter stores settings per server extension. Use the same URL and API key as the existing Pterodactyl extension.

For a new or test product, select **FragCity CS2** as its server extension and configure its normal Pterodactyl product settings (node, location, nest, egg, resources, allocations, etc.).

**Do not switch an existing production product until the extension has been enabled, configured, and tested with a test product.** The extension inherits the Pterodactyl implementation, but the selected server-extension record and its credentials are distinct.

## Field policy

The manifest contains all 32 Egg variables. 17 safe, useful variables are exposed at checkout.

The following are intentionally not exposed to customers:

- Internal Egg controls and fixed AppID / player-limit / update settings.
- `AUTHKEY`: secret server-side value. Its actual default is redacted from the manifest.
- `CUSTOM_PARAMS`: arbitrary startup arguments; must remain admin-controlled.
- Bot Guard sub-options while the Egg keeps Bot Guard itself locked for customers.
- `EXEC`: the current Egg startup command does not reference this variable, so adding it to checkout would not change the server startup behavior.
- GDB development-only setting and other Egg variables marked non-editable.

Optional text/number inputs are handled through Paymenter's server-extension `getCheckoutConfig()` hook. They are not represented as native Config Options, because Paymenter 1.5.9 validates native text/number Config Options as required at checkout.

## Source of truth

- Egg repository: https://github.com/larnzs/cs2-kus-addons/blob/integration/kus-monorepo/pterodactyl/egg-kitsune-lab-c-s2-go--k4ryuu-m-o-d-i-f-i-e-d.json
- Paymenter compatibility: v1.5.9
- Generated manifest: `resources/cs2-egg-variables.json`

If the Egg variables change, regenerate the manifest and review the checkout field policy before deploying the update.
