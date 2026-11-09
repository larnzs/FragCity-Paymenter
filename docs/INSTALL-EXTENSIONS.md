# Plug-and-play Paymenter extension installation

This guide uses Paymenter's native ZIP uploader. No Paymenter Core files, Composer dependencies, or database dumps are shipped in the packages.

## What gets packaged

The workflow builds one ZIP per Paymenter Extension found under the repository's extensions directory:

| Package | Paymenter section | Purpose |
| --- | --- | --- |
| FragCityAPI | Admin → Extensions → Others | Private HMAC bridge, account sync, verified CS2 order acceptance and service coordination |
| FragCitySupportAPI | Admin → Extensions → Others | Separate signed support identity bridge |
| FragCityCS2 | Admin → Extensions → Servers | CS2 Egg contract, validation and Pterodactyl provisioning layer |

The FragCity theme is not a Paymenter Extension and cannot be installed through the Extension ZIP uploader. It remains a separate theme/Vite deployment.

The ZarinPal Gateway is intentionally not packaged from main. ZarinPal payment verification belongs to the FragCity website backend; Paymenter receives only the authenticated, verified order from the website bridge.

## Download the packages

1. Open the repository's GitHub Actions page: https://github.com/larnzs/FragCity-Paymenter/actions/workflows/package-extensions.yml
2. Open the latest successful run named **Package Paymenter Extensions**.
3. Download the artifact named like fragcity-paymenter-extensions-<commit>.
4. Extract the downloaded artifact once. It contains the individual extension ZIPs, PACKAGES.txt and SHA256SUMS.

The downloaded Actions artifact is only a bundle for convenience. Do **not** upload that outer bundle to Paymenter. Upload each contained extension ZIP separately.

The workflow rebuilds packages automatically after relevant changes are pushed to main. Use **Run workflow** to rebuild them manually at any time. Artifacts are retained for 30 days.

## Install order

Use the Paymenter Admin panel while logged in as an administrator:

1. Open **Admin → Extensions → Others**. Choose **Upload Extension**, drag in the FragCityAPI ZIP, and upload it. Then install **FragCity API** from the ready-to-install list.
2. Still under **Others**, upload and install the FragCitySupportAPI ZIP.
3. Open **Admin → Extensions → Servers**, upload and install the FragCityCS2 ZIP.

Install FragCityAPI first. FragCitySupportAPI reads the customer-to-Paymenter mapping created by FragCityAPI and therefore depends on its database table. FragCityCS2 is installed after the two API extensions so the complete FragCity integration is available before assigning it to a Product.

Paymenter v1.5.9 accepts one ZIP containing one extension folder. Each generated ZIP has this layout:

    FragCityAPI/
      FragCityAPI.php
      routes.php
      README.md
      database/
      ...

The same one-folder layout is used for the other extensions. This is intentional: Paymenter's uploader looks for the main PHP class at the top level or one directory below the ZIP extraction root.

The upload action copies extension files and invokes the extension lifecycle hook. The extension must still be registered/enabled from its corresponding **Others** or **Servers** list after upload. Do not manually insert rows into the extensions table.

## Configure after installation

### FragCityAPI

Open **Admin → Extensions → Others → FragCity API** and configure:

- A private HMAC shared secret generated with openssl rand -hex 32.
- Optional exact allowlisted source IPs.
- CS2 Product ID.
- The 1-day, 7-day, 15-day and 30-day Plan IDs.
- The existing Paymenter Rial currency code used by all configured Plans (for example `IRR`). Paymenter order and invoice records are always stored in Rial; FragCity's website may continue displaying Toman.

The HMAC secret must match the website's private integrations.paymenter_backend.shared_secret setting. Do not put it in browser JavaScript, Git, or public configuration.

### FragCitySupportAPI

Configure its own dedicated HMAC secret and optional exact source IP allowlist. Do not reuse FragCityAPI's HMAC secret. Create a dedicated Paymenter Admin API key for the website's native ticket and invoice operations, with only the permissions listed in the Support API README.

### FragCityCS2

Configure the Pterodactyl HTTPS host and Application API key inherited from Paymenter's Pterodactyl extension. Configure the optional read-only dashboard API key separately when available.

Then open the CS2 Product and select **FragCity CS2** as its Server extension. Configure Product-level Egg variables, Nest/Egg, node, location and allocation settings. Do not configure RAM, Storage or CPU in the inherited Pterodactyl Product fields; those fields are hidden by FragCityCS2. Attach the three native RAM/SSD/CPU Config Options to this Product and price their child values for every supported Plan/currency. Their selected values are stored as native ServiceConfig records and translated to Pterodactyl limits at provisioning/upgrade time.

See each extension's README for exact settings, routes, permissions and integration contracts.

## Updating an existing extension

1. Back up the Paymenter database and the extension's current directory.
2. Download and extract the latest Actions artifact.
3. Upload the newer ZIP for that extension using **Admin → Extensions → Upload Extension**.
4. Keep the existing extension registration. Do not uninstall it first.

Paymenter's uploader detects an existing extension directory, replaces its files and invokes the upgraded() lifecycle hook when the extension provides one. Database-backed settings remain in Paymenter; FragCityAPI runs its extension migrations from its install/upgrade hooks. Review the extension-specific README and test in staging before production updates.

### Important: prevent accidental API downgrade

Packages are built from the repository's **main** branch only. At the time this guide was added, main contains:

- FragCityAPI **1.5.0**
- FragCitySupportAPI **1.3.0**
- FragCityCS2 **1.4.0**

The separate native-paymenter-config-options branch has a different FragCityAPI 1.6.0 code line and also contains the old Paymenter-side ZarinPal Gateway. It is not the source for these main-branch packages.

If an existing Paymenter instance reports FragCityAPI **1.6.0**, do not upload the main-branch 1.5.0 ZIP as an update until that instance's code and the main-branch Bridge changes have been reconciled. Paymenter's uploader replaces the code directory; it does not block semantic-version downgrades.

## Safety and boundaries

- Upload one extension ZIP at a time. The package builder validates the root folder layout and Paymenter's 10 MiB upload limit.
- Never upload secrets, .env, vendor, Paymenter Core, customer data, database dumps or runtime storage.
- Never upload an older package over a newer installed version without reviewing the source changes.
- The packages contain no Paymenter core modifications and do not install ZarinPal inside Paymenter.
- Keep customer checkout and payment on FragCity. Paymenter is the private commercial and provisioning backend.
