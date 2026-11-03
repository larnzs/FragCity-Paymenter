# Deployment

Deploy only FragCity-owned files into the existing Paymenter installation.

## Persian localization
1. Copy this repository's `lang/fa` directory to the Paymenter installation's `lang/fa` directory.
2. Deploy `extensions/Others/FragCity` and `themes/fragcity` using the normal extension/theme deployment procedure.
3. In Paymenter, install and enable the **FragCity** extension. Its `boot()` method registers the `fa` locale label and RTL metadata at runtime.
4. In **Admin → Settings → General**, set **Default Language** to `fa` and include `fa` in **Allowed Languages**.
5. Clear cached views/configuration if used, then verify customer pages in a fresh browser session.

Do not modify Paymenter core files such as `config/app.php`, files under `app/`, or files under `vendor/`.

Deployment must not overwrite:
- Paymenter core application files
- `.env` or runtime secrets
- `vendor`
- database contents
- `storage` uploads, sessions, logs or cache
