<?php

namespace Paymenter\Extensions\Others\FragCityAPI;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Classes\Price;
use App\Classes\Settings;
use App\Enums\InvoiceTransactionStatus;
use App\Helpers\ExtensionHelper;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

#[ExtensionMeta(
    name: 'FragCity API',
    description: 'Private signed server-to-server catalogue, wallet, CS2 checkout and service bridge for FragCity.',
    version: '1.9.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class FragCityAPI extends Extension
{
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'shared_secret',
                'label' => 'FragCity Bridge HMAC Secret',
                'type' => 'password',
                'description' => 'A private 32-byte random secret (64 hexadecimal characters). Must match integrations.paymenter_backend.shared_secret in FragCity runtime settings.',
                'required' => true,
                'encrypted' => true,
                'validation' => 'required|string|min:64|max:256',
            ],
            [
                'name' => 'allowed_ips',
                'label' => 'Allowed Source IPs',
                'type' => 'textarea',
                'description' => 'Optional comma- or newline-separated exact source IP allowlist. Leave empty only when the hosting provider cannot guarantee a stable egress IP; HMAC authentication remains mandatory.',
                'required' => false,
                'default' => '',
            ],
            [
                'name' => 'cs2_product_id',
                'label' => 'FragCity CS2 Product ID',
                'type' => 'number',
                'description' => 'Paymenter Product ID assigned to the FragCity CS2 server extension.',
                'required' => true,
                'validation' => 'required|integer|min:1',
            ],
            [
                'name' => 'cs2_plan_id_1_day',
                'label' => 'CS2 1-day Plan ID',
                'type' => 'number',
                'required' => true,
                'validation' => 'required|integer|min:1',
            ],
            [
                'name' => 'cs2_plan_id_7_days',
                'label' => 'CS2 7-day Plan ID',
                'type' => 'number',
                'required' => true,
                'validation' => 'required|integer|min:1',
            ],
            [
                'name' => 'cs2_plan_id_15_days',
                'label' => 'CS2 15-day Plan ID',
                'type' => 'number',
                'required' => true,
                'validation' => 'required|integer|min:1',
            ],
            [
                'name' => 'cs2_plan_id_30_days',
                'label' => 'CS2 30-day Plan ID',
                'type' => 'number',
                'required' => true,
                'validation' => 'required|integer|min:1',
            ],
            [
                'name' => 'cs2_currency_code',
                'label' => 'CS2 Paymenter Currency Code',
                'type' => 'text',
                'description' => 'Currency code used by the configured CS2 plans, for example the code already created in Paymenter for Toman or Rial.',
                'required' => true,
                'validation' => 'required|string|max:12|regex:/^[A-Za-z0-9_-]+$/',
            ],
        ];
    }

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/FragCityAPI/database/migrations');
    }

    public function upgraded($oldVersion = null): void
    {
        ExtensionHelper::runMigrations('extensions/Others/FragCityAPI/database/migrations');
    }

    public function boot(): void
    {
        require __DIR__ . '/routes.php';
    }

    public function ping(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        return response()->json([
            'success' => true,
            'service' => 'FragCityAPI',
            'version' => '1.9.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function syncUser(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_json_body',
            ], 400);
        }

        // The payload is decoded from the raw HMAC-signed body. Normalize
        // client names here so whitespace-only values cannot pass as names.
        $payload['username'] = is_string($payload['username'] ?? null)
            ? strtolower(trim($payload['username']))
            : $payload['username'] ?? null;
        $payload['first_name'] = is_string($payload['first_name'] ?? null)
            ? trim($payload['first_name'])
            : $payload['first_name'] ?? null;
        $payload['last_name'] = is_string($payload['last_name'] ?? null)
            ? trim($payload['last_name'])
            : $payload['last_name'] ?? null;

        $validator = Validator::make($payload, [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^09[0-9]{9}$/', 'max:15'],
            'first_name' => ['required', 'string', 'min:1', 'max:191'],
            'last_name' => ['required', 'string', 'min:1', 'max:191'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/^(?=.*\\p{L})(?=.*[0-9])[\\s\\S]+$/u',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $steamId = $data['steamid64'];
        $username = $data['username'];
        $email = mb_strtolower(trim($data['email']));
        $phone = trim($data['phone']);
        $firstName = trim($data['first_name']);
        $lastName = trim((string) $data['last_name']);
        $password = $data['password'];

        try {
            $result = DB::transaction(function () use (
                $steamId,
                $username,
                $email,
                $phone,
                $firstName,
                $lastName,
                $password
            ): array {
                $link = DB::table('fragcity_user_links')
                    ->where('steamid64', $steamId)
                    ->lockForUpdate()
                    ->first();

                $userByEmail = User::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                $created = false;

                if ($link) {
                    $user = User::query()->lockForUpdate()->find($link->user_id);
                    if (!$user) {
                        throw new RuntimeException('linked_paymenter_user_missing');
                    }

                    if ($userByEmail && (int) $userByEmail->id !== (int) $user->id) {
                        throw new RuntimeException('email_already_linked_to_another_paymenter_user');
                    }
                } elseif ($userByEmail) {
                    $existingLink = DB::table('fragcity_user_links')
                        ->where('user_id', $userByEmail->id)
                        ->lockForUpdate()
                        ->first();

                    if ($existingLink && (string) $existingLink->steamid64 !== $steamId) {
                        throw new RuntimeException('paymenter_user_already_linked_to_another_steam_account');
                    }

                    // Do not silently claim an existing Paymenter account based
                    // only on an email address. The supplied password must also
                    // prove control of that existing account.
                    if (!Hash::check($password, (string) $userByEmail->password)) {
                        throw new RuntimeException('email_already_in_use');
                    }

                    $user = $userByEmail;
                    DB::table('fragcity_user_links')->insert([
                        'user_id' => $user->id,
                        'steamid64' => $steamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $user = User::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName !== '' ? $lastName : null,
                        'email' => $email,
                        'password' => $password,
                    ]);

                    DB::table('fragcity_user_links')->insert([
                        'user_id' => $user->id,
                        'steamid64' => $steamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created = true;
                }

                // FragCity is the identity source of truth. A successful sync
                // intentionally refreshes the Paymenter password to the same
                // current password supplied during the authenticated FragCity
                // registration/login flow.
                $usernameTaken = User::query()
                    ->where('id', '<>', (int) $user->id)
                    ->whereHas('properties', function ($query) use ($username): void {
                        $query->where('key', 'fragcity_username')
                            ->where('value', $username);
                    })
                    ->exists();

                if ($usernameTaken) {
                    throw new RuntimeException('username_already_in_use');
                }

                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->email = $email;
                $user->password = $password;
                $user->save();

                $user->properties()->updateOrCreate(
                    ['key' => 'fragcity_username'],
                    ['name' => 'FragCity Username', 'value' => $username]
                );

                $user->properties()->updateOrCreate(
                    ['key' => 'fragcity_phone'],
                    ['name' => 'FragCity Phone', 'value' => $phone]
                );

                $user->properties()->updateOrCreate(
                    ['key' => 'fragcity_steamid64'],
                    ['name' => 'FragCity SteamID64', 'value' => $steamId]
                );

                return [
                    'user_id' => (int) $user->id,
                    'created' => $created,
                    'email' => (string) $user->email,
                ];
            }, 3);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (RuntimeException $e) {
            $safeErrors = [
                'username_already_in_use',
                'linked_paymenter_user_missing',
                'email_already_linked_to_another_paymenter_user',
                'paymenter_user_already_linked_to_another_steam_account',
                'email_already_in_use',
            ];

            if (in_array($e->getMessage(), $safeErrors, true)) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 409);
            }

            Log::error('FragCity user synchronization failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'account_sync_failed',
            ], 500);
        } catch (Throwable $e) {
            Log::error('FragCity user synchronization failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'account_sync_failed',
            ], 500);
        }
    }


    /**
     * Return the configured FragCity CS2 catalogue, including native Paymenter
     * plans, prices and product Config Options. This endpoint is server-to-server
     * only; the website must never expose the HMAC secret to browser JavaScript.
     */
    public function cs2Catalog(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $currencyCode = strtoupper(trim((string) $this->config('cs2_currency_code')));
        $productId = (int) $this->config('cs2_product_id');

        $planSettings = [
            1 => 'cs2_plan_id_1_day',
            7 => 'cs2_plan_id_7_days',
            15 => 'cs2_plan_id_15_days',
            30 => 'cs2_plan_id_30_days',
        ];

        if ($productId < 1 || $currencyCode === '') {
            return response()->json([
                'success' => false,
                'error' => 'cs2_checkout_not_configured',
            ], 503);
        }

        try {
            $product = Product::query()
                ->with([
                    'server',
                    'plans.prices.currency',
                    'configOptions.children.plans.prices.currency',
                ])
                ->find($productId);

            if (
                !$product
                || !(bool) $product->enabled
                || $product->server?->extension !== 'FragCityCS2'
            ) {
                throw new RuntimeException('cs2_product_invalid');
            }

            $plans = [];
            foreach ($planSettings as $days => $setting) {
                $planId = (int) $this->config($setting);
                $plan = $product->plans->firstWhere('id', $planId);
                $expected = match ($days) {
                    1 => [1, 'day'],
                    7 => [1, 'week'],
                    15 => [15, 'day'],
                    30 => [1, 'month'],
                };

                if (
                    !$plan
                    || in_array((string) $plan->type, ['free', 'one-time'], true)
                    || (int) $plan->billing_period !== $expected[0]
                    || (string) $plan->billing_unit !== $expected[1]
                ) {
                    throw new RuntimeException('cs2_plan_invalid');
                }

                $price = $plan->price($currencyCode);
                $plans[] = [
                    'id' => (int) $plan->id,
                    'duration_days' => (int) $days,
                    'name' => (string) $plan->name,
                    'billing_period' => (int) $plan->billing_period,
                    'billing_unit' => (string) $plan->billing_unit,
                    'available' => (bool) $price->available,
                    'price' => $price->available ? number_format((float) $price->price, 2, '.', '') : null,
                    'setup_fee' => $price->available ? number_format((float) ($price->setup_fee ?? 0), 2, '.', '') : null,
                    'currency_code' => $currencyCode,
                ];
            }

            $configOptions = [];
            foreach ($product->configOptions as $option) {
                $children = [];
                foreach ($option->children as $child) {
                    $childPrices = [];

                    foreach ($plans as $planData) {
                        $plan = $product->plans->firstWhere('id', $planData['id']);
                        if (!$plan) {
                            continue;
                        }

                        // Match Paymenter's native CartItem calculation: Config
                        // Option child plans are resolved by billing period/unit,
                        // not by reusing the parent Product Plan ID.
                        $childPrice = $child->price(
                            null,
                            (int) $plan->billing_period,
                            (string) $plan->billing_unit,
                            $currencyCode
                        );

                        $childPrices[(string) $planData['duration_days']] = [
                            'available' => (bool) $childPrice->available,
                            'price' => $childPrice->available
                                ? number_format((float) $childPrice->price, 2, '.', '')
                                : null,
                            'setup_fee' => $childPrice->available
                                ? number_format((float) ($childPrice->setup_fee ?? 0), 2, '.', '')
                                : null,
                        ];
                    }

                    $children[] = [
                        'id' => (int) $child->id,
                        'name' => (string) $child->name,
                        'env_variable' => (string) ($child->env_variable ?? ''),
                        'prices' => $childPrices,
                    ];
                }

                $configOptions[] = [
                    'id' => (int) $option->id,
                    'name' => (string) $option->name,
                    'description' => (string) ($option->description ?? ''),
                    'env_variable' => (string) ($option->env_variable ?? ''),
                    'type' => (string) $option->type,
                    'upgradable' => (bool) ($option->upgradable ?? false),
                    'children' => $children,
                ];
            }

            $checkoutFields = ExtensionHelper::getCheckoutConfig($product);
            if (!is_array($checkoutFields)) {
                throw new RuntimeException('cs2_checkout_manifest_unavailable');
            }

            $manifestPath = dirname(__DIR__, 2) . '/Servers/FragCityCS2/resources/cs2-egg-variables.json';
            if (!is_file($manifestPath)) {
                throw new RuntimeException('cs2_checkout_manifest_unavailable');
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)) {
                throw new RuntimeException('cs2_checkout_manifest_unavailable');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => (int) $product->id,
                        'name' => (string) $product->name,
                        'description' => (string) ($product->description ?? ''),
                        'stock' => $product->stock === null ? null : (int) $product->stock,
                        'per_user_limit' => (int) ($product->per_user_limit ?? 0),
                    ],
                    'currency_code' => $currencyCode,
                    'currency_unit' => 'rial',
                    'plans' => $plans,
                    'config_options' => $configOptions,
                    'checkout_fields' => $checkoutFields,
                    'game_mode_manager' => $manifest['game_mode_manager'] ?? [],
                    'catalog_version' => '1.9.0',
                ],
            ]);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'cs2_product_invalid', 'cs2_plan_invalid' => 422,
                'cs2_checkout_not_configured', 'cs2_checkout_manifest_unavailable' => 503,
                default => 500,
            };

            if ($status === 500) {
                Log::error('FragCity CS2 catalogue could not be built.', [
                    'product_id' => $productId,
                    'exception' => get_class($e),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'cs2_catalog_unavailable',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            Log::error('FragCity CS2 catalogue could not be built.', [
                'product_id' => $productId,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'cs2_catalog_unavailable',
            ], 500);
        }
    }

    /**
     * Resolve a FragCity Steam identity and return only its Rial wallet balance.
     * The SteamID is accepted only on this authenticated private bridge; it is
     * never accepted as proof of identity by the browser-facing website API.
     */
    public function walletBalance(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
            ], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];
        $currencyCode = strtoupper(trim((string) $this->config('cs2_currency_code')));

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'error' => 'fragcity_user_not_linked',
                ], 404);
            }

            $user = User::query()->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $credit = $user->credits()
                ->where('currency_code', $currencyCode)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'currency_code' => $currencyCode,
                    'currency_unit' => 'rial',
                    'balance' => number_format((float) ($credit?->amount ?? 0), 2, '.', ''),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('FragCity wallet balance lookup failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'wallet_balance_unavailable',
            ], 500);
        }
    }


    /**
     * Create and settle a Paymenter Credit invoice after FragCity has verified
     * the ZarinPal receipt server-to-server. Paymenter then applies the credit
     * through its native ProcessPaidInvoiceService after the DB commit.
     */
    public function createWalletTopup(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'topup_ref' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/i'],
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'payment.provider' => ['required', 'in:zarinpal'],
            'payment.authority' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payment.ref_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payment.amount_toman' => ['required', 'integer', 'min:1'],
            'payment.amount_rial' => ['required', 'integer', 'min:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $topupRef = strtolower((string) $data['topup_ref']);
        $steamId = (string) $data['steamid64'];
        $authority = (string) $data['payment']['authority'];
        $zarinpalRef = (string) $data['payment']['ref_id'];
        $amountToman = (int) $data['payment']['amount_toman'];
        $amountRial = (int) $data['payment']['amount_rial'];

        if (
            $amountToman > intdiv(PHP_INT_MAX, 10)
            || $amountRial !== $amountToman * 10
        ) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_order_payload',
            ], 422);
        }

        $currencyCode = strtoupper(trim((string) $this->config('cs2_currency_code')));
        if ($currencyCode === '') {
            return response()->json([
                'success' => false,
                'error' => 'cs2_checkout_not_configured',
            ], 503);
        }

        try {
            $creditsEnabled = filter_var(
                (string) (Settings::getSetting('credits_enabled')->value ?? false),
                FILTER_VALIDATE_BOOLEAN
            );
            $minimumDeposit = (float) (Settings::getSetting('credits_minimum_deposit')->value ?? 0);
            $maximumDeposit = (float) (Settings::getSetting('credits_maximum_deposit')->value ?? 0);
            $maximumCredit = (float) (Settings::getSetting('credits_maximum_credit')->value ?? 0);
        } catch (Throwable $e) {
            Log::error('FragCity wallet top-up settings could not be loaded.', [
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_not_configured',
            ], 503);
        }

        if (
            !$creditsEnabled
            || $minimumDeposit <= 0
            || $maximumDeposit < $minimumDeposit
            || $maximumCredit <= 0
        ) {
            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_not_configured',
            ], 503);
        }

        if ($amountRial < $minimumDeposit || $amountRial > $maximumDeposit) {
            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_amount_invalid',
            ], 422);
        }

        $payloadHash = hash(
            'sha256',
            json_encode([
                'topup_ref' => $topupRef,
                'steamid64' => $steamId,
                'payment' => [
                    'provider' => 'zarinpal',
                    'authority' => $authority,
                    'ref_id' => $zarinpalRef,
                    'amount_toman' => $amountToman,
                    'amount_rial' => $amountRial,
                ],
                'currency_code' => $currencyCode,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        try {
            $result = DB::transaction(function () use (
                $topupRef,
                $steamId,
                $authority,
                $zarinpalRef,
                $amountToman,
                $amountRial,
                $currencyCode,
                $payloadHash,
                $maximumCredit
            ): array {
                $existing = DB::table('fragcity_wallet_topups')
                    ->where('topup_ref', $topupRef)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (!hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw new RuntimeException('wallet_topup_reference_conflict');
                    }

                    if ((string) $existing->status !== 'accepted') {
                        throw new RuntimeException('wallet_topup_processing_incomplete');
                    }

                    return [
                        'invoice_id' => (int) $existing->paymenter_invoice_id,
                        'user_id' => (int) $existing->paymenter_user_id,
                        'replayed' => true,
                    ];
                }

                $usedReceipt = DB::table('fragcity_payment_receipts')
                    ->where(function ($query) use ($authority, $zarinpalRef): void {
                        $query->where('authority', $authority)
                            ->orWhere('ref_id', $zarinpalRef);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($usedReceipt) {
                    throw new RuntimeException('zarinpal_payment_already_used');
                }

                $link = DB::table('fragcity_user_links')
                    ->where('steamid64', $steamId)
                    ->lockForUpdate()
                    ->first();

                if (!$link) {
                    throw new RuntimeException('fragcity_user_not_linked');
                }

                $user = User::query()
                    ->lockForUpdate()
                    ->find((int) $link->user_id);

                if (!$user) {
                    throw new RuntimeException('linked_paymenter_user_missing');
                }

                // Match Paymenter's native deposit flow: serialize the user's
                // invoices and credit row, reject a second outstanding deposit,
                // and enforce its configured maximum wallet balance.
                $user->invoices()->lockForUpdate()->get();

                $pendingCreditInvoice = $user->invoices()
                    ->where('status', Invoice::STATUS_PENDING)
                    ->whereHas('items', function ($query): void {
                        $query->where('reference_type', Credit::class);
                    })
                    ->exists();

                if ($pendingCreditInvoice) {
                    throw new RuntimeException('wallet_topup_pending_invoice_exists');
                }

                $credit = $user->credits()
                    ->where('currency_code', $currencyCode)
                    ->lockForUpdate()
                    ->first();

                $currentBalance = (float) ($credit?->amount ?? 0);
                if (($currentBalance + $amountRial) > $maximumCredit) {
                    throw new RuntimeException('wallet_maximum_credit_exceeded');
                }

                $now = now();
                $invoice = new Invoice([
                    'user_id' => (int) $user->id,
                    'due_at' => $now,
                    'currency_code' => $currencyCode,
                ]);
                $invoice->send_create_email = false;
                $invoice->save();

                // Paymenter's native paid-invoice processor recognizes Credit
                // items by reference_type and increments/creates the Credit row.
                $invoice->items()->create([
                    'description' => 'FragCity Wallet Deposit',
                    'quantity' => 1,
                    'price' => number_format($amountRial, 2, '.', ''),
                    'reference_type' => Credit::class,
                ]);

                ExtensionHelper::addPayment(
                    $invoice,
                    null,
                    $amountRial,
                    null,
                    'zarinpal:' . $zarinpalRef,
                    InvoiceTransactionStatus::Succeeded
                );

                DB::table('fragcity_wallet_topups')->insert([
                    'topup_ref' => $topupRef,
                    'steamid64' => $steamId,
                    'amount_toman' => $amountToman,
                    'amount_rial' => $amountRial,
                    'currency_code' => $currencyCode,
                    'payload_hash' => $payloadHash,
                    'paymenter_user_id' => (int) $user->id,
                    'paymenter_invoice_id' => (int) $invoice->id,
                    'status' => 'accepted',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('fragcity_payment_receipts')->insert([
                    'provider' => 'zarinpal',
                    'authority' => $authority,
                    'ref_id' => $zarinpalRef,
                    'reference' => $topupRef,
                    'purpose' => 'wallet_topup',
                    'steamid64' => $steamId,
                    'amount_toman' => $amountToman,
                    'amount_rial' => $amountRial,
                    'currency_code' => $currencyCode,
                    'paymenter_invoice_id' => (int) $invoice->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // The observer processes the Credit invoice item after this
                // transaction commits; it is Paymenter's native wallet credit.
                $invoice->status = Invoice::STATUS_PAID;
                $invoice->save();

                return [
                    'invoice_id' => (int) $invoice->id,
                    'user_id' => (int) $user->id,
                    'replayed' => false,
                ];
            }, 3);

            $balance = (float) (User::query()
                ->find((int) $result['user_id'])
                ?->credits()
                ->where('currency_code', $currencyCode)
                ->value('amount') ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'topup_ref' => $topupRef,
                    'invoice_id' => (int) $result['invoice_id'],
                    'status' => 'accepted',
                    'replayed' => (bool) $result['replayed'],
                    'currency_code' => $currencyCode,
                    'currency_unit' => 'rial',
                    'wallet_balance' => number_format($balance, 2, '.', ''),
                ],
            ], !empty($result['replayed']) ? 200 : 201);
        } catch (QueryException $e) {
            $existing = DB::table('fragcity_wallet_topups')
                ->where('topup_ref', $topupRef)
                ->first();

            if (
                $existing
                && hash_equals((string) $existing->payload_hash, $payloadHash)
                && (string) $existing->status === 'accepted'
            ) {
                $balance = (float) (User::query()
                    ->find((int) $existing->paymenter_user_id)
                    ?->credits()
                    ->where('currency_code', $currencyCode)
                    ->value('amount') ?? 0);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'topup_ref' => $topupRef,
                        'invoice_id' => (int) $existing->paymenter_invoice_id,
                        'status' => 'accepted',
                        'replayed' => true,
                        'currency_code' => $currencyCode,
                        'currency_unit' => 'rial',
                        'wallet_balance' => number_format($balance, 2, '.', ''),
                    ],
                ]);
            }

            $paymentUsed = DB::table('fragcity_payment_receipts')
                ->where(function ($query) use ($authority, $zarinpalRef): void {
                    $query->where('authority', $authority)
                        ->orWhere('ref_id', $zarinpalRef);
                })
                ->exists();

            if ($paymentUsed) {
                return response()->json([
                    'success' => false,
                    'error' => 'zarinpal_payment_already_used',
                ], 409);
            }

            Log::error('FragCity wallet top-up persistence failed.', [
                'topup_ref' => $topupRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'sqlstate' => (string) $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_creation_failed',
            ], 500);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'wallet_topup_reference_conflict', 'zarinpal_payment_already_used' => 409,
                'wallet_topup_pending_invoice_exists', 'wallet_maximum_credit_exceeded', 'wallet_topup_amount_invalid' => 422,
                'wallet_topup_processing_incomplete' => 503,
                default => 500,
            };

            if ($status === 500) {
                Log::error('FragCity wallet top-up failed.', [
                    'topup_ref' => $topupRef,
                    'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                    'exception' => get_class($e),
                    'reason' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'wallet_topup_creation_failed',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            Log::error('FragCity wallet top-up failed.', [
                'topup_ref' => $topupRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_creation_failed',
            ], 500);
        }
    }

    /**
     * Accept one paid CS2 checkout from the authenticated FragCity website.
     *
     * FragCity verifies the ZarinPal callback before calling this endpoint.
     * Paymenter records the paid invoice and pending service, then its native
     * InvoiceObserver -> ProcessPaidInvoiceService -> CreateJob flow invokes
     * FragCityCS2 and the inherited Pterodactyl provisioner.
     *
     * This method never calls Pterodactyl directly and never trusts browser data.
     */
    public function createCs2Order(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'order_ref' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/i'],
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'payment.provider' => ['required', 'in:zarinpal'],
            'payment.authority' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payment.ref_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payment.amount_toman' => ['required', 'integer', 'min:1'],
            'payment.amount_rial' => ['required', 'integer', 'min:10'],
            'duration_days' => ['required', 'integer', 'in:1,7,15,30'],
            'server_name' => ['required', 'string', 'min:1', 'max:64', 'regex:/^[A-Za-z0-9 ]+$/'],
            'resources.memory_mb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'resources.disk_mb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'resources.cpu_percent' => ['required', 'integer', 'min:1', 'max:6400'],
            'feature_limits' => ['sometimes', 'array'],
            'feature_limits.databases' => ['sometimes', 'integer', 'in:0,1,2,3,5,10'],
            'feature_limits.additional_allocations' => ['sometimes', 'integer', 'in:0,1,2,3,5'],
            'feature_limits.backups' => ['sometimes', 'integer', 'in:0,1,2,3,5,10'],
            'egg' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $orderRef = strtolower((string) $data['order_ref']);
        $steamId = (string) $data['steamid64'];
        $authority = (string) $data['payment']['authority'];
        $zarinpalRef = (string) $data['payment']['ref_id'];
        $amountToman = (int) $data['payment']['amount_toman'];
        $amountRial = (int) $data['payment']['amount_rial'];
        $durationDays = (int) $data['duration_days'];
        $serverName = trim((string) $data['server_name']);
        $memoryMb = (int) $data['resources']['memory_mb'];
        $diskMb = (int) $data['resources']['disk_mb'];
        $cpuPercent = (int) $data['resources']['cpu_percent'];
        $featureLimitInput = is_array($data['feature_limits'] ?? null) ? $data['feature_limits'] : [];
        $featureLimits = [
            'databases' => (int) ($featureLimitInput['databases'] ?? 0),
            'additional_allocations' => (int) ($featureLimitInput['additional_allocations'] ?? 0),
            'backups' => (int) ($featureLimitInput['backups'] ?? 0),
        ];
        $eggValues = is_array($data['egg']) ? $data['egg'] : [];

        if (
            $amountToman > intdiv(PHP_INT_MAX, 10)
            || $amountRial !== $amountToman * 10
            || $memoryMb < 1
            || $diskMb < 1
            || $cpuPercent < 1
        ) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_order_payload',
            ], 422);
        }

        // Bot Guard remains a paid add-on and is still blocked until its
        // surcharge is included in the authoritative FragCity quote.
        // Tickrate 64 and 128 are both free and are priced through zero-cost
        // native Paymenter Config Option child rows.
        if ((string) ($eggValues['ENABLE_GUARD'] ?? '0') !== '0') {
            return response()->json([
                'success' => false,
                'error' => 'paid_option_pricing_not_configured',
            ], 422);
        }

        $currencyCode = strtoupper(trim((string) $this->config('cs2_currency_code')));
        // Paymenter is the Rial system of record. FragCity receives and quotes Toman,
        // but every Paymenter monetary record must be stored in Rial.
        $currencyUnit = 'rial';
        $productId = (int) $this->config('cs2_product_id');
        $planSetting = [
            1 => 'cs2_plan_id_1_day',
            7 => 'cs2_plan_id_7_days',
            15 => 'cs2_plan_id_15_days',
            30 => 'cs2_plan_id_30_days',
        ];
        $planId = (int) $this->config($planSetting[$durationDays] ?? '');

        if (
            $productId < 1
            || $planId < 1
            || $currencyCode === ''
        ) {
            return response()->json([
                'success' => false,
                'error' => 'cs2_checkout_not_configured',
            ], 503);
        }

        $paymenterAmount = $amountRial;
        $eggHashValues = $eggValues;
        ksort($eggHashValues, SORT_STRING);
        $canonicalPayload = [
            'order_ref' => $orderRef,
            'steamid64' => $steamId,
            'payment' => [
                'provider' => 'zarinpal',
                'authority' => $authority,
                'ref_id' => $zarinpalRef,
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
            ],
            'duration_days' => $durationDays,
            'server_name' => $serverName,
            'resources' => [
                'memory_mb' => $memoryMb,
                'disk_mb' => $diskMb,
                'cpu_percent' => $cpuPercent,
            ],
            'feature_limits' => $featureLimits,
            'egg' => $eggHashValues,
            'currency_code' => $currencyCode,
            'currency_unit' => $currencyUnit,
            'product_id' => $productId,
            'plan_id' => $planId,
        ];
        $payloadHash = hash(
            'sha256',
            json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        try {
            $result = DB::transaction(function () use (
                $orderRef,
                $steamId,
                $authority,
                $zarinpalRef,
                $amountToman,
                $amountRial,
                $durationDays,
                $serverName,
                $memoryMb,
                $diskMb,
                $cpuPercent,
                $featureLimits,
                $eggValues,
                $payloadHash,
                $currencyCode,
                $currencyUnit,
                $productId,
                $planId,
                $paymenterAmount
            ): array {
                $existing = DB::table('fragcity_server_orders')
                    ->where('order_ref', $orderRef)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (!hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw new RuntimeException('order_reference_conflict');
                    }

                    if ((string) $existing->status !== 'accepted') {
                        throw new RuntimeException('order_processing_incomplete');
                    }

                    return [
                        'order_id' => (int) $existing->paymenter_order_id,
                        'invoice_id' => (int) $existing->paymenter_invoice_id,
                        'service_id' => (int) $existing->paymenter_service_id,
                        'status' => 'accepted',
                        'replayed' => true,
                    ];
                }

                $usedPayment = DB::table('fragcity_payment_receipts')
                    ->where(function ($query) use ($authority, $zarinpalRef): void {
                        $query->where('authority', $authority)
                            ->orWhere('ref_id', $zarinpalRef);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($usedPayment) {
                    throw new RuntimeException('zarinpal_payment_already_used');
                }

                $userLink = DB::table('fragcity_user_links')
                    ->where('steamid64', $steamId)
                    ->lockForUpdate()
                    ->first();

                if (!$userLink) {
                    throw new RuntimeException('fragcity_user_not_linked');
                }

                $user = User::query()
                    ->with('properties')
                    ->lockForUpdate()
                    ->find((int) $userLink->user_id);

                if (!$user) {
                    throw new RuntimeException('linked_paymenter_user_missing');
                }

                $product = Product::query()
                    ->with([
                        'server',
                        'plans.prices',
                        'configOptions.children.plans.prices',
                    ])
                    ->lockForUpdate()
                    ->find($productId);

                if (
                    !$product
                    || !(bool) $product->enabled
                    || $product->server?->extension !== 'FragCityCS2'
                ) {
                    throw new RuntimeException('cs2_product_invalid');
                }

                if ($product->stock !== null && (int) $product->stock < 1) {
                    throw new RuntimeException('cs2_product_out_of_stock');
                }

                $perUserLimit = (int) ($product->per_user_limit ?? 0);
                if (
                    $perUserLimit > 0
                    && $user->services()->where('product_id', $product->id)->count() >= $perUserLimit
                ) {
                    throw new RuntimeException('cs2_product_user_limit');
                }

                $plan = $product->plans->firstWhere('id', $planId);
                $expectedPlan = match ($durationDays) {
                    1 => [1, 'day'],
                    7 => [1, 'week'],
                    15 => [15, 'day'],
                    30 => [1, 'month'],
                };

                if (
                    !$plan
                    || (string) $plan->type === 'free'
                    || (string) $plan->type === 'one-time'
                    || (int) $plan->billing_period !== $expectedPlan[0]
                    || (string) $plan->billing_unit !== $expectedPlan[1]
                    || !$plan->prices->contains('currency_code', $currencyCode)
                ) {
                    throw new RuntimeException('cs2_plan_invalid');
                }

                // Persist RAM, Storage, CPU, Max Players, Tickrate and Pterodactyl
                // feature limits as native Paymenter ServiceConfig selections.
                // ExtensionHelper merges their child values into the provisioning
                // property map; FragCityCS2 maps resources, Egg values and limits.
                $configOptionTargets = [
                    'memory_mb' => [
                        'aliases' => ['memory', 'memorymb', 'ram', 'rammb', 'memorylimit', 'ramlimit'],
                        'value' => (string) $memoryMb,
                    ],
                    'disk_mb' => [
                        'aliases' => ['disk', 'diskmb', 'storage', 'storagemb', 'ssd', 'ssdmb', 'disklimit', 'storagelimit'],
                        'value' => (string) $diskMb,
                    ],
                    'cpu_percent' => [
                        'aliases' => ['cpu', 'cpupercent', 'cpulimit'],
                        'value' => (string) $cpuPercent,
                    ],
                    'max_players' => [
                        'aliases' => ['srcdsmaxplayers'],
                        'value' => (string) ($eggValues['SRCDS_MAXPLAYERS'] ?? ''),
                        'allowed_values' => array_map('strval', range(5, 64)),
                    ],
                    'tickrate' => [
                        'aliases' => ['tickrate'],
                        'value' => (string) ($eggValues['TICKRATE'] ?? '64'),
                        // Both Tickrate values are currently free and supported by the Egg startup argument.
                        'allowed_values' => ['64', '128'],
                    ],
                    'databases' => [
                        'aliases' => ['databases'],
                        'value' => (string) $featureLimits['databases'],
                        'allowed_values' => ['0', '1', '2', '3', '5', '10'],
                    ],
                    'additional_allocations' => [
                        'aliases' => ['additionalallocations'],
                        'value' => (string) $featureLimits['additional_allocations'],
                        'allowed_values' => ['0', '1', '2', '3', '5'],
                    ],
                    'backups' => [
                        'aliases' => ['backups'],
                        'value' => (string) $featureLimits['backups'],
                        'allowed_values' => ['0', '1', '2', '3', '5', '10'],
                    ],
                ];
                $normalizeConfigOptionName = static function ($value): string {
                    return strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim((string) $value)));
                };
                $configOptionSelections = [];
                $usedConfigOptionIds = [];

                foreach ($configOptionTargets as $resourceKey => $target) {
                    if (
                        isset($target['allowed_values'])
                        && !in_array($target['value'], $target['allowed_values'], true)
                    ) {
                        throw new RuntimeException('cs2_config_option_mapping_invalid');
                    }

                    // First identify the exact parent Config Option by its own
                    // env_variable/name. Then resolve the numeric child value
                    // inside that option only; unrelated options may have
                    // children with the same numeric value.
                    $matchingOptions = collect($product->configOptions)->filter(
                        function ($configOption) use ($target, $normalizeConfigOptionName) {
                            $identifiers = array_map(
                                $normalizeConfigOptionName,
                                [
                                    $configOption->env_variable ?? '',
                                    $configOption->name ?? '',
                                ]
                            );

                            return array_intersect($target['aliases'], $identifiers) !== [];
                        }
                    );

                    if ($matchingOptions->count() !== 1) {
                        throw new RuntimeException('cs2_config_option_mapping_invalid');
                    }

                    $configOption = $matchingOptions->first();
                    $matchingValues = $configOption->children->filter(
                        fn ($configValue) => trim((string) $configValue->env_variable) === $target['value']
                    );

                    if ($matchingValues->count() !== 1) {
                        throw new RuntimeException('cs2_config_option_mapping_invalid');
                    }

                    $configValue = $matchingValues->first();
                    $configOptionId = (int) $configOption->id;

                    // Text and number options are stored as free-form properties by
                    // Paymenter checkout, not as ServiceConfig value relations.
                    if (
                        (string) $configOption->type !== 'select'
                        || (bool) ($configOption->upgradable ?? false)
                        || in_array($configOptionId, $usedConfigOptionIds, true)
                    ) {
                        throw new RuntimeException('cs2_config_option_mapping_invalid');
                    }

                    $configPrice = $configValue->price(
                        null,
                        (int) $plan->billing_period,
                        (string) $plan->billing_unit,
                        $currencyCode
                    );

                    if (!$configPrice->available) {
                        throw new RuntimeException('cs2_config_option_price_missing');
                    }

                    // The five-slot base, both Tickrate choices and the three
                    // feature-limit options are included for now. Until the
                    // storefront quote prices these features, reject any charge.
                    $mustBeFree =
                        ($resourceKey === 'max_players' && $target['value'] === '5')
                        || in_array($resourceKey, [
                            'tickrate',
                            'databases',
                            'additional_allocations',
                            'backups',
                        ], true);

                    if (
                        $mustBeFree
                        && (
                            (float) $configPrice->price !== 0.0
                            || (float) ($configPrice->setup_fee ?? 0) !== 0.0
                        )
                    ) {
                        throw new RuntimeException('cs2_config_option_price_missing');
                    }

                    $configOptionSelections[] = [
                        'config_option_id' => $configOptionId,
                        'config_value_id' => (int) $configValue->id,
                    ];
                    $usedConfigOptionIds[] = $configOptionId;
                }

                $cs2ManifestPath = dirname(__DIR__, 2) . '/Servers/FragCityCS2/resources/cs2-egg-variables.json';
                if (!is_file($cs2ManifestPath)) {
                    throw new RuntimeException('cs2_checkout_manifest_unavailable');
                }

                try {
                    $cs2Manifest = json_decode(
                        (string) file_get_contents($cs2ManifestPath),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (Throwable) {
                    throw new RuntimeException('cs2_checkout_manifest_unavailable');
                }

                $checkoutFields = ExtensionHelper::getCheckoutConfig($product);
                if (!is_array($checkoutFields) || $checkoutFields === []) {
                    throw new RuntimeException('cs2_checkout_manifest_unavailable');
                }

                $allowedEggKeys = [];
                $eggRules = [];
                $normalizedEggValues = [];

                foreach ($checkoutFields as $field) {
                    $name = (string) ($field['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $allowedEggKeys[] = $name;
                    $value = $eggValues[$name] ?? $field['default'] ?? null;
                    $normalizedEggValues[$name] = $value;

                    $rules = [];
                    if (!empty($field['required'])) {
                        $rules[] = 'required';
                    } else {
                        $rules[] = 'nullable';
                    }

                    $fieldRules = $field['validation'] ?? [];
                    if (is_string($fieldRules)) {
                        $fieldRules = array_filter(explode('|', $fieldRules));
                    }
                    if (is_array($fieldRules)) {
                        $rules = array_merge($rules, $fieldRules);
                    }

                    if (in_array((string) ($field['type'] ?? ''), ['select', 'radio'], true)) {
                        $options = $field['options'] ?? [];
                        if (is_array($options) && array_is_list($options) && isset($options[0]) && is_array($options[0])) {
                            $optionValues = array_map(
                                static fn ($option): string => (string) ($option['value'] ?? ''),
                                $options
                            );
                        } else {
                            // FragCityCS2 adapts manifest select records to Paymenter's
                            // associative [egg_value => label] Checkout contract.
                            $optionValues = is_array($options)
                                ? array_map('strval', array_keys($options))
                                : [];
                        }
                        if ($optionValues !== []) {
                            $rules[] = 'in:' . implode(',', array_map(
                                static fn (string $option): string => str_replace(',', '\\,', $option),
                                $optionValues
                            ));
                        }
                    }

                    $eggRules[$name] = array_values(array_unique($rules));
                }

                $nativeConfigOptionFields = $cs2Manifest['paymenter']['config_options'] ?? [];
                if (!is_array($nativeConfigOptionFields) || count($nativeConfigOptionFields) !== 5) {
                    throw new RuntimeException('cs2_checkout_manifest_unavailable');
                }

                foreach ($nativeConfigOptionFields as $field) {
                    $fieldTarget = (string) ($field['target'] ?? 'egg');
                    $envVariable = trim((string) ($field['env_variable'] ?? ''));

                    if ($fieldTarget === 'feature_limit') {
                        $optionValues = array_map(
                            static fn ($option): string => (string) ($option['value'] ?? ''),
                            is_array($field['options'] ?? null) ? $field['options'] : []
                        );
                        if (
                            $envVariable === ''
                            || !array_key_exists($envVariable, $featureLimits)
                            || in_array($envVariable, $allowedEggKeys, true)
                            || !in_array((string) $featureLimits[$envVariable], $optionValues, true)
                        ) {
                            throw new RuntimeException('cs2_checkout_manifest_unavailable');
                        }

                        // These selections are Pterodactyl feature limits, not Egg variables.
                        // They are persisted exclusively as ServiceConfig rows.
                        continue;
                    }

                    if ($fieldTarget !== 'egg') {
                        throw new RuntimeException('cs2_checkout_manifest_unavailable');
                    }

                    $name = strtoupper($envVariable);
                    if ($name === '' || in_array($name, $allowedEggKeys, true)) {
                        throw new RuntimeException('cs2_checkout_manifest_unavailable');
                    }

                    $allowedEggKeys[] = $name;
                    $value = $eggValues[$name] ?? $field['default'] ?? null;
                    $normalizedEggValues[$name] = $value;

                    $rules = [];
                    if (!empty($field['required'])) {
                        $rules[] = 'required';
                    } else {
                        $rules[] = 'nullable';
                    }

                    $fieldRules = $field['validation'] ?? [];
                    if (is_string($fieldRules)) {
                        $fieldRules = array_filter(explode('|', $fieldRules));
                    }
                    if (is_array($fieldRules)) {
                        $rules = array_merge($rules, $fieldRules);
                    }

                    $optionValues = array_map(
                        static fn ($option): string => (string) ($option['value'] ?? ''),
                        is_array($field['options'] ?? null) ? $field['options'] : []
                    );
                    if ($optionValues === [] || in_array('', $optionValues, true)) {
                        throw new RuntimeException('cs2_checkout_manifest_unavailable');
                    }

                    $rules[] = 'in:' . implode(',', array_map(
                        static fn (string $option): string => str_replace(',', '\\,', $option),
                        $optionValues
                    ));

                    $eggRules[$name] = array_values(array_unique($rules));
                }

                if (array_diff(array_keys($eggValues), $allowedEggKeys) !== []) {
                    throw new RuntimeException('invalid_egg_variable');
                }

                $eggValidator = Validator::make($normalizedEggValues, $eggRules);
                if ($eggValidator->fails()) {
                    throw new RuntimeException('egg_validation_failed');
                }

                $cfgOptions = $cs2Manifest['game_mode_manager']['cfg_options'] ?? [];
                $selectedCfg = null;
                foreach (is_array($cfgOptions) ? $cfgOptions : [] as $cfgOption) {
                    if ((string) ($cfgOption['config'] ?? '') === (string) $normalizedEggValues['EXEC']) {
                        $selectedCfg = $cfgOption;
                        break;
                    }
                }

                $compatibleMapGroups = $selectedCfg['mapGroups'] ?? [];
                if (
                    !is_array($compatibleMapGroups)
                    || !in_array((string) $normalizedEggValues['MAPGROUP'], $compatibleMapGroups, true)
                ) {
                    throw new RuntimeException('egg_combination_invalid');
                }

                $normalizedEggValues['GUARD_BAN_MINUTES'] = (string) (
                    $normalizedEggValues['GUARD_BAN_MINUTES'] ?? 60
                );

                // Recalculate the first invoice total from Paymenter's authoritative Plan
                // and Config Option prices. Never accept the browser/website quote as
                // the source of truth. This mirrors Paymenter CartItem: recurring
                // prices + setup fees, with the customer's applicable tax policy.
                $planPrice = $plan->price($currencyCode);
                if (!$planPrice->available) {
                    throw new RuntimeException('cs2_plan_price_missing');
                }

                $recurringSubtotal = (float) $planPrice->price;
                $setupFeeTotal = (float) ($planPrice->setup_fee ?? 0);

                foreach ($configOptionSelections as $selection) {
                    $selectedParent = $product->configOptions->firstWhere(
                        'id',
                        (int) $selection['config_option_id']
                    );
                    $selectedValue = $selectedParent?->children->firstWhere(
                        'id',
                        (int) $selection['config_value_id']
                    );

                    if (!$selectedParent || !$selectedValue) {
                        throw new RuntimeException('cs2_config_option_mapping_invalid');
                    }

                    $selectedPrice = $selectedValue->price(
                        null,
                        (int) $plan->billing_period,
                        (string) $plan->billing_unit,
                        $currencyCode
                    );

                    if (!$selectedPrice->available) {
                        throw new RuntimeException('cs2_config_option_price_missing');
                    }

                    $recurringSubtotal += (float) $selectedPrice->price;
                    $setupFeeTotal += (float) ($selectedPrice->setup_fee ?? 0);
                }

                $customerTax = Settings::tax($user);
                $invoicePrice = new Price(
                    [
                        'price' => $recurringSubtotal,
                        'setup_fee' => $setupFeeTotal,
                        'currency' => $planPrice->currency,
                    ],
                    apply_exclusive_tax: true,
                    tax: $customerTax
                );
                $serviceRecurringPrice = new Price(
                    [
                        'price' => $recurringSubtotal,
                        'currency' => $planPrice->currency,
                    ],
                    apply_exclusive_tax: true,
                    tax: $customerTax
                );

                $computedInvoiceTotal = round((float) $invoicePrice->total, 2);
                $computedServicePrice = round((float) $serviceRecurringPrice->price, 2);
                $wholeRialTotal = (int) round($computedInvoiceTotal);

                // ZarinPal's Toman amount must map exactly to Paymenter's Rial
                // total. We reject fractional Rial or a remainder that cannot be
                // represented as whole Toman rather than silently rounding a charge.
                if (
                    abs($computedInvoiceTotal - $wholeRialTotal) > 0.00001
                    || ($wholeRialTotal % 10) !== 0
                    || $amountRial !== $wholeRialTotal
                    || $amountToman !== intdiv($wholeRialTotal, 10)
                ) {
                    throw new RuntimeException('cs2_quote_amount_mismatch');
                }

                $paymenterAmount = $computedInvoiceTotal;
                $serviceRecurringAmount = $computedServicePrice;

                $now = now();
                $order = new Order([
                    'user_id' => (int) $user->id,
                    'currency_code' => $currencyCode,
                ]);
                $order->send_create_email = false;
                $order->save();

                $invoice = new Invoice([
                    'user_id' => (int) $user->id,
                    'due_at' => $now,
                    'currency_code' => $currencyCode,
                ]);
                $invoice->send_create_email = false;
                $invoice->save();

                $service = $order->services()->create([
                    'user_id' => (int) $user->id,
                    'currency_code' => $currencyCode,
                    'product_id' => (int) $product->id,
                    'plan_id' => (int) $plan->id,
                    'price' => number_format($serviceRecurringAmount, 2, '.', ''),
                    'quantity' => 1,
                    'status' => Service::STATUS_PENDING,
                ]);

                // Native Config Option selections are stored only as ServiceConfig
                // rows. ExtensionHelper restores their exact Pterodactyl/Egg keys
                // when Paymenter invokes FragCityCS2 for provisioning.
                unset($normalizedEggValues['SRCDS_MAXPLAYERS'], $normalizedEggValues['TICKRATE']);

                $serviceProperties = [
                    'servername' => $serverName,
                ] + $normalizedEggValues;

                foreach ($serviceProperties as $key => $value) {
                    $service->properties()->updateOrCreate(
                        ['key' => (string) $key],
                        ['value' => (string) $value]
                    );
                }

                foreach ($configOptionSelections as $configSelection) {
                    $service->configs()->create($configSelection);
                }

                $invoice->items()->create([
                    'reference_id' => (int) $service->id,
                    'reference_type' => Service::class,
                    'price' => number_format($paymenterAmount, 2, '.', ''),
                    'quantity' => 1,
                    'description' => 'FragCity CS2 Server — ' . $serverName . ' (' . $durationDays . ' days)',
                ]);

                ExtensionHelper::addPayment(
                    $invoice,
                    null,
                    $paymenterAmount,
                    null,
                    'zarinpal:' . $zarinpalRef,
                    InvoiceTransactionStatus::Succeeded
                );

                if ($product->stock !== null) {
                    $product->stock = max(0, (int) $product->stock - 1);
                    $product->save();
                }

                $invoice->status = Invoice::STATUS_PAID;
                $invoice->save();

                DB::table('fragcity_server_orders')->insert([
                    'order_ref' => $orderRef,
                    'steamid64' => $steamId,
                    'zarinpal_authority' => $authority,
                    'zarinpal_ref_id' => $zarinpalRef,
                    'payment_method' => 'zarinpal',
                    'amount_toman' => $amountToman,
                    'amount_rial' => $amountRial,
                    'currency_code' => $currencyCode,
                    'currency_unit' => $currencyUnit,
                    'duration_days' => $durationDays,
                    'payload_hash' => $payloadHash,
                    'paymenter_user_id' => (int) $user->id,
                    'paymenter_order_id' => (int) $order->id,
                    'paymenter_invoice_id' => (int) $invoice->id,
                    'paymenter_service_id' => (int) $service->id,
                    'status' => 'accepted',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('fragcity_payment_receipts')->insert([
                    'provider' => 'zarinpal',
                    'authority' => $authority,
                    'ref_id' => $zarinpalRef,
                    'reference' => $orderRef,
                    'purpose' => 'cs2_server',
                    'steamid64' => $steamId,
                    'amount_toman' => $amountToman,
                    'amount_rial' => $amountRial,
                    'currency_code' => $currencyCode,
                    'paymenter_invoice_id' => (int) $invoice->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [
                    'order_id' => (int) $order->id,
                    'invoice_id' => (int) $invoice->id,
                    'service_id' => (int) $service->id,
                    'status' => 'accepted',
                    'replayed' => false,
                ];
            }, 3);

            return response()->json([
                'success' => true,
                'data' => $result + [
                    'provisioning' => 'queued_by_paymenter',
                    'currency_code' => $currencyCode,
                    'currency_unit' => $currencyUnit,
                ],
            ], !empty($result['replayed']) ? 200 : 201);
        } catch (QueryException $e) {
            // Unique order/payment indexes are the final concurrency guard.
            // Recover an exact retry, but never attach one ZarinPal receipt to
            // a different order reference or payload.
            try {
                $existing = DB::table('fragcity_server_orders')
                    ->where('order_ref', $orderRef)
                    ->first();

                if ($existing) {
                    if (
                        hash_equals((string) $existing->payload_hash, $payloadHash)
                        && (string) $existing->status === 'accepted'
                    ) {
                        return response()->json([
                            'success' => true,
                            'data' => [
                                'order_id' => (int) $existing->paymenter_order_id,
                                'invoice_id' => (int) $existing->paymenter_invoice_id,
                                'service_id' => (int) $existing->paymenter_service_id,
                                'status' => 'accepted',
                                'replayed' => true,
                                'provisioning' => 'queued_by_paymenter',
                                'currency_code' => $currencyCode,
                                'currency_unit' => $currencyUnit,
                            ],
                        ]);
                    }

                    return response()->json([
                        'success' => false,
                        'error' => 'order_reference_conflict',
                    ], 409);
                }

                $paymentUsed = DB::table('fragcity_payment_receipts')
                    ->where(function ($query) use ($authority, $zarinpalRef): void {
                        $query->where('authority', $authority)
                            ->orWhere('ref_id', $zarinpalRef);
                    })
                    ->exists();

                if ($paymentUsed) {
                    return response()->json([
                        'success' => false,
                        'error' => 'zarinpal_payment_already_used',
                    ], 409);
                }
            } catch (Throwable) {
                // Fall through to the generic persistence failure below.
            }

            Log::error('FragCity CS2 order persistence failed.', [
                'order_ref' => $orderRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'sqlstate' => (string) $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'cs2_order_creation_failed',
            ], 500);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'cs2_checkout_not_configured', 'cs2_checkout_manifest_unavailable' => 503,
                'order_reference_conflict', 'zarinpal_payment_already_used' => 409,
                'paid_option_pricing_not_configured', 'invalid_egg_variable', 'egg_validation_failed',
                'cs2_product_invalid', 'cs2_product_out_of_stock', 'cs2_product_user_limit',
                'cs2_plan_invalid', 'cs2_plan_price_missing', 'cs2_quote_amount_mismatch', 'invalid_order_payload', 'egg_combination_invalid' => 422,
                'cs2_config_option_mapping_invalid', 'cs2_config_option_price_missing' => 503,
                'order_processing_incomplete' => 503,
                default => 500,
            };

            if ($status === 500) {
                Log::error('FragCity CS2 order creation failed.', [
                    'order_ref' => $orderRef,
                    'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                    'exception' => get_class($e),
                    'reason' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'cs2_order_creation_failed',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            // A client may retry after a transport timeout that happened after
            // the DB commit. Recover only an exact payload/order-ref match.
            try {
                $existing = DB::table('fragcity_server_orders')
                    ->where('order_ref', $orderRef)
                    ->first();

                if (
                    $existing
                    && hash_equals((string) $existing->payload_hash, $payloadHash)
                    && (string) $existing->status === 'accepted'
                ) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'order_id' => (int) $existing->paymenter_order_id,
                            'invoice_id' => (int) $existing->paymenter_invoice_id,
                            'service_id' => (int) $existing->paymenter_service_id,
                            'status' => 'accepted',
                            'replayed' => true,
                            'provisioning' => 'queued_by_paymenter',
                            'currency_code' => $currencyCode,
                            'currency_unit' => $currencyUnit,
                        ],
                    ]);
                }
            } catch (Throwable) {
                // Keep the original failure; the website can retry with this order_ref.
            }

            Log::error('FragCity CS2 order bridge failed.', [
                'order_ref' => $orderRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'cs2_order_creation_failed',
            ], 500);
        }
    }

    /**
     * Return the authenticated customer's FragCity CS2 services and
     * allow-listed Pterodactyl access details. No password is ever returned.
     */
    public function myServers(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
            ], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => true,
                    'data' => ['servers' => []],
                ]);
            }

            $user = User::query()->with('properties')->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $services = Service::query()
                ->where('user_id', (int) $user->id)
                ->where('status', '<>', Service::STATUS_CANCELLED)
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue', 'upgrade'])
                ->orderByDesc('id')
                ->get();

            $servers = [];

            foreach ($services as $service) {
                $serverExtension = $service->product?->server;
                if (!$serverExtension || $serverExtension->extension !== 'FragCityCS2') {
                    continue;
                }

                try {
                    $summary = ExtensionHelper::callService($service, 'getCustomerPanelSummary');
                    if (is_array($summary)) {
                        $summary['service_upgrade_ids'] = $service->upgrade
                            ->pluck('id')
                            ->map(static fn ($id): int => (int) $id)
                            ->values()
                            ->all();
                        $servers[] = $summary;
                    }
                } catch (Throwable $e) {
                    Log::warning('FragCity customer server summary failed.', [
                        'paymenter_user_id' => (int) $user->id,
                        'service_id' => (int) $service->id,
                        'exception' => get_class($e),
                    ]);

                    $servers[] = [
                        'service_id' => (int) $service->id,
                        'product_name' => (string) ($service->product?->name ?? 'CS2 Server'),
                        'service_status' => (string) $service->status,
                        'service_upgrade_ids' => $service->upgrade
                            ->pluck('id')
                            ->map(static fn ($id): int => (int) $id)
                            ->values()
                            ->all(),
                        'state' => 'unavailable',
                        'panel' => null,
                        'server' => null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['servers' => $servers],
            ]);
        } catch (Throwable $e) {
            Log::error('FragCity customer server list failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'customer_servers_unavailable',
            ], 500);
        }
    }

    /**
     * Change only the Pterodactyl password for one of the caller's CS2
     * services. Ownership is established from the signed SteamID link and
     * Paymenter service owner; the browser never receives an Application API key.
     */
    public function setPanelPassword(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:128',
                'regex:/^(?=.*\\p{L})(?=.*[0-9])\\S+$/u',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $steamId = (string) $data['steamid64'];
        $serviceId = (int) $data['service_id'];
        $password = (string) $data['password'];

        // Bridge traffic originates from the website server, so the route's
        // IP throttle is only a coarse ceiling. Apply the meaningful limit to
        // the signed customer identity to avoid coupling different customers.
        $rateLimitKey = 'fragcity_panel_password:' . hash('sha256', $steamId);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'success' => false,
                'error' => 'panel_password_rate_limited',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            $user = User::query()->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $service = Service::query()
                ->where('id', $serviceId)
                ->where('user_id', (int) $user->id)
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue'])
                ->first();

            if (!$service || $service->status === Service::STATUS_CANCELLED
                || $service->product?->server?->extension !== 'FragCityCS2'
            ) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            ExtensionHelper::callService(
                $service,
                'setCustomerPanelPassword',
                [$password]
            );

            // Store only a timestamp marker, never the password or its hash.
            $user->properties()->updateOrCreate(
                ['key' => 'fragcity_panel_password_set_at'],
                ['name' => 'FragCity Panel Password Set At', 'value' => now()->toIso8601String()]
            );

            RateLimiter::clear($rateLimitKey);

            return response()->json([
                'success' => true,
                'data' => ['password_updated' => true],
            ]);
        } catch (Throwable $e) {
            Log::warning('FragCity Pterodactyl password update failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'service_id' => $serviceId,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'panel_password_update_failed',
            ], 502);
        }
    }

    /**
     * Update the customer's shared Pterodactyl client username.
     * Ownership is checked through the signed SteamID link and service owner.
     */
    public function setPanelUsername(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        if (is_array($payload) && is_string($payload['username'] ?? null)) {
            $payload['username'] = strtolower(trim($payload['username']));
        }

        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'service_id' => ['required', 'integer', 'min:1'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9_-]{1,48}[a-z0-9]$/',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
        }

        $data = $validator->validated();
        $steamId = (string) $data['steamid64'];
        $serviceId = (int) $data['service_id'];
        $username = (string) $data['username'];

        $rateLimitKey = 'fragcity_panel_username:' . hash('sha256', $steamId);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'success' => false,
                'error' => 'panel_username_rate_limited',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        try {
            $link = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            $user = User::query()->find((int) $link->user_id);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'linked_paymenter_user_missing',
                ], 404);
            }

            $service = Service::query()
                ->where('id', $serviceId)
                ->where('user_id', (int) $user->id)
                ->with(['product.server', 'properties', 'configs.configOption', 'configs.configValue'])
                ->first();

            if (
                !$service
                || $service->status === Service::STATUS_CANCELLED
                || $service->product?->server?->extension !== 'FragCityCS2'
            ) {
                return response()->json([
                    'success' => false,
                    'error' => 'service_not_found',
                ], 404);
            }

            $result = ExtensionHelper::callService(
                $service,
                'setCustomerPanelUsername',
                [$username]
            );

            RateLimiter::clear($rateLimitKey);

            return response()->json([
                'success' => true,
                'data' => [
                    'username_updated' => true,
                    'username' => (string) ($result['username'] ?? $username),
                ],
            ]);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'panel_username_already_in_use') {
                return response()->json([
                    'success' => false,
                    'error' => 'panel_username_already_in_use',
                ], 409);
            }

            if ($e->getMessage() === 'panel_username_invalid') {
                return response()->json([
                    'success' => false,
                    'error' => 'validation_failed',
                ], 422);
            }

            Log::warning('FragCity Pterodactyl username update failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'service_id' => $serviceId,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'panel_username_update_failed',
            ], 502);
        }
    }

    private function authenticateBridgeRequest(Request $request): ?JsonResponse
    {
        $secret = (string) $this->config('shared_secret');
        if (!preg_match('/^[a-f0-9]{64,256}$/iD', $secret)) {
            return response()->json([
                'success' => false,
                'error' => 'bridge_not_configured',
            ], 503);
        }

        $allowedIps = preg_split('/[,\s]+/', trim((string) ($this->config('allowed_ips') ?? ''))) ?: [];
        $allowedIps = array_values(array_filter($allowedIps, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP)));

        if ($allowedIps && !in_array((string) $request->ip(), $allowedIps, true)) {
            return response()->json([
                'success' => false,
                'error' => 'source_ip_not_allowed',
            ], 403);
        }

        $timestamp = (string) $request->header('X-FragCity-Timestamp', '');
        $nonce = (string) $request->header('X-FragCity-Nonce', '');
        $signature = strtolower((string) $request->header('X-FragCity-Signature', ''));
        $rawBody = $request->getContent();

        if (strlen($rawBody) > 16384) {
            return response()->json([
                'success' => false,
                'error' => 'request_too_large',
            ], 413);
        }

        if (
            !preg_match('/^[0-9]{10}$/D', $timestamp)
            || !preg_match('/^[a-f0-9]{32,64}$/Di', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/D', $signature)
            || abs(time() - (int) $timestamp) > 300
        ) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_signature_headers',
            ], 401);
        }

        $signedMessage = $timestamp . "\n" . $nonce . "\n" . $rawBody;
        $expectedSignature = hash_hmac('sha256', $signedMessage, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_signature',
            ], 401);
        }

        $nonceKey = 'fragcity_api_nonce:' . hash('sha256', $nonce);
        if (!Cache::add($nonceKey, true, now()->addMinutes(10))) {
            return response()->json([
                'success' => false,
                'error' => 'request_replayed',
            ], 409);
        }

        if ($rawBody !== '') {
            try {
                $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_json_body',
                ], 400);
            }

            if (!is_array($payload)) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_json_body',
                ], 400);
            }
        } else {
            $payload = [];
        }

        $request->attributes->set('fragcity_bridge_payload', $payload);

        return null;
    }
}
