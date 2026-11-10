#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:?Usage: package-release.sh OUTPUT_DIR RELEASE_TAG}"
RELEASE_TAG="${2:?Usage: package-release.sh OUTPUT_DIR RELEASE_TAG}"
PAYMENTER_BUILD_ROOT="${PAYMENTER_BUILD_ROOT:-}"

if [[ ! "$RELEASE_TAG" =~ ^[A-Za-z0-9._-]+$ ]]; then
    echo "ERROR: Invalid release tag: $RELEASE_TAG" >&2
    exit 1
fi

[[ -n "$PAYMENTER_BUILD_ROOT" && -d "$PAYMENTER_BUILD_ROOT" ]] || {
    echo "ERROR: PAYMENTER_BUILD_ROOT must point to the checked-out Paymenter v1.5.9 build tree." >&2
    exit 1
}

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"

# Build and validate all native Paymenter Extension ZIPs.
bash "$ROOT/tools/package-extensions.sh" "$OUT_DIR"

# Build a deploy-ready overlay containing both Laravel theme source and
# the exact production Vite manifest/assets required by @vite(..., 'fragcity').
bash "$ROOT/tools/package-theme-deploy.sh" "$PAYMENTER_BUILD_ROOT" "$OUT_DIR" "$RELEASE_TAG"

(
    cd "$OUT_DIR"
    sha256sum ./*.zip > SHA256SUMS
)

echo
echo "SUCCESS: Built 4 Paymenter Extension ZIPs and 1 deploy-ready FragCity Theme ZIP."
echo "Release tag: $RELEASE_TAG"
echo "Output: $OUT_DIR"
echo "Checksums: $OUT_DIR/SHA256SUMS"
