#!/usr/bin/env bash
set -Eeuo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

api="extensions/Others/FragCityAPI/FragCityAPI.php"
routes="extensions/Others/FragCityAPI/routes.php"
api_readme="extensions/Others/FragCityAPI/README.md"
cs2="extensions/Servers/FragCityCS2/FragCityCS2.php"
cs2_readme="extensions/Servers/FragCityCS2/README.md"
manifest="extensions/Servers/FragCityCS2/resources/cs2-egg-variables.json"

for dir in extensions/Others/FragCityAPI extensions/Others/FragCitySupportAPI extensions/Servers/FragCityCS2; do
  test -d "$dir"
  while IFS= read -r -d '' file; do
    php -l "$file"
  done < <(find "$dir" -type f -name '*.php' -print0 | sort -z)
done

migration_dir="extensions/Others/FragCityAPI/database/migrations"
test -d "$migration_dir"
while IFS= read -r -d '' file; do
  php -l "$file"
done < <(find "$migration_dir" -maxdepth 1 -type f -name '*.php' -print0 | sort -z)

# Extension versions and runtime bridge version must be released together.
grep -Fq "version: '1.12.0'" "$api"
grep -Fq "'version' => '1.12.0'" "$api"
grep -Fq "version: '1.7.0'" "$cs2"

# Signed bridge, authoritative billing, receipt idempotency and native provisioning.
for needle in \
  "function authenticateBridgeRequest" \
  "hash_hmac('sha256'" \
  "Cache::add(" \
  "function cs2Catalog" \
  "function quoteCs2Order" \
  "expected_amount_rial" \
  "if (\$expectedAmountRial !== null && \$expectedAmountRial !== \$wholeRialTotal)" \
  "function createCs2Order" \
  "Settings::tax(\$user)" \
  "cs2_quote_amount_mismatch" \
  "zarinpal_payment_already_used" \
  "order_reference_conflict" \
  "ExtensionHelper::addPayment(" \
  "Invoice::STATUS_PAID" \
  "ProcessPaidInvoiceService" \
  "wallet_insufficient_credit" \
  "function prepareWalletTopup" \
  "function createWalletTopup"; do
  grep -Fq "$needle" "$api"
done

grep -Fq "function createServer" "$cs2"
grep -Fq "function upgradeServer" "$cs2"
grep -Fq "applyResourceLimitsFromConfigOptions(\$service, \$properties)" "$cs2"
test "$(grep -Fc 'applyNativeConfigOptions($service, $properties)' "$cs2")" -ge 2

# All eight service-level Config Options must survive Paymenter's native upgrade flow.
grep -Fq "!(bool) (\$configOption->upgradable ?? false)" "$api"
grep -Fq "!(bool) (\$option->upgradable ?? false)" "$cs2"
grep -Fq 'Enable `Upgradable` for all eight Config Options' "$cs2_readme"
grep -Fq 'Enable `Upgradable` for all eight Config Options' "$api_readme"

# Paymenter is Rial-only; do not reintroduce a browser-selected currency unit or
# duplicated resource properties that could drift from native ServiceConfig rows.
grep -Fq "\$currencyUnit = 'rial'" "$api"
grep -Fq "\$paymenterAmount = \$amountRial;" "$api"
! grep -Fq "'name' => 'cs2_currency_unit'" "$api"
! grep -Fq "'memory' => (string) \$memoryMb" "$api"
! grep -Fq "'disk' => (string) \$diskMb" "$api"
! grep -Fq "'cpu' => (string) \$cpuPercent" "$api"

grep -Fq "/api/v1/fragcity/bridge/orders/cs2" "$routes"
grep -Fq "/api/v1/fragcity/bridge/quotes/cs2" "$routes"
grep -Fq "Settings::tax(\$user)" "$api"
grep -Fq "/api/v1/fragcity/bridge/wallet/topups/prepare" "$routes"
grep -Fq "/api/v1/fragcity/bridge/wallet/topups/zarinpal" "$routes"
route_count="$(grep -c "Route::post('/api/v1/fragcity/bridge/" "$routes")"
readme_route_count="$(grep -c -- '- `POST /api/v1/fragcity/bridge/' "$api_readme")"
test "$route_count" -ge 11
test "$readme_route_count" -eq "$route_count"

php -r '
  $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
  $checkout = array_column($manifest["paymenter"]["checkout_fields"] ?? [], "name");
  $expectedCheckout = ["SRCDS_MAP", "EXEC", "MAPGROUP", "COOLDOWN", "ENABLE_GUARD", "GUARD_BAN_MINUTES"];
  if (array_diff($expectedCheckout, $checkout) || array_diff($checkout, $expectedCheckout)) {
    fwrite(STDERR, "CS2 Egg Checkout manifest does not match the six-field Checkout contract.\\n");
    exit(1);
  }

  $configOptions = $manifest["paymenter"]["config_options"] ?? [];
  $expectedOptions = ["SRCDS_MAXPLAYERS", "TICKRATE", "databases", "additional_allocations", "backups"];
  $actualOptions = array_column($configOptions, "env_variable");
  if (count($configOptions) !== 5 || array_diff($expectedOptions, $actualOptions) || array_diff($actualOptions, $expectedOptions)) {
    fwrite(STDERR, "CS2 native Config Option manifest does not match the five non-resource options.\\n");
    exit(1);
  }

  foreach ($configOptions as $option) {
    if (($option["type"] ?? null) !== "select" || empty($option["env_variable"])) {
      fwrite(STDERR, "Every native CS2 Config Option must be a named Select.\\n");
      exit(1);
    }
    if (($option["env_variable"] ?? null) === "TICKRATE") {
      $values = array_column($option["options"] ?? [], "value");
      if ($values !== ["64", "128"]) {
        fwrite(STDERR, "Tickrate must expose exactly 64 and 128.\\n");
        exit(1);
      }
    }
  }
  echo "CS2 checkout and native Config Option manifests OK", PHP_EOL;
' "$manifest"

echo "FragCity Paymenter validation passed."
