# FragCity Theme installation (Paymenter v1.5.9)

The FragCity theme is a Laravel Blade theme, not a Paymenter Extension. Do not use Admin → Extensions → Upload Extension for this ZIP.

## What the release ZIP contains

The deploy-ready package has three application-root items:

- `themes/fragcity/` — theme metadata, Laravel Blade views, source CSS and JavaScript.
- `public/fragcity/` — Vite production manifest and compiled, hashed assets.
- `fragcity-fix-permissions.sh` — a narrowly scoped helper for Linux deployments.

Paymenter loads the active theme assets through Laravel's `@vite(..., 'fragcity')` directive. Both theme and public asset paths must be present.

## Install on a Linux server

1. Open the Paymenter application root — the directory containing `artisan`, `app/`, `bootstrap/`, `public/`, `themes/` and `.env`.
2. Back up the existing `themes/fragcity/` and `public/fragcity/` directories, if present.
3. Upload `FragCityPaymenterTheme-*.zip` into that application root.
4. Extract the ZIP at that same root. Allow the `themes/` and `public/` directories to merge.
5. From the application root, run the included helper as root:

   ```bash
   sudo bash ./fragcity-fix-permissions.sh
   ```

   The helper assigns only `themes/fragcity` and `public/fragcity` to the PHP-FPM group (default: `www-data`), makes their files readable and directories traversable by that group, and enables group inheritance on the existing `themes/` and `public/` parents. It verifies that the PHP-FPM user can read the theme metadata and Vite manifest. It does not change `app/`, `vendor/`, `.env`, storage, the database or any other theme.

   For a host using a different PHP-FPM user/group, run it with the appropriate values, for example: `sudo FRAGCITY_WEB_USER=phpuser FRAGCITY_WEB_GROUP=phpgroup bash ./fragcity-fix-permissions.sh`.
6. In Paymenter Admin → Settings → Theme, select `FragCity` and save.
7. Open the storefront in a private/incognito window and verify the home page, category page, product detail, cart, login and dashboard.

### Why the helper is included

ZIP archives do not preserve Unix ownership. On servers that extract as `root` with a restrictive umask, theme files can end up owned by `root:root` with mode `640`, while PHP-FPM runs as `www-data`. PHP then cannot read `themes/fragcity/theme.php`, even though the file exists. The helper repairs only the two FragCity deployment directories and sets group inheritance so future updates do not repeat the same issue.

The ZIP is built by GitHub Actions against the pinned Paymenter v1.5.9 source. The host does not need Node.js, npm or Composer.

## Install through cPanel File Manager (no SSH)

1. Open the Paymenter application root.
2. Back up the existing `themes/fragcity/` and `public/fragcity/` directories.
3. Upload the theme ZIP and extract it at the application root, allowing the directories to merge.
4. If PHP runs under the same hosting account that extracted the files, select FragCity in Paymenter Admin → Settings → Theme.
5. If PHP runs under a separate PHP-FPM user/group, the extracted files must be assigned to that PHP-FPM group with directory traversal and file-read access. Ask the host to apply the equivalent of the included helper to `themes/fragcity` and `public/fragcity`; do not set permissions to `777`.

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

The package overlays only `themes/fragcity/` and `public/fragcity/`; its helper changes permissions only on those paths plus group inheritance on the `themes/` and `public/` parent directories.
