# Deployment

Deploy only the FragCity-owned add-ons required by the current integration stage into an existing, separately installed Paymenter instance.

## 1. Upstream Paymenter installation

1. Install Paymenter from its official upstream repository.
2. Keep the installation on the pinned compatibility target: **v1.5.9**.
3. Use the official upstream language files and normal Paymenter locale configuration.
4. Do not install any FragCity Persian language package; this repository no longer ships one.
5. Do not copy Paymenter core files into this add-ons repository or replace upstream application files.

## 2. FragCity add-ons

Deploy only the components required for the current test:

- `extensions/Servers/FragCityCS2` — required for CS2 server provisioning through the FragCity Egg.
- `themes/fragcity` — retained theme add-on. Its presence does not mean customers should use Paymenter's customer portal.

The separate `extensions/Others/FragCityAPI` server-to-server Bridge is planned but is **not implemented yet**. Do not create placeholder API routes or enable provisioning writes until its contract and implementation are complete.

After deploying the CS2 extension, rebuild Composer autoload and clear Laravel caches using the normal Paymenter deployment process.

## 3. Access boundary

FragCity customers use the Raw PHP FragCity website. Do not direct customers to the Paymenter customer portal or the Pterodactyl panel.

The Paymenter Admin area and Pterodactyl management interfaces remain restricted to authorized operators. All customer actions must be mediated by FragCity's backend.

## 4. Never overwrite

Deployment must not overwrite or replace:

- Paymenter core application files
- Official upstream language files
- `.env` or runtime secrets
- `vendor`
- database contents
- `storage` uploads, sessions, logs or cache

Deploy only the exact FragCity-owned theme/extension paths required for the current stage.
