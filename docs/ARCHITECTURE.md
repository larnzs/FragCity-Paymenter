# FragCity Paymenter Add-ons — Architecture

## 1. Repository responsibility

This repository contains only FragCity-owned add-ons and supporting documentation. It is not a Paymenter fork, and it must not contain a copy of the Paymenter core application.

Paymenter is installed separately from its official upstream repository at the pinned compatibility version. Upstream language files, Laravel application files, Filament core, Composer dependencies and runtime files remain untouched.

## 2. Components kept here

### CS2 server extension

`extensions/Servers/FragCityCS2`

Owns the Egg-specific server extension layer. It inherits Paymenter's built-in Pterodactyl server implementation and adds FragCity's CS2 checkout/product settings from the Egg manifest.

### Theme

`themes/fragcity`

Retained as a FragCity-owned theme add-on derived from Paymenter's default theme. It may be used where a Paymenter customer-facing view is intentionally required, but it does not define the primary FragCity customer experience and must not be used as a reason to send customers into Paymenter.

### FragCity API Bridge — planned

A dedicated `extensions/Others/FragCityAPI` extension will be introduced when the server-to-server Bridge is implemented. Its responsibilities will be limited to authenticated internal API routes, request validation, authorization, idempotency and calling Paymenter's supported service/payment helpers.

The API extension will be separate from `FragCityCS2`. It will not register locales, ship translation files or modify Paymenter core.

## 3. UI ownership and request flow

- FragCity Raw PHP owns the storefront, public catalogue, checkout, Zarinpal payment flow, customer server dashboard and administrator dashboard.
- Paymenter is a private backend supervisor for commercial records, invoices, services, expiry and server provisioning coordination.
- Pterodactyl/Wings own server records, nodes, allocations, resource limits, containers and runtime lifecycle.
- Customer operations must be authorized by FragCity's backend before being forwarded to the appropriate Paymenter or Pterodactyl API.
- No Paymenter Admin API key, Pterodactyl Application API key, Client API token or Wings credential may reach browser JavaScript.

## 4. Compatibility

Initial target: Paymenter v1.5.9, Laravel 12 and PHP 8.3+.

## 5. Upstream integrity

- Keep the upstream Paymenter installation as the source of truth for core and official language files.
- Do not copy upstream core into this repository.
- Do not maintain a Persian localization package or runtime locale-registration extension here.
- Do not patch upstream `app/`, `config/`, `routes/` or `vendor/` for FragCity functionality.
- Implement any required custom API behavior in the dedicated FragCity extension, using supported Paymenter extension hooks and helpers.

## 6. Security boundaries

- Never commit `.env` or private credentials.
- Never commit database dumps or customer records.
- Never commit runtime logs, sessions or cache.
- Validate service ownership and permissions on the server.
- Verify payment server-side before any provisioning request.
- Use idempotency keys and durable reconciliation for write operations.
- Expose only explicitly approved customer operations.
