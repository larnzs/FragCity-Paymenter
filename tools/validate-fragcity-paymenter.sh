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
grep -Fq "version: '1.0.4'" "$zarinpal"
test -f "$zarinpal_migrations/2026_11_10_000002_widen_encrypted_merchant_id.php"
grep -Fq "Crypt::encryptString(\$stored)" "$zarinpal_migrations/2026_11_10_000002_widen_encrypted_merchant_id.php"
! grep -Fq "use Throwable;" "$zarinpal_migrations/2026_11_10_000002_widen_encrypted_merchant_id.php"
grep -Fq "'phase' => 'payment_request'" "$zarinpal"
grep -Fq "'http_status' => \$responseStatus" "$zarinpal"
grep -Fq "'provider_error_code' => \$providerErrorCode" "$zarinpal"
grep -Fq "'provider_error_message' => \$providerErrorMessage" "$zarinpal"
grep -Fq "private function safeDiagnosticText" "$zarinpal"
! grep -Fq "'response_body' =>" "$zarinpal"
grep -Fq "function canUseGateway" "$zarinpal"
grep -Fq "function installed" "$zarinpal"
grep -Fq "function upgraded" "$zarinpal"
grep -Fq "function callback" "$zarinpal"
grep -Fq "private function ensurePaymentTable" "$zarinpal"
grep -Fq "private function buildCallbackUrl" "$zarinpal"
grep -Fq "https://checkout.fragcity.ir" "$zarinpal"
grep -Fq "callback_load_payment" "$zarinpal"
grep -Fq "callback_claim_payment" "$zarinpal"
grep -Fq "payment_attempt_prepare" "$zarinpal"
grep -Fq 'in_array($code, [100, 101], true)' "$zarinpal"
grep -Fq "ExtensionHelper::runMigrations('extensions/Gateways/ZarinPal/database/migrations')" "$zarinpal"
grep -Fq "ExtensionHelper::addPayment(" "$zarinpal"
grep -Fq "InvoiceTransactionStatus::Succeeded" "$zarinpal"
grep -Fq "token_hash" "$zarinpal"
grep -Fq "Crypt::encryptString(\$merchantId)" "$zarinpal"
grep -Fq "Crypt::decryptString((string) \$payment->merchant_id)" "$zarinpal"
grep -Fq "hash('sha256', \$token)" "$zarinpal"
grep -Fq "verify.json" "$zarinpal"
grep -Fq "'currency' => 'IRR'" "$zarinpal"
grep -Fq "abs(\$rial - \$roundedRial) > 0.000001" "$zarinpal"
grep -Fq "manual_review" "$zarinpal"
grep -Fq "status' => 'succeeded'" "$zarinpal"
grep -Fq "extensions.gateways.zarinpal.callback" "$zarinpal_routes"
grep -Fq "credits_enabled" "$zarinpal_readme"
grep -Fq "credits_auto_use" "$zarinpal_readme"
grep -Fq "https://checkout.fragcity.ir" "$zarinpal_readme"
! grep -Fq "Cache::" "$zarinpal"
! grep -Fq "data.amount" "$zarinpal"

# Extension versions and runtime bridge version must be released together.
grep -Fq "version: '1.18.3'" "$api"
grep -Fq "'version' => '1.18.3'" "$api"
test -s "deployment/patches/paymenter-1.5.9-sensitive-config-options.patch"
grep -Fq "sensitiveConfigOptions" "deployment/patches/paymenter-1.5.9-sensitive-config-options.patch"
grep -Fq "function startSso" "$api"
grep -Fq "function completeSso" "$api"
grep -Fq "function ssoTfaPage" "$api"
grep -Fq "loadAuthenticatedFragCityProfile" "$api"
grep -Fq "/api/paymenter-profile.php" "$api"
grep -Fq "View::composer('components.navigation.index'" "$api"

grep -Fq "fragcity_sso_2fa_return_to" "$api"
grep -Fq "\$destination = \$returnTo === '/' ? '/' : \$returnTo;" "$api"
! grep -Fq "route('home')" "$api"
grep -Fq "extensions.fragcity-api.sso.2fa" "$routes"
test -f "extensions/Others/FragCityAPI/Support/SsoTfa.php"
grep -Fq "class SsoTfa extends Component" "extensions/Others/FragCityAPI/Support/SsoTfa.php"
grep -Fq "Session::pull('fragcity_sso_2fa_return_to', '/')" "extensions/Others/FragCityAPI/Support/SsoTfa.php"
! grep -Fq "route('home')" "extensions/Others/FragCityAPI/Support/SsoTfa.php"
grep -Fq "new TwoFactorAuth(new EndroidQrCodeProvider" "extensions/Others/FragCityAPI/Support/SsoTfa.php"
grep -Fq "Cache::has(\$usedCodeKey)" "extensions/Others/FragCityAPI/Support/SsoTfa.php"
test -f "themes/fragcity/views/auth/fragcity-sso-tfa.blade.php"
grep -Fq "auth.fragcity-sso-tfa" "extensions/Others/FragCityAPI/Support/SsoTfa.php"
grep -Fq "Always prove the FragCity identity" "$api"
grep -Fq "fragcity_sso_used_tickets" "$api"
grep -Fq "/auth/fragcity/sso/start" "$routes"
grep -Fq "/auth/fragcity/sso/callback" "$routes"
grep -Fq "fragcitySsoEnabled" "$api"

# FragCity browser SSO origin checks stay strict, while accepting the canonical www alias
# and same-site top-level form posts when shared hosting omits the Origin header.
grep -Fq "private function ssoWebsiteOriginAliases" "$api"
grep -Fq "Sec-Fetch-Site" "$api"
grep -Fq "Sec-Fetch-Mode" "$api"
grep -Fq "Sec-Fetch-Dest" "$api"
grep -Fq "private function redirectSsoErrorToModal" "$api"
grep -Fq "query.get('auth') === 'login'" themes/fragcity/js/fragcity-auth-modal.js
grep -Fq '<div class="fc-auth-modal__content">' themes/fragcity/views/layouts/app.blade.php
grep -Fq 'https://fragcity.ir/register/' themes/fragcity/views/auth/login.blade.php
! grep -Fq 'shock-register-btn' themes/fragcity/views/components/navigation/index.blade.php
! grep -Fq 'ساخت حساب کاربری' themes/fragcity/views/components/navigation/index.blade.php
test -f "extensions/Others/FragCityAPI/database/migrations/2026_11_11_000009_create_fragcity_sso_used_tickets_table.php"
grep -Fq "ticket ID prevents replay" "$api_readme"
grep -Fq "version: '1.8.3'" "$cs2"
grep -Fq "public function getActions(Service \$service): array" "$cs2"
! grep -Fq "function getActions(Service \$service, " "$cs2"
grep -Fq "function getCustomerDashboardView" "$cs2"
test -s "extensions/Servers/FragCityCS2/views/customer-dashboard.blade.php"


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
  "private function assertCs2TextConfigOptions" \
  "assertCs2TextConfigOptions(\$product, \$manifest)" \
  "assertCs2TextConfigOptions(\$product, \$cs2Manifest)" \
  "cs2_text_config_option_mapping_invalid" \
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
grep -Fq "applyNativeEggConfigOptionsToPterodactyl(\$service, \$settings, \$properties)" "$cs2"
grep -Fq "'/api/application/servers/' . \$serverId . '/startup'" "$cs2"
grep -Fq "Pterodactyl did not persist the requested CS2 startup variable" "$cs2"
grep -Fq "applyResourceLimitsFromConfigOptions(\$service, \$properties)" "$cs2"
grep -Fq "'memory' => 1048576" "$cs2"
grep -Fq "'disk' => 1048576" "$cs2"
grep -Fq "'cpu' => 6400" "$cs2"
! grep -Fq "in_array(\$resolved['disk']" "$cs2"
! grep -Fq "in_array(\$resolved['memory']" "$cs2"
! grep -Fq "in_array(\$resolved['cpu']" "$cs2"
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

  $serviceProperties = $manifest["paymenter"]["service_properties"] ?? [];
  if (count($serviceProperties) !== 1 || ($serviceProperties[0]["env_variable"] ?? null) !== "STEAM_ACC") {
    fwrite(STDERR, "CS2 per-service GSLT text property is missing.\\n");
    exit(1);
  }
  $pterodactylOptions = $manifest["paymenter"]["pterodactyl_options"] ?? [];
  if (count($pterodactylOptions) !== 1 || ($pterodactylOptions[0]["env_variable"] ?? null) !== "servername") {
    fwrite(STDERR, "CS2 Pterodactyl Server Name option must use servername.\\n");
    exit(1);
  }
  if (($serviceProperties[0]["type"] ?? null) !== "text"
    || !in_array("nullable", $serviceProperties[0]["validation"] ?? [], true)
    || !in_array("max:32", $serviceProperties[0]["validation"] ?? [], true)
    || ($pterodactylOptions[0]["type"] ?? null) !== "text"
    || ($pterodactylOptions[0]["required"] ?? false) !== true
    || !in_array("required", $pterodactylOptions[0]["validation"] ?? [], true)
    || !in_array("max:64", $pterodactylOptions[0]["validation"] ?? [], true)
    || !in_array("regex:/^[A-Za-z0-9_. -]+$/", $pterodactylOptions[0]["validation"] ?? [], true)) {
    fwrite(STDERR, "CS2 GSLT and Server Name must remain validated text properties.\\n");
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


# Customer portal redesign: keep the storefront and customer workflow in the FragCity theme.
portal_css="themes/fragcity/css/fragcity/customer-portal.css"
grep -Fq 'customer-portal.css' themes/fragcity/css/app.css
for file in \
  themes/fragcity/views/dashboard.blade.php \
  themes/fragcity/views/services/index.blade.php \
  themes/fragcity/views/services/show.blade.php \
  themes/fragcity/views/invoices/index.blade.php \
  themes/fragcity/views/invoices/show.blade.php \
  themes/fragcity/views/tickets/index.blade.php \
  themes/fragcity/views/tickets/show.blade.php \
  themes/fragcity/views/tickets/create.blade.php \
  themes/fragcity/views/client/account/index.blade.php \
  themes/fragcity/views/client/account/security.blade.php \
  themes/fragcity/views/client/account/credits.blade.php \
  themes/fragcity/views/client/account/payment-methods.blade.php \
  themes/fragcity/views/client/account/notifications.blade.php \
  themes/fragcity/views/cart.blade.php \
  themes/fragcity/views/invoices/partials/payment-modal.blade.php \
  themes/fragcity/views/invoices/partials/payment-options.blade.php; do
  test -s "$file"
done
grep -Fq "\$wire.entangle('{{ \$name }}').live" themes/fragcity/views/components/form/configoption.blade.php
grep -Fq "transform:scaleX" themes/fragcity/views/components/form/configoption.blade.php
grep -Fq "transform-origin:right center" themes/fragcity/css/fragcity/storefront-product-pages.css
grep -Fq "right:28px !important" themes/fragcity/css/fragcity/storefront-product-pages.css
grep -Fq "width:calc(100% - 56px) !important" themes/fragcity/css/fragcity/storefront-product-pages.css
grep -Fq "box-sizing:border-box" themes/fragcity/css/fragcity/storefront-product-pages.css

grep -Fq "sensitiveConfigOptions." themes/fragcity/views/components/form/configoption.blade.php
grep -Fq 'sensitiveConfigOptions[$configOption->id]' themes/fragcity/views/products/checkout.blade.php
grep -Fq 'fc-checkout-option-card--tall' themes/fragcity/views/products/checkout.blade.php
grep -Fq 'body.fc-customer-app-shell .fc-invoice-detail-page .fc-invoice-paper' themes/fragcity/css/fragcity/customer-app.css
grep -Fq ".fc-invoice-detail-page .fc-invoice-paper" "$portal_css"
grep -Fq ".fc-modal-panel--payment" "$portal_css"
grep -Fq ".fc-ticket-create-page .EasyMDEContainer .CodeMirror" "$portal_css"
grep -Fq ".fc-service-upgrade-page .fc-upgrade-summary" "$portal_css"
grep -Fq '.fc-portal-page' "$portal_css"
grep -Fq '.fc-payment-modal' "$portal_css"
grep -Fq '.fc-payment-option--selected' "$portal_css"
grep -Fq 'variant="payment"' themes/fragcity/views/invoices/partials/payment-modal.blade.php
grep -Fq 'fc-payment-flow' themes/fragcity/views/invoices/partials/payment-options.blade.php
grep -Fq 'href="/" class="shock-nav-item active"' themes/fragcity/views/components/navigation/index.blade.php
grep -Fq 'href="/" class="mobile-nav-link active"' themes/fragcity/views/components/navigation/index.blade.php

echo "FragCity Paymenter validation passed."
