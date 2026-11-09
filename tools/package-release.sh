#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "\${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="\${1:?Usage: package-release.sh OUTPUT_DIR RELEASE_TAG}"
RELEASE_TAG="\${2:?Usage: package-release.sh OUTPUT_DIR RELEASE_TAG}"

if [[ ! "$RELEASE_TAG" =~ ^[A-Za-z0-9._-]+$ ]]; then
    echo "ERROR: Invalid release tag: $RELEASE_TAG" >&2
    exit 1
fi

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"

# Build and validate the three native Paymenter Extension ZIPs.
bash "$ROOT/tools/package-extensions.sh" "$OUT_DIR"

THEME_DIR="$ROOT/themes/fragcity"
THEME_ARCHIVE="FragCityPaymenterTheme-\${RELEASE_TAG}.zip"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

for command_name in zip unzip sha256sum; do
    command -v "$command_name" >/dev/null 2>&1 || {
        echo "ERROR: Required command not found: $command_name" >&2
        exit 1
    }
done

[[ -f "$THEME_DIR/theme.php" ]] || {
    echo "ERROR: FragCity Paymenter theme metadata is missing." >&2
    exit 1
}

if find "$THEME_DIR" -type l -print -quit | grep -q .; then
    echo "ERROR: Symlinks are not allowed inside the theme package." >&2
    exit 1
fi

# The archive root is fragcity/, so it can be extracted directly into
# the Paymenter themes/ directory. This is a theme source bundle, not
# an Extension ZIP and must not be uploaded through Extension Uploader.
(
    cd "$ROOT/themes"
    zip -qr "$TMP_DIR/$THEME_ARCHIVE" fragcity \
        -x "fragcity/.DS_Store" \
           "fragcity/**/.DS_Store" \
           "fragcity/**/.git/**" \
           "fragcity/**/__MACOSX/**"
)

unzip -tq "$TMP_DIR/$THEME_ARCHIVE" >/dev/null
theme_roots="$(unzip -Z1 "$TMP_DIR/$THEME_ARCHIVE" | awk -F/ 'NF { print $1 }' | sort -u)"
[[ "$theme_roots" == "fragcity" ]] || {
    echo "ERROR: Theme archive must contain only fragcity/ at its root." >&2
    exit 1
}
unzip -Z1 "$TMP_DIR/$THEME_ARCHIVE" | grep -Fqx "fragcity/theme.php" || {
    echo "ERROR: Theme archive is missing fragcity/theme.php." >&2
    exit 1
}

mv "$TMP_DIR/$THEME_ARCHIVE" "$OUT_DIR/$THEME_ARCHIVE"

{
    echo
    echo "Paymenter Theme | fragcity | source bundle | $THEME_ARCHIVE"
    echo
    echo "Theme installation note:"
    echo "- This is a theme source bundle, not a Paymenter Extension."
    echo "- Extract its fragcity/ folder into the Paymenter installation's themes/ directory."
    echo "- Follow the theme deployment guide and rebuild Vite assets on the Paymenter installation."
    echo "- Do not upload this ZIP through Admin -> Extensions -> Upload Extension."
} >> "$OUT_DIR/PACKAGES.txt"

{
    echo "FragCity Paymenter Theme source bundle"
    echo "Release: $RELEASE_TAG"
    echo "Source branch: \${GITHUB_REF_NAME:-$(git -C "$ROOT" branch --show-current 2>/dev/null || echo unknown)}"
    echo "Source commit: $(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
    echo "Theme source: themes/fragcity/"
    echo
    echo "This is not a Paymenter Extension ZIP."
    echo "Extract fragcity/ into the Paymenter installation's themes/ directory."
    echo "Rebuild Vite assets using the deployment procedure documented in the release notes."
} > "$OUT_DIR/THEME-PACKAGE.txt"

(
    cd "$OUT_DIR"
    sha256sum ./*.zip > SHA256SUMS
)

echo
echo "SUCCESS: Built 3 Paymenter Extension ZIPs and 1 FragCity Theme source ZIP."
echo "Release tag: $RELEASE_TAG"
echo "Output: $OUT_DIR"
echo "Checksums: $OUT_DIR/SHA256SUMS"
