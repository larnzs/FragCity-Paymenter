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
zarinpal="extensions/Gateways/ZarinPal/ZarinPal.php"
zarinpal_routes="extensions/Gateways/ZarinPal/routes.php"
zarinpal_readme="extensions/Gateways/ZarinPal/README.md"
zarinpal_migrations="extensions/Gateways/ZarinPal/database/migrations"

for dir in extensions/Others/FragCityAPI extensions/Others/FragCitySupportAPI extensions/Servers/FragCityCS2 extensions/Gateways/ZarinPal; do
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

test -d "$zarinpal_migrations"
while IFS= read -r -d '' file; do
  php -l "$file"
done < <(find "$zarinpal_migrations" -maxdepth 1 -type f -name '*.php' -print0 | sort -z)

# Native ZarinPal gateway and Paymenter Credits wallet contract.
grep -Fq "version: '1.0.1'" "$zarinpal"
test -f "$zarinpal_migrations/2026_11_10_000002_widen_encrypted_merchant_id.php"
grep -Fq "function canUseGateway" "$zarinpal"
grep -Fq "function installed" "$zarinpal"
grep -Fq "function upgraded" "$zarinpal"
grep -Fq "function callback" "$zarinpal"
grep -Fq "ExtensionHelper::runMigrations('extensions/Gateways/ZarinPal/database/migrations')" "$zarinpal"
grep -Fq "ExtensionHelper::addPayment(" "$zarinpal"
grep -Fq "InvoiceTransactionStatus::Succeeded" "$zarinpal"
grep -Fq "token_hash" "$zarinpal"
grep -Fq "Crypt::encryptString(\$merchantId)" "$zarinpal"
grep -Fq "Crypt::decryptString((string) \$payment->merchant_id)" "$zarinpal"
grep -Fq "hash('sha256', \$token)" "$zarinpal"
grep -Fq "verify.json" "$zarinpal"
grep -Fq "'currency' => \$attempt['currency_unit'] === 'toman' ? 'IRT' : 'IRR'" "$zarinpal"
grep -Fq "abs(\$rial - \$roundedRial) > 0.000001" "$zarinpal"
grep -Fq "manual_review" "$zarinpal"
grep -Fq "status' => 'succeeded'" "$zarinpal"
grep -Fq "extensions.gateways.zarinpal.callback" "$zarinpal_routes"
grep -Fq "credits_enabled" "$zarinpal_readme"
grep -Fq "credits_auto_use" "$zarinpal_readme"
! grep -Fq "Cache::" "$zarinpal"
! grep -Fq "data.amount" "$zarinpal"

# Extension versions and runtime bridge version must be released together.
grep -Fq "version: '1.13.0'" "$api"
grep -Fq "'version' => '1.13.0'" "$api"
grep -Fq "version: '1.7.0'" "$cs2"

# The first supported CS2 subscription is now three days. Keep the historical
# setting key so an existing Product ID value is not lost during extension update.
grep -Fq "'label' => 'CS2 3-day Plan ID'" "$api"
test "$(grep -Fc "3 => 'cs2_plan_id_1_day'" "$api")" -eq 3
test "$(grep -Fc "3 => [3, 'day']" "$api")" -eq 3
test "$(grep -Fc "7 => [7, 'day']" "$api")" -eq 3
test "$(grep -Fc "'in:3,7,15,30'" "$api")" -eq 2
grep -Fq '3 days = `3 days`' "$api_readme"
grep -Fq '7 days = `7 days`' "$api_readme"

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
