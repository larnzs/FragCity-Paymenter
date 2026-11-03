# FragCity Paymenter Add-ons

Private development repository for FragCity-specific Paymenter extensions,
customer-facing themes, integrations, Persian translations and deployment tooling.

This repository is not a full Paymenter installation.

## Compatibility target
- Paymenter: v1.5.9
- Laravel: 12.x
- PHP: 8.3+

## Project structure
- `extensions/Others/FragCity`: FragCity application functionality and runtime locale registration.
- `themes/fragcity`: FragCity customer-facing theme.
- `lang/fa`: Persian translations for Paymenter's shared and customer-facing language groups.
- `deployment`: Deployment and update guidance.
- `docs`: Architecture and development documentation.
- `LICENSES`: Third-party license notices.

## Design identity
- Background: `#0D0D0D`
- Surface: `#171717`
- Primary: `#F97316`
- Accent: `#FFB347`

## Important
The Paymenter application core, its environment configuration, credentials,
database, user uploads and runtime data are not stored here.

The customer-facing theme is based on Paymenter's default theme.
See `LICENSES/Paymenter-MIT.txt`.

Persian localization is provided as an add-on: deploy `lang/fa` into the
Paymenter installation's `lang/fa` directory and enable the FragCity
extension. The extension registers the Persian locale label and RTL locale
metadata at runtime; Paymenter core files are not modified.
