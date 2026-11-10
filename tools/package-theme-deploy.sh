#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PAYMENTER_ROOT="${1:?Usage: package-theme-deploy.sh PAYMENTER_BUILD_ROOT OUTPUT_DIR RELEASE_TAG}"
OUT_DIR="${2:?Usage: package-theme-deploy.sh PAYMENTER_BUILD_ROOT OUTPUT_DIR RELEASE_TAG}"
RELEASE_TAG="${3:?Usage: package-theme-deploy.sh PAYMENTER_BUILD_ROOT OUTPUT_DIR RELEASE_TAG}"

THEME_DIR="$ROOT/themes/fragcity"
ASSET_DIR="$PAYMENTER_ROOT/public/fragcity"
MANIFEST="$ASSET_DIR/manifest.json"
ARCHIVE="FragCityPaymenterTheme-$RELEASE_TAG.zip"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

for command_name in zip unzip sha256sum node; do
    command -v "$command_name" >/dev/null 2>&1 || {
        echo "ERROR: Required command not found: $command_name" >&2
        exit 1
    }
done

[[ -f "$THEME_DIR/theme.php" ]] || {
    echo "ERROR: Theme metadata is missing: $THEME_DIR/theme.php" >&2
    exit 1
}
[[ -f "$THEME_DIR/views/home.blade.php" ]] || {
    echo "ERROR: Theme homepage Blade view is missing." >&2
    exit 1
}
[[ -f "$THEME_DIR/views/layouts/app.blade.php" ]] || {
    echo "ERROR: Theme application layout is missing." >&2
    exit 1
}
[[ -f "$MANIFEST" ]] || {
    echo "ERROR: Built Vite manifest is missing: $MANIFEST" >&2
    exit 1
}
[[ -d "$ASSET_DIR/assets" ]] || {
    echo "ERROR: Compiled Vite assets directory is missing: $ASSET_DIR/assets" >&2
    exit 1
}

if find "$THEME_DIR" -type l -print -quit | grep -q .; then
    echo "ERROR: Symlinks are not allowed inside the theme package." >&2
    exit 1
fi

# Validate the actual Vite entrypoints used by layouts/app.blade.php and
# verify every emitted JS/CSS/asset dependency exists before creating a ZIP.
node - "$MANIFEST" "$ASSET_DIR" <<'NODE'
const fs = require('node:fs');
const path = require('node:path');

const manifestPath = process.argv[2];
const publicDir = path.resolve(process.argv[3]);
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const keys = Object.keys(manifest);

const requiredEntrypoints = [
  'themes/fragcity/js/app.js',
  'themes/fragcity/css/app.css',
];

for (const wanted of requiredEntrypoints) {
  const key = keys.find((candidate) => candidate.replaceAll('\\\\', '/').endsWith(wanted));
  if (!key) {
    throw new Error(`Vite manifest is missing the required entrypoint: ${wanted}`);
  }

  const visited = new Set();
  const visit = (entryKey) => {
    if (visited.has(entryKey)) return;
    visited.add(entryKey);

    const entry = manifest[entryKey];
    if (!entry) throw new Error(`Vite manifest references a missing entry: ${entryKey}`);

    const emitted = [entry.file, ...(entry.css || []), ...(entry.assets || [])].filter(Boolean);
    for (const relative of emitted) {
      const target = path.resolve(publicDir, relative);
      if (!target.startsWith(publicDir + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) {
        throw new Error(`Vite output is missing or unsafe: ${relative}`);
      }
    }

    for (const imported of [...(entry.imports || []), ...(entry.dynamicImports || [])]) {
      visit(imported);
    }
  };

  visit(key);
}

console.log('FragCity Vite manifest and required entrypoints validated.');
NODE

# Ensure the deployment archive overlays only the theme source and its
# precompiled public assets onto an existing Paymenter installation.
STAGE="$TMP_DIR/stage"
mkdir -p "$STAGE/themes" "$STAGE/public"
cp -a "$THEME_DIR" "$STAGE/themes/fragcity"
cp -a "$ASSET_DIR" "$STAGE/public/fragcity"

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"

(
    cd "$STAGE"
    zip -qr "$TMP_DIR/$ARCHIVE" themes/fragcity public/fragcity \
        -x "themes/fragcity/.DS_Store" \
           "themes/fragcity/**/.DS_Store" \
           "themes/fragcity/**/.git/**" \
           "themes/fragcity/**/__MACOSX/**"
)

unzip -tq "$TMP_DIR/$ARCHIVE" >/dev/null
unzip -Z1 "$TMP_DIR/$ARCHIVE" | grep -Fqx "themes/fragcity/theme.php" || {
    echo "ERROR: Deployment ZIP is missing themes/fragcity/theme.php." >&2
    exit 1
}
unzip -Z1 "$TMP_DIR/$ARCHIVE" | grep -Fqx "public/fragcity/manifest.json" || {
    echo "ERROR: Deployment ZIP is missing public/fragcity/manifest.json." >&2
    exit 1
}

mv "$TMP_DIR/$ARCHIVE" "$OUT_DIR/$ARCHIVE"

{
    echo
    echo "Paymenter Theme | fragcity | deploy-ready overlay | $ARCHIVE"
    echo
    echo "This archive contains:"
    echo "- themes/fragcity/ (Laravel Blade theme source)"
    echo "- public/fragcity/ (Vite production manifest and compiled assets)"
    echo
    echo "Upload and extract this ZIP at the Paymenter application root."
    echo "Do not upload it through Admin -> Extensions -> Upload Extension."
    echo "Do not overwrite .env, vendor, app, database, storage or bootstrap."
} >> "$OUT_DIR/PACKAGES.txt"

{
    echo "FragCity Paymenter Theme deployment bundle"
    echo "Release: $RELEASE_TAG"
    echo "Paymenter compatibility: v1.5.9"
    echo "Source commit: $(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
    echo "Theme source: themes/fragcity/"
    echo "Compiled Vite output: public/fragcity/"
    echo
    echo "INSTALL:"
    echo "1. Back up the existing themes/fragcity and public/fragcity directories."
    echo "2. Upload this ZIP to the Paymenter application root using File Manager."
    echo "3. Extract it at the application root and allow folders to merge."
    echo "4. Keep the active theme setting as fragcity only after both directories exist."
    echo "5. Never use the Paymenter Extension Uploader for this ZIP."
    echo
    echo "The ZIP includes the Vite production manifest and all compiled JS/CSS assets."
    echo "No Node.js, npm, Composer or SSH command is required on the hosting account."
} > "$OUT_DIR/THEME-PACKAGE.txt"

echo "SUCCESS: Built deploy-ready FragCity theme package: $OUT_DIR/$ARCHIVE"
