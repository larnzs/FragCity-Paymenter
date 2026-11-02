# Deployment

Deployment scripts will be added after the extension and theme structure
is validated against the installed Paymenter v1.5.9 environment.

Deployment must not overwrite:

- Paymenter core application files
- `.env` or runtime secrets
- `vendor`
- database contents
- `storage` uploads, sessions, logs or cache

Only FragCity-owned extension and theme files should be deployed.
