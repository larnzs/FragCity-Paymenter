# FragCity Paymenter Architecture

## Repository responsibility

This repository stores only FragCity-owned customizations and add-ons.
The Paymenter application remains a separately installed dependency.

## Main components

### FragCity extension

`extensions/Others/FragCity`

Owns FragCity-specific integration logic, service authorization,
customer controls, application routes, models, policies and migrations.

### Customer-facing theme

`themes/fragcity`

Owns the customer-facing Paymenter interface:
layouts, navigation, pages, reusable components, CSS and JavaScript.

### Administration interface

Paymenter's administration interface is based on Filament.
Its FragCity-specific resources and functionality belong in the
FragCity extension. Admin visual customization will be handled separately
from the customer-facing theme.

## Compatibility

Initial target: Paymenter v1.5.9, Laravel 12 and PHP 8.3+.

## Security boundaries

- Never commit `.env` or private credentials.
- Never commit database dumps or customer records.
- Never commit runtime logs, sessions or cache.
- Validate service ownership and permissions on the server.
- Expose only explicitly approved customer operations.
