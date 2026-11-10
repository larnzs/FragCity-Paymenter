# FragCity Theme installation (Paymenter v1.5.9)

The FragCity theme is a Laravel Blade theme, not a Paymenter Extension. Do not use Admin → Extensions → Upload Extension for this ZIP.

## What the release ZIP contains

The deploy-ready package has two application-root paths:

- `themes/fragcity/` — theme metadata, Laravel Blade views, source CSS and JavaScript.
- `public/fragcity/` — Vite production manifest and compiled, hashed assets.

Paymenter loads the active theme assets through Laravel's `@vite(..., 'fragcity')` directive. Both paths must be present. A theme source folder without its matching Vite manifest can cause Laravel to return HTTP 500 when the layout renders.

## Install through cPanel File Manager (no SSH)

1. Open the Paymenter application root — the directory containing `artisan`, `app/`, `bootstrap/`, `public/`, `themes/` and `.env`.
2. Make a backup of the existing `themes/fragcity/` and `public/fragcity/` directories, if present.
3. Upload `FragCityPaymenterTheme-*.zip` into that application root.
4. Extract the ZIP at that same root. Allow the `themes/` and `public/` directories to merge.
5. Confirm these files exist:
   - `themes/fragcity/theme.php`
   - `themes/fragcity/views/home.blade.php`
   - `public/fragcity/manifest.json`
6. In Paymenter Admin → Settings → Theme, select `FragCity` and save (or leave it selected if it is already active and the manifest now exists).
7. Open the storefront in a private/incognito window and verify the home page, category page, product detail, cart, login and dashboard.

The ZIP is built by GitHub Actions against the pinned Paymenter v1.5.9 source. The host does not need Node.js, npm, Composer or SSH.

## Rollback

If the new theme does not load:

1. Restore the backed-up `themes/fragcity/` and `public/fragcity/` directories together.
2. If the admin panel is reachable, switch the active theme to `default`.
3. If the admin panel is also unavailable, change only the global `settings.theme` value to `default` in the Paymenter database using phpMyAdmin. Do not delete settings rows, users, invoices, transactions or orders.
4. Refresh the storefront and verify that the default theme works before attempting another deployment.

## Deployment boundaries

Never extract this ZIP into the `extensions/` directory. Never overwrite the following Paymenter paths:

- `.env`
- `vendor/`
- `app/`
- `database/`
- `storage/`
- `bootstrap/`

The package only overlays `themes/fragcity/` and `public/fragcity/`.
