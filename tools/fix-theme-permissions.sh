#!/usr/bin/env bash
set -Eeuo pipefail

# Run from the Paymenter application root after extracting the FragCity theme ZIP.
# It changes permissions only for the FragCity theme and its public Vite assets.
APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
WEB_GROUP="${FRAGCITY_WEB_GROUP:-www-data}"
WEB_USER="${FRAGCITY_WEB_USER:-www-data}"

for parent in themes public; do
    parent_path="$APP_ROOT/$parent"
    [[ -d "$parent_path" && ! -L "$parent_path" ]] || {
        echo "ERROR: Expected Paymenter directory is missing or is a symlink: $parent_path" >&2
        exit 1
    }

    chgrp "$WEB_GROUP" "$parent_path"
    chmod g+rx,g+s "$parent_path"
done

for relative in themes/fragcity public/fragcity; do
    target="$APP_ROOT/$relative"
    [[ -d "$target" && ! -L "$target" ]] || {
        echo "ERROR: Expected FragCity deployment directory is missing or is a symlink: $target" >&2
        exit 1
    }

    chgrp -R "$WEB_GROUP" "$target"
    find "$target" -type d -exec chmod 2750 {} +
    find "$target" -type f -exec chmod 0640 {} +
done

[[ -r "$APP_ROOT/themes/fragcity/theme.php" ]] || {
    echo "ERROR: theme.php is still not readable by the invoking user." >&2
    exit 1
}
[[ -r "$APP_ROOT/public/fragcity/manifest.json" ]] || {
    echo "ERROR: Vite manifest is missing or unreadable." >&2
    exit 1
}

if command -v runuser >/dev/null 2>&1; then
    runuser -u "$WEB_USER" -- test -r "$APP_ROOT/themes/fragcity/theme.php" || {
        echo "ERROR: $WEB_USER cannot read themes/fragcity/theme.php." >&2
        exit 1
    }
    runuser -u "$WEB_USER" -- test -r "$APP_ROOT/public/fragcity/manifest.json" || {
        echo "ERROR: $WEB_USER cannot read public/fragcity/manifest.json." >&2
        exit 1
    }
fi

echo "SUCCESS: FragCity theme and public assets are readable by $WEB_USER ($WEB_GROUP)."
echo "Only themes/fragcity, public/fragcity, and the themes/public parent directory group inheritance were adjusted."
