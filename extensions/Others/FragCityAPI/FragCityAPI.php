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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
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
    description: 'Private signed server-to-server VIP store, Paymenter wallet, CS2 checkout and service bridge for FragCity.',
    version: '1.15.0',
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
                'name' => 'sso_enabled',
                'label' => 'FragCity Single Sign-On Enabled',
                'type' => 'checkbox',
                'description' => 'Allow customers who are signed in to the main FragCity website to enter Billing without signing in again. Steam and FragCity username/password login are both supported.',
                'required' => false,
                'default' => true,
            ],
            [
                'name' => 'website_base_url',
                'label' => 'FragCity Website HTTPS Origin',
                'type' => 'text',
                'description' => 'Canonical main website origin used by the SSO hand-off. Use exactly https://fragcity.ir (no path or trailing slash).',
                'required' => true,
                'default' => 'https://fragcity.ir',
                'validation' => 'required|url|starts_with:https://',
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
                'label' => 'CS2 3-day Plan ID',
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
                'description' => 'Existing Paymenter Rial currency code used by all configured CS2 Plans (for example IRR). All Paymenter monetary records are stored in Rial.',
                'required' => true,
                'validation' => 'required|string|max:12|regex:/^[A-Za-z0-9_-]+$/',
            ],
            [
                'name' => 'vip_product_id',
                'label' => 'FragCity VIP Product ID',
                'type' => 'number',
                'description' => 'Non-server Paymenter Product used for Bronze, Silver and Gold VIP memberships.',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_plan_id_7_days',
                'label' => 'VIP 7-day Plan ID',
                'type' => 'number',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_plan_id_15_days',
                'label' => 'VIP 15-day Plan ID',
                'type' => 'number',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_plan_id_30_days',
                'label' => 'VIP 30-day Plan ID',
                'type' => 'number',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_plan_id_60_days',
                'label' => 'VIP 60-day Plan ID',
                'type' => 'number',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_plan_id_90_days',
                'label' => 'VIP 90-day Plan ID',
                'type' => 'number',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_level_option_id',
                'label' => 'VIP Level Config Option ID',
                'type' => 'number',
                'description' => 'Native Product Config Option with child Environment Variables 1, 2 and 3.',
                'required' => false,
                'validation' => 'nullable|integer|min:1',
            ],
            [
                'name' => 'vip_currency_code',
                'label' => 'VIP Paymenter Currency Code',
                'type' => 'text',
                'description' => 'Rial currency code for VIP plans, prices and Paymenter Wallet (for example IRR).',
                'required' => false,
                'validation' => 'nullable|string|max:12|regex:/^[A-Za-z0-9_-]+$/',
            ],
            [
                'name' => 'vipcore_api_base_url',
                'label' => 'Linux VIPCore API HTTPS Origin',
                'type' => 'text',
                'description' => 'HTTPS origin only, for example https://api.fragcity.ir. Paymenter calls the signed /v1/vipcore endpoint server-to-server.',
                'required' => false,
                'validation' => 'nullable|url|starts_with:https://',
            ],
            [
                'name' => 'vipcore_shared_secret',
                'label' => 'Linux VIPCore API HMAC Secret',
                'type' => 'password',
                'description' => 'Must match the global shared_secret in /etc/fragcity-api/config.php. Kept separate from the Paymenter website-bridge secret.',
                'required' => false,
                'encrypted' => true,
                'validation' => 'nullable|string|min:64|max:128',
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
        View::share('fragcitySsoEnabled', $this->isSsoEnabled());
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
            'version' => '1.15.0',
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
            3 => 'cs2_plan_id_1_day',
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
                    3 => [3, 'day'],
                    7 => [7, 'day'],
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

            $creditsEnabled = filter_var(
                (string) (Settings::getSetting('credits_enabled')->value ?? false),
                FILTER_VALIDATE_BOOLEAN
            );
            $minimumDeposit = (float) (Settings::getSetting('credits_minimum_deposit')->value ?? 0);
            $maximumDeposit = (float) (Settings::getSetting('credits_maximum_deposit')->value ?? 0);
            $maximumCredit = (float) (Settings::getSetting('credits_maximum_credit')->value ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'currency_code' => $currencyCode,
                    'currency_unit' => 'rial',
                    'balance' => number_format((float) ($credit?->amount ?? 0), 2, '.', ''),
                    'wallet_enabled' => $creditsEnabled,
                    'limits' => [
                        'minimum_deposit' => number_format($minimumDeposit, 2, '.', ''),
                        'maximum_deposit' => number_format($maximumDeposit, 2, '.', ''),
                        'maximum_credit' => number_format($maximumCredit, 2, '.', ''),
                    ],
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
     * Reserve wallet capacity before FragCity creates a ZarinPal payment
     * request. This prevents concurrent deposits from both passing the
     * maximum-credit check and only discovering the conflict after payment.
     */
    public function prepareWalletTopup(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'topup_ref' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/i'],
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'amount_toman' => ['required', 'integer', 'min:1'],
            'amount_rial' => ['required', 'integer', 'min:10'],
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
        $amountToman = (int) $data['amount_toman'];
        $amountRial = (int) $data['amount_rial'];

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
            Log::error('FragCity wallet top-up reservation settings could not be loaded.', [
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
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
                'currency_code' => $currencyCode,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        try {
            $result = DB::transaction(function () use (
                $topupRef,
                $steamId,
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

                    if ((string) $existing->status === 'accepted') {
                        return [
                            'status' => 'accepted',
                            'invoice_id' => (int) $existing->paymenter_invoice_id,
                            'expires_at' => null,
                            'replayed' => true,
                        ];
                    }

                    if (
                        (string) $existing->status === 'pending_payment'
                        && $existing->expires_at
                        && now()->lt($existing->expires_at)
                    ) {
                        return [
                            'status' => 'prepared',
                            'invoice_id' => null,
                            'expires_at' => (string) $existing->expires_at,
                            'replayed' => true,
                        ];
                    }

                    throw new RuntimeException('wallet_topup_reservation_expired');
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

                // Lock native invoices and reject outstanding manual Credit
                // invoices, matching Paymenter's native deposit restrictions.
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

                $now = now();
                DB::table('fragcity_wallet_topups')
                    ->where('paymenter_user_id', (int) $user->id)
                    ->where('status', 'pending_payment')
                    ->where('expires_at', '<=', $now)
                    ->update([
                        'status' => 'expired',
                        'updated_at' => $now,
                    ]);

                $credit = $user->credits()
                    ->where('currency_code', $currencyCode)
                    ->lockForUpdate()
                    ->first();

                $currentBalance = (float) ($credit?->amount ?? 0);
                $activeReservations = (float) DB::table('fragcity_wallet_topups')
                    ->where('paymenter_user_id', (int) $user->id)
                    ->where('status', 'pending_payment')
                    ->where('expires_at', '>', $now)
                    ->sum('amount_rial');

                if (($currentBalance + $activeReservations + $amountRial) > $maximumCredit) {
                    throw new RuntimeException('wallet_maximum_credit_exceeded');
                }

                $expiresAt = $now->copy()->addMinutes(20);
                DB::table('fragcity_wallet_topups')->insert([
                    'topup_ref' => $topupRef,
                    'steamid64' => $steamId,
                    'amount_toman' => $amountToman,
                    'amount_rial' => $amountRial,
                    'currency_code' => $currencyCode,
                    'payload_hash' => $payloadHash,
                    'paymenter_user_id' => (int) $user->id,
                    'paymenter_invoice_id' => null,
                    'status' => 'pending_payment',
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [
                    'status' => 'prepared',
                    'invoice_id' => null,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'replayed' => false,
                ];
            }, 3);

            return response()->json([
                'success' => true,
                'data' => [
                    'topup_ref' => $topupRef,
                    'status' => $result['status'],
                    'invoice_id' => $result['invoice_id'],
                    'expires_at' => $result['expires_at'],
                    'replayed' => (bool) $result['replayed'],
                    'currency_code' => $currencyCode,
                    'currency_unit' => 'rial',
                ],
            ], !empty($result['replayed']) ? 200 : 201);
        } catch (QueryException $e) {
            $existing = DB::table('fragcity_wallet_topups')
                ->where('topup_ref', $topupRef)
                ->first();

            if (
                $existing
                && hash_equals((string) $existing->payload_hash, $payloadHash)
                && (string) $existing->status === 'pending_payment'
                && $existing->expires_at
                && now()->lt($existing->expires_at)
            ) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'topup_ref' => $topupRef,
                        'status' => 'prepared',
                        'invoice_id' => null,
                        'expires_at' => (string) $existing->expires_at,
                        'replayed' => true,
                        'currency_code' => $currencyCode,
                        'currency_unit' => 'rial',
                    ],
                ]);
            }

            Log::error('FragCity wallet top-up reservation failed.', [
                'topup_ref' => $topupRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'sqlstate' => (string) $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_reservation_failed',
            ], 500);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'wallet_topup_reference_conflict' => 409,
                'wallet_topup_pending_invoice_exists', 'wallet_maximum_credit_exceeded',
                'wallet_topup_amount_invalid' => 422,
                'wallet_topup_reservation_expired' => 409,
                default => 500,
            };

            if ($status === 500) {
                Log::error('FragCity wallet top-up reservation failed.', [
                    'topup_ref' => $topupRef,
                    'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                    'exception' => get_class($e),
                    'reason' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'wallet_topup_reservation_failed',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            Log::error('FragCity wallet top-up reservation failed.', [
                'topup_ref' => $topupRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'wallet_topup_reservation_failed',
            ], 500);
        }
    }

    /**
     * Create and settle a Paymenter Credit invoice after FragCity has verified
     * the ZarinPal receipt server-to-server. Paymenter then applies the credit
     * through its native ProcessPaidInvoiceService after the DB commit.
     */
    /**
     * Settle a previously prepared wallet top-up after FragCity has verified
     * the ZarinPal receipt. Paymenter credits the wallet through its native
     * paid-Credit-invoice processor after this transaction commits.
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

        // This hash must be byte-for-byte equivalent to the prepare endpoint's
        // reservation hash. The ZarinPal receipt is separately guarded by the
        // global authority/ref-id ledger and is deliberately not in this hash.
        $payloadHash = hash(
            'sha256',
            json_encode([
                'topup_ref' => $topupRef,
                'steamid64' => $steamId,
                'amount_toman' => $amountToman,
                'amount_rial' => $amountRial,
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
                $payloadHash
            ): array {
                $reservation = DB::table('fragcity_wallet_topups')
                    ->where('topup_ref', $topupRef)
                    ->lockForUpdate()
                    ->first();

                if (!$reservation) {
                    throw new RuntimeException('wallet_topup_reservation_missing');
                }

                if (
                    !hash_equals((string) $reservation->payload_hash, $payloadHash)
                    || (string) $reservation->steamid64 !== $steamId
                    || (int) $reservation->amount_toman !== $amountToman
                    || (int) $reservation->amount_rial !== $amountRial
                    || strtoupper((string) $reservation->currency_code) !== $currencyCode
                ) {
                    throw new RuntimeException('wallet_topup_reference_conflict');
                }

                if ((string) $reservation->status === 'accepted') {
                    $acceptedReceipt = DB::table('fragcity_payment_receipts')
                        ->where('reference', $topupRef)
                        ->where('purpose', 'wallet_topup')
                        ->first();

                    if (
                        !$acceptedReceipt
                        || !hash_equals((string) $acceptedReceipt->authority, $authority)
                        || !hash_equals((string) $acceptedReceipt->ref_id, $zarinpalRef)
                    ) {
                        throw new RuntimeException('wallet_topup_reference_conflict');
                    }

                    return [
                        'invoice_id' => (int) $reservation->paymenter_invoice_id,
                        'user_id' => (int) $reservation->paymenter_user_id,
                        'replayed' => true,
                    ];
                }

                if (!in_array((string) $reservation->status, ['pending_payment', 'expired'], true)) {
                    throw new RuntimeException('wallet_topup_processing_incomplete');
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

                if ((int) $user->id !== (int) $reservation->paymenter_user_id) {
                    throw new RuntimeException('wallet_topup_reference_conflict');
                }

                // The receipt has already been verified by ZarinPal on FragCity.
                // Do not reject a paid deposit because a reservation expired or
                // Credits settings changed while the customer was at the gateway:
                // a verified payment must be credited, not stranded.
                $now = now();
                $invoice = new Invoice([
                    'user_id' => (int) $user->id,
                    'due_at' => $now,
                    'currency_code' => $currencyCode,
                ]);
                $invoice->send_create_email = false;
                $invoice->save();

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

                DB::table('fragcity_wallet_topups')
                    ->where('id', (int) $reservation->id)
                    ->update([
                        'paymenter_invoice_id' => (int) $invoice->id,
                        'status' => 'accepted',
                        'expires_at' => null,
                        'updated_at' => $now,
                    ]);

                // The native Paymenter observer processes the Credit invoice
                // item after commit and increments/creates the user's Credit row.
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
            // Recover only an exact already-accepted reservation/receipt pair.
            // A unique receipt collision from another purpose remains a conflict.
            $existing = DB::table('fragcity_wallet_topups')
                ->where('topup_ref', $topupRef)
                ->first();

            if (
                $existing
                && hash_equals((string) $existing->payload_hash, $payloadHash)
                && (string) $existing->status === 'accepted'
            ) {
                $receipt = DB::table('fragcity_payment_receipts')
                    ->where('reference', $topupRef)
                    ->where('purpose', 'wallet_topup')
                    ->first();

                if (
                    $receipt
                    && hash_equals((string) $receipt->authority, $authority)
                    && hash_equals((string) $receipt->ref_id, $zarinpalRef)
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

            Log::error('FragCity wallet top-up settlement persistence failed.', [
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
                'wallet_topup_reservation_missing' => 404,
                'wallet_topup_processing_incomplete' => 503,
                default => 500,
            };

            if ($status === 500) {
                Log::error('FragCity wallet top-up settlement failed.', [
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
            Log::error('FragCity wallet top-up settlement failed.', [
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
    /**
     * Calculate an authoritative CS2 checkout quote for the linked FragCity user.
     *
     * This is a read-only pre-payment operation. The website sends selections,
     * never a price; Paymenter resolves every native Config Option and applies
     * the same customer tax policy used again by createCs2Order().
     */
    public function quoteCs2Order(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'duration_days' => ['required', 'integer', 'in:3,7,15,30'],
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
        $steamId = (string) $data['steamid64'];
        $durationDays = (int) $data['duration_days'];
        $serverName = trim((string) $data['server_name']);
        $memoryMb = (int) $data['resources']['memory_mb'];
        $diskMb = (int) $data['resources']['disk_mb'];
        $cpuPercent = (int) $data['resources']['cpu_percent'];
        $featureInput = is_array($data['feature_limits'] ?? null) ? $data['feature_limits'] : [];
        $featureLimits = [
            'databases' => (int) ($featureInput['databases'] ?? 0),
            'additional_allocations' => (int) ($featureInput['additional_allocations'] ?? 0),
            'backups' => (int) ($featureInput['backups'] ?? 0),
        ];
        $eggValues = is_array($data['egg'] ?? null) ? $data['egg'] : [];

        // Bot Guard remains a paid add-on until its surcharge is part of the
        // authoritative quote. Do not let the browser request it as a free option.
        if ((string) ($eggValues['ENABLE_GUARD'] ?? '0') !== '0') {
            return response()->json([
                'success' => false,
                'error' => 'paid_option_pricing_not_configured',
            ], 422);
        }

        $currencyCode = strtoupper(trim((string) $this->config('cs2_currency_code')));
        $productId = (int) $this->config('cs2_product_id');
        $planSettings = [
            3 => 'cs2_plan_id_1_day',
            7 => 'cs2_plan_id_7_days',
            15 => 'cs2_plan_id_15_days',
            30 => 'cs2_plan_id_30_days',
        ];
        $planSetting = $planSettings[$durationDays] ?? null;
        $planId = $planSetting !== null ? (int) $this->config($planSetting) : 0;

        if ($productId < 1 || $planId < 1 || $currencyCode === '') {
            return response()->json([
                'success' => false,
                'error' => 'cs2_checkout_not_configured',
            ], 503);
        }

        try {
            $userLink = DB::table('fragcity_user_links')
                ->where('steamid64', $steamId)
                ->first();

            if (!$userLink) {
                throw new RuntimeException('fragcity_user_not_linked');
            }

            $user = User::query()
                ->with('properties')
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
                3 => [3, 'day'],
                7 => [7, 'day'],
                15 => [15, 'day'],
                30 => [1, 'month'],
            };

            if (
                !$plan
                || in_array((string) $plan->type, ['free', 'one-time'], true)
                || (int) $plan->billing_period !== $expectedPlan[0]
                || (string) $plan->billing_unit !== $expectedPlan[1]
                || !$plan->prices->contains('currency_code', $currencyCode)
            ) {
                throw new RuntimeException('cs2_plan_invalid');
            }

            // Resolve all eight native Config Options in exactly the same way
            // as the paid-order endpoint. Numeric child values are never trusted
            // unless they exist beneath the expected parent Config Option.
            $targets = [
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

            $normalizeOptionName = static function ($value): string {
                return strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim((string) $value)));
            };

            $recurringSubtotal = 0.0;
            $setupFeeTotal = 0.0;
            $priceLines = [];
            $usedConfigOptionIds = [];

            foreach ($targets as $targetName => $target) {
                if (
                    isset($target['allowed_values'])
                    && !in_array($target['value'], $target['allowed_values'], true)
                ) {
                    throw new RuntimeException('cs2_config_option_mapping_invalid');
                }

                $matchingOptions = collect($product->configOptions)->filter(
                    function ($option) use ($target, $normalizeOptionName) {
                        $identifiers = array_map(
                            $normalizeOptionName,
                            [$option->env_variable ?? '', $option->name ?? '']
                        );

                        return array_intersect($target['aliases'], $identifiers) !== [];
                    }
                );

                if ($matchingOptions->count() !== 1) {
                    throw new RuntimeException('cs2_config_option_mapping_invalid');
                }

                $option = $matchingOptions->first();
                $matchingChildren = $option->children->filter(
                    fn ($child) => trim((string) $child->env_variable) === $target['value']
                );

                if ($matchingChildren->count() !== 1) {
                    throw new RuntimeException('cs2_config_option_mapping_invalid');
                }

                if (
                    (string) $option->type !== 'select'
                    || !(bool) ($option->upgradable ?? false)
                    || in_array((int) $option->id, $usedConfigOptionIds, true)
                ) {
                    throw new RuntimeException('cs2_config_option_mapping_invalid');
                }

                $child = $matchingChildren->first();
                $childPrice = $child->price(
                    null,
                    (int) $plan->billing_period,
                    (string) $plan->billing_unit,
                    $currencyCode
                );

                if (!$childPrice->available) {
                    throw new RuntimeException('cs2_config_option_price_missing');
                }

                $mustBeFree = (
                    $targetName === 'max_players' && $target['value'] === '5'
                ) || in_array($targetName, [
                    'tickrate',
                    'databases',
                    'additional_allocations',
                    'backups',
                ], true);

                if (
                    $mustBeFree
                    && (
                        (float) $childPrice->price !== 0.0
                        || (float) ($childPrice->setup_fee ?? 0) !== 0.0
                    )
                ) {
                    throw new RuntimeException('cs2_config_option_price_missing');
                }

                $recurringSubtotal += (float) $childPrice->price;
                $setupFeeTotal += (float) ($childPrice->setup_fee ?? 0);
                $priceLines[] = [
                    'option' => (string) $option->name,
                    'value' => (string) $child->name,
                    'recurring_price' => number_format((float) $childPrice->price, 2, '.', ''),
                    'setup_fee' => number_format((float) ($childPrice->setup_fee ?? 0), 2, '.', ''),
                ];
                $usedConfigOptionIds[] = (int) $option->id;
            }

            // Validate the Egg fields against Paymenter's live Checkout manifest.
            $checkoutFields = ExtensionHelper::getCheckoutConfig($product);
            if (!is_array($checkoutFields) || $checkoutFields === []) {
                throw new RuntimeException('cs2_checkout_manifest_unavailable');
            }

            $manifestPath = dirname(__DIR__, 2) . '/Servers/FragCityCS2/resources/cs2-egg-variables.json';
            if (!is_file($manifestPath)) {
                throw new RuntimeException('cs2_checkout_manifest_unavailable');
            }

            $manifest = json_decode(
                (string) file_get_contents($manifestPath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if (!is_array($manifest)) {
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

                $rules = [!empty($field['required']) ? 'required' : 'nullable'];
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
                        $optionValues = is_array($options)
                            ? array_map('strval', array_keys($options))
                            : [];
                    }

                    if ($optionValues !== []) {
                        $rules[] = 'in:' . implode(',', array_map(
                            static fn (string $value): string => str_replace(',', '\\,', $value),
                            $optionValues
                        ));
                    }
                }

                $eggRules[$name] = array_values(array_unique($rules));
            }

            // The manifest also defines five native Paymenter Config Options:
            // Max Players and Tickrate are Egg values; database/allocation/backup
            // values are feature limits and must not be sent as arbitrary Egg keys.
            $nativeConfigOptionFields = $manifest['paymenter']['config_options'] ?? [];
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

                $rules = [!empty($field['required']) ? 'required' : 'nullable'];
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
                    static fn (string $value): string => str_replace(',', '\\,', $value),
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

            $cfgOptions = $manifest['game_mode_manager']['cfg_options'] ?? [];
            $selectedCfg = null;
            foreach (is_array($cfgOptions) ? $cfgOptions : [] as $cfgOption) {
                if ((string) ($cfgOption['config'] ?? '') === (string) ($normalizedEggValues['EXEC'] ?? '')) {
                    $selectedCfg = $cfgOption;
                    break;
                }
            }

            $compatibleMapGroups = $selectedCfg['mapGroups'] ?? [];
            if (
                !is_array($compatibleMapGroups)
                || !in_array((string) ($normalizedEggValues['MAPGROUP'] ?? ''), $compatibleMapGroups, true)
            ) {
                throw new RuntimeException('egg_combination_invalid');
            }

            $planPrice = $plan->price($currencyCode);
            if (!$planPrice->available) {
                throw new RuntimeException('cs2_plan_price_missing');
            }

            $recurringSubtotal += (float) $planPrice->price;
            $setupFeeTotal += (float) ($planPrice->setup_fee ?? 0);
            array_unshift($priceLines, [
                'option' => (string) $plan->name,
                'value' => (string) $durationDays . ' days',
                'recurring_price' => number_format((float) $planPrice->price, 2, '.', ''),
                'setup_fee' => number_format((float) ($planPrice->setup_fee ?? 0), 2, '.', ''),
            ]);

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

            $computedTotal = round((float) $invoicePrice->total, 2);
            $wholeRialTotal = (int) round($computedTotal);
            if (
                abs($computedTotal - $wholeRialTotal) > 0.00001
                || $wholeRialTotal < 1
                || ($wholeRialTotal % 10) !== 0
            ) {
                throw new RuntimeException('cs2_quote_amount_mismatch');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'currency_code' => $currencyCode,
                    'currency_unit' => 'rial',
                    'amount_rial' => $wholeRialTotal,
                    'amount_toman' => intdiv($wholeRialTotal, 10),
                    'recurring_subtotal_rial' => (int) round($recurringSubtotal),
                    'setup_fee_total_rial' => (int) round($setupFeeTotal),
                    'tax_rial' => $wholeRialTotal - (int) round($recurringSubtotal + $setupFeeTotal),
                    'duration_days' => $durationDays,
                    'server_name' => $serverName,
                    'price_lines' => $priceLines,
                    'expires_in_seconds' => 60,
                ],
            ]);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'cs2_checkout_not_configured', 'cs2_checkout_manifest_unavailable' => 503,
                'cs2_product_invalid', 'cs2_product_out_of_stock', 'cs2_product_user_limit',
                'cs2_plan_invalid', 'cs2_plan_price_missing', 'cs2_config_option_mapping_invalid',
                'cs2_config_option_price_missing', 'cs2_quote_amount_mismatch',
                'egg_combination_invalid', 'invalid_egg_variable', 'egg_validation_failed',
                'paid_option_pricing_not_configured' => 422,
                default => 500,
            };

            if ($status === 500) {
                Log::error('FragCity CS2 quote failed.', [
                    'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                    'exception' => get_class($e),
                    'reason' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'cs2_quote_unavailable',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            Log::error('FragCity CS2 quote failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'cs2_quote_unavailable',
            ], 500);
        }
    }

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
            'expected_amount_rial' => ['sometimes', 'integer', 'min:10'],
            'payment' => ['required', 'array'],
            'payment.provider' => ['required', 'in:zarinpal,wallet'],
            'payment.authority' => [
                'required_if:payment.provider,zarinpal',
                'prohibited_if:payment.provider,wallet',
                'string',
                'max:128',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'payment.ref_id' => [
                'required_if:payment.provider,zarinpal',
                'prohibited_if:payment.provider,wallet',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'payment.amount_toman' => [
                'required_if:payment.provider,zarinpal',
                'prohibited_if:payment.provider,wallet',
                'integer',
                'min:1',
            ],
            'payment.amount_rial' => [
                'required_if:payment.provider,zarinpal',
                'prohibited_if:payment.provider,wallet',
                'integer',
                'min:10',
            ],
            'duration_days' => ['required', 'integer', 'in:3,7,15,30'],
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
        $paymentProvider = (string) $data['payment']['provider'];
        $isZarinpal = $paymentProvider === 'zarinpal';
        $authority = $isZarinpal ? (string) $data['payment']['authority'] : '';
        $zarinpalRef = $isZarinpal ? (string) $data['payment']['ref_id'] : '';
        $amountToman = $isZarinpal ? (int) $data['payment']['amount_toman'] : 0;
        $amountRial = $isZarinpal ? (int) $data['payment']['amount_rial'] : 0;
        $expectedAmountRial = isset($data['expected_amount_rial'])
            ? (int) $data['expected_amount_rial']
            : null;
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
            ($isZarinpal && (
                $amountToman > intdiv(PHP_INT_MAX, 10)
                || $amountRial !== $amountToman * 10
            ))
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
            3 => 'cs2_plan_id_1_day',
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
                'provider' => $paymentProvider,
                'authority' => $isZarinpal ? $authority : null,
                'ref_id' => $isZarinpal ? $zarinpalRef : null,
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
                $paymentProvider,
                $isZarinpal,
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
                $paymenterAmount,
                $expectedAmountRial
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

                if ($isZarinpal) {
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
                    3 => [3, 'day'],
                    7 => [7, 'day'],
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
                        || !(bool) ($configOption->upgradable ?? false)
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

                if ($expectedAmountRial !== null && $expectedAmountRial !== $wholeRialTotal) {
                    throw new RuntimeException('cs2_quote_amount_mismatch');
                }

                // ZarinPal's Toman amount must map exactly to Paymenter's Rial
                // total. We reject fractional Rial or a remainder that cannot be
                // represented as whole Toman rather than silently rounding a charge.
                if (
                    abs($computedInvoiceTotal - $wholeRialTotal) > 0.00001
                    || ($wholeRialTotal % 10) !== 0
                ) {
                    throw new RuntimeException('cs2_quote_amount_mismatch');
                }

                // A verified external payment must exactly equal Paymenter's
                // computed total. Wallet purchases do not send an amount at all:
                // Paymenter chooses the amount and checks/debits its own Credit row.
                if ($isZarinpal) {
                    if (
                        $amountRial !== $wholeRialTotal
                        || $amountToman !== intdiv($wholeRialTotal, 10)
                    ) {
                        throw new RuntimeException('cs2_quote_amount_mismatch');
                    }
                } else {
                    $creditsEnabled = filter_var(
                        (string) (Settings::getSetting('credits_enabled')->value ?? false),
                        FILTER_VALIDATE_BOOLEAN
                    );
                    if (!$creditsEnabled) {
                        throw new RuntimeException('wallet_not_enabled');
                    }

                    $credit = $user->credits()
                        ->where('currency_code', $currencyCode)
                        ->lockForUpdate()
                        ->first();

                    $availableCredit = (float) ($credit?->amount ?? 0);
                    if (!$credit || $availableCredit < $computedInvoiceTotal) {
                        throw new RuntimeException('wallet_insufficient_credit');
                    }

                    // The linked Paymenter user row is already locked in this
                    // transaction; the credit row is now locked too. This debit
                    // is atomic with Order, Service, Invoice and transaction creation.
                    $credit->decrement('amount', $computedInvoiceTotal);
                    $amountRial = $wholeRialTotal;
                    $amountToman = intdiv($wholeRialTotal, 10);
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
                    $isZarinpal ? 'zarinpal:' . $zarinpalRef : 'fragcity-credit:' . $orderRef,
                    InvoiceTransactionStatus::Succeeded,
                    !$isZarinpal
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
                    'zarinpal_authority' => $isZarinpal ? $authority : null,
                    'zarinpal_ref_id' => $isZarinpal ? $zarinpalRef : null,
                    'payment_method' => $paymentProvider,
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

                if ($isZarinpal) {
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
                }

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
                'cs2_plan_invalid', 'cs2_plan_price_missing', 'cs2_quote_amount_mismatch', 'invalid_order_payload', 'egg_combination_invalid',
                'wallet_insufficient_credit', 'wallet_not_enabled' => 422,
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

    /**
     * Return Paymenter-owned VIP prices, wallet balance and current VIPCore state.
     * No browser-supplied price or Paymenter user ID is trusted.
     */
    public function vipCatalog(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'steamid64' => ['nullable', 'string', 'regex:/^[0-9]{17}$/'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'validation_failed'], 422);
        }

        $steamId = trim((string) ($validator->validated()['steamid64'] ?? ''));
        try {
            $settings = $this->vipStoreSettings();
            $user = null;
            $vipStatus = [
                'active' => false,
                'level' => 0,
                'expires' => 0,
                'paid_purchase' => null,
                'paid_ledger_available' => false,
            ];

            if ($steamId !== '') {
                $link = DB::table('fragcity_user_links')->where('steamid64', $steamId)->first();
                if (!$link) {
                    return response()->json(['success' => false, 'error' => 'fragcity_user_not_linked'], 404);
                }
                $user = User::query()->find((int) $link->user_id);
                if (!$user) {
                    return response()->json(['success' => false, 'error' => 'linked_paymenter_user_missing'], 404);
                }
                $vipStatus = $this->requestVipCore($settings, [
                    'action' => 'get_status',
                    'steamid64' => $steamId,
                ]);
            }

            $product = $this->loadVipProduct($settings);

            $prices = [];
            foreach ([7, 15, 30, 60, 90] as $days) {
                foreach ([1, 2, 3] as $level) {
                    $prices[(string) $level][(string) $days] = $this->calculateVipPrice(
                        $product,
                        $settings,
                        $user,
                        $level,
                        $days
                    );
                }
            }

            $upgradePrices = [];
            $active = ($vipStatus['active'] ?? false) === true;
            $currentLevel = (int) ($vipStatus['level'] ?? 0);
            $currentExpires = (int) ($vipStatus['expires'] ?? 0);
            $paidPurchase = $vipStatus['paid_purchase'] ?? null;

            if ($active && is_array($paidPurchase) && in_array($currentLevel, [1, 2], true)) {
                $sourceRef = strtolower((string) ($paidPurchase['order_ref'] ?? ''));
                $durationSeconds = (int) ($paidPurchase['duration_seconds'] ?? 0);
                $durationDays = (int) round($durationSeconds / 86400);
                $sourceOrder = DB::table('fragcity_vip_orders')
                    ->where('order_ref', $sourceRef)
                    ->where('steamid64', $steamId)
                    ->where('status', 'provisioned')
                    ->first();

                // Existing paid memberships from the old website are deliberately
                // not guessed into the new billing ledger. They need a one-time
                // verified import before discounted upgrades can be quoted.
                if (
                    is_array($paidPurchase)
                    && preg_match('/^[a-f0-9]{32}$/D', $sourceRef)
                    && is_object($sourceOrder)
                    && in_array($durationDays, [7, 15, 30, 60, 90], true)
                    && $durationSeconds === $durationDays * 86400
                    && (int) ($sourceOrder->vip_level ?? 0) === $currentLevel
                    && (int) ($sourceOrder->vip_expires_at ?? 0) === $currentExpires
                ) {
                    $remainingSeconds = max(0, $currentExpires - time());
                    $currentTierPrice = (int) ($paidPurchase['tier_price_rial'] ?? $sourceOrder->tier_price_rial);
                    if ($remainingSeconds > 0 && $currentTierPrice > 0) {
                        foreach (range($currentLevel + 1, 3) as $targetLevel) {
                            $target = $this->calculateVipPrice(
                                $product,
                                $settings,
                                $user,
                                $targetLevel,
                                $durationDays
                            );
                            $baseDifference = (int) $target['tier_price_rial'] - $currentTierPrice;
                            if ($baseDifference < 1) {
                                continue;
                            }

                            $proratedBase = (int) (round((($baseDifference * $remainingSeconds) / $durationSeconds) / 1000) * 1000);
                            if ($proratedBase < 10) {
                                continue;
                            }

                            $plan = $this->resolveVipPlan($product, $settings, $durationDays);
                            $taxed = new Price(
                                ['price' => $proratedBase, 'currency' => $plan->price($settings['currency'])->currency],
                                apply_exclusive_tax: true,
                                tax: Settings::tax($user)
                            );
                            $amount = (float) $taxed->total;
                            $amountRial = (int) round($amount);
                            if (abs($amount - $amountRial) > 0.00001 || $amountRial < 10 || $amountRial % 10 !== 0) {
                                continue;
                            }

                            $upgradePrices[(string) $targetLevel] = [
                                'amount_rial' => $amountRial,
                                'amount_toman' => intdiv($amountRial, 10),
                                'tier_price_rial' => (int) $target['tier_price_rial'],
                                'duration_days' => $durationDays,
                                'remaining_seconds' => $remainingSeconds,
                                'from_order_ref' => $sourceRef,
                                'from_level' => $currentLevel,
                                'from_expires' => $currentExpires,
                            ];
                        }
                    }
                }
            }

            $credit = $user
                ? $user->credits()->where('currency_code', $settings['currency'])->first()
                : null;
            $creditsEnabled = $user !== null && filter_var(
                (string) (Settings::getSetting('credits_enabled')->value ?? false),
                FILTER_VALIDATE_BOOLEAN
            );
            $pendingOrder = $steamId !== ''
                ? DB::table('fragcity_vip_orders')
                    ->where('steamid64', $steamId)
                    ->where('status', 'paid')
                    ->orderByDesc('id')
                    ->first()
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => [
                        'id' => (int) $product->id,
                        'name' => (string) $product->name,
                    ],
                    'currency_code' => $settings['currency'],
                    'currency_unit' => 'rial',
                    'duration_days' => [7, 15, 30, 60, 90],
                    'prices' => $prices,
                    'upgrade_prices' => $upgradePrices,
                    'pending_order' => $pendingOrder ? $this->vipOrderResponse($pendingOrder, true) : null,
                    'wallet_balance' => number_format((float) ($credit?->amount ?? 0), 2, '.', ''),
                    'wallet_enabled' => $creditsEnabled,
                    'current_membership' => [
                        'active' => $active,
                        'level' => $currentLevel,
                        'expires' => $currentExpires,
                        'paid_purchase' => is_array($paidPurchase) ? $paidPurchase : null,
                        'upgrade_available' => count($upgradePrices) > 0,
                        'legacy_paid_import_required' => $active
                            && is_array($paidPurchase)
                            && $currentLevel > 0
                            && count($upgradePrices) === 0
                            && !is_object($sourceOrder ?? null),
                    ],
                ],
            ]);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'vip_store_not_configured', 'vipcore_not_configured' => 503,
                'vip_product_invalid', 'vip_plan_invalid', 'vip_level_option_invalid' => 422,
                default => 503,
            };
            Log::warning('FragCity VIP catalogue request failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'reason' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            Log::error('FragCity VIP catalogue could not be built.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);
            return response()->json(['success' => false, 'error' => 'vip_catalog_unavailable'], 503);
        }
    }

    /**
     * Charge the native Paymenter Wallet, create a real paid Invoice and write
     * an idempotent VIP entitlement ledger before calling the Linux VIPCore API.
     */
    public function createVipOrder(Request $request): JsonResponse
    {
        $authError = $this->authenticateBridgeRequest($request);
        if ($authError) {
            return $authError;
        }

        $payload = $request->attributes->get('fragcity_bridge_payload');
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'order_ref' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/i'],
            'steamid64' => ['required', 'string', 'regex:/^[0-9]{17}$/'],
            'level' => ['required', 'integer', 'in:1,2,3'],
            'duration_days' => ['required', 'integer', 'in:7,15,30,60,90'],
            'expected_amount_rial' => ['required', 'integer', 'min:10'],
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
        $level = (int) $data['level'];
        $durationDays = (int) $data['duration_days'];
        $expectedAmount = (int) $data['expected_amount_rial'];
        $payloadHash = hash('sha256', json_encode([
            'steamid64' => $steamId,
            'level' => $level,
            'duration_days' => $durationDays,
            'expected_amount_rial' => $expectedAmount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        try {
            $settings = $this->vipStoreSettings();

            $existing = DB::table('fragcity_vip_orders')->where('order_ref', $orderRef)->first();
            if ($existing) {
                if (
                    (string) $existing->steamid64 !== $steamId
                    || !hash_equals((string) $existing->payload_hash, $payloadHash)
                ) {
                    return response()->json(['success' => false, 'error' => 'vip_order_reference_conflict'], 409);
                }

                if ((string) $existing->status === 'provisioned') {
                    return response()->json([
                        'success' => true,
                        'data' => $this->vipOrderResponse($existing, true),
                    ]);
                }

                if ((string) $existing->status !== 'paid') {
                    return response()->json(['success' => false, 'error' => 'vip_order_not_retryable'], 409);
                }

                return $this->provisionVipOrder($settings, $existing, true);
            }

            $link = DB::table('fragcity_user_links')->where('steamid64', $steamId)->first();
            if (!$link) {
                return response()->json(['success' => false, 'error' => 'fragcity_user_not_linked'], 404);
            }

            $createdOrder = DB::transaction(function () use (
                $settings,
                $link,
                $orderRef,
                $steamId,
                $level,
                $durationDays,
                $expectedAmount,
                $payloadHash
            ): object {
                $existing = DB::table('fragcity_vip_orders')->where('order_ref', $orderRef)->lockForUpdate()->first();
                if ($existing) {
                    if (
                        (string) $existing->steamid64 !== $steamId
                        || !hash_equals((string) $existing->payload_hash, $payloadHash)
                    ) {
                        throw new RuntimeException('vip_order_reference_conflict');
                    }
                    return $existing;
                }

                $user = User::query()->lockForUpdate()->find((int) $link->user_id);
                if (!$user) {
                    throw new RuntimeException('linked_paymenter_user_missing');
                }

                $product = $this->loadVipProduct($settings);
                $playerName = trim((string) $user->properties()->where('key', 'fragcity_username')->value('value'));
                if ($playerName === '') {
                    $playerName = trim((string) $user->name);
                }
                if ($playerName === '') {
                    throw new RuntimeException('vip_player_name_missing');
                }
                if (function_exists('mb_strlen') && mb_strlen($playerName, 'UTF-8') > 64) {
                    $playerName = mb_substr($playerName, 0, 64, 'UTF-8');
                } elseif (strlen($playerName) > 64) {
                    $playerName = substr($playerName, 0, 64);
                }
                if (preg_match('//u', $playerName) !== 1 || preg_match('/[\\x00-\\x1F\\x7F]/', $playerName)) {
                    throw new RuntimeException('vip_player_name_invalid');
                }

                $vipStatus = $this->requestVipCore($settings, [
                    'action' => 'get_status',
                    'steamid64' => $steamId,
                ]);
                $quote = $this->quoteVipSelection(
                    $product,
                    $settings,
                    $user,
                    $vipStatus,
                    $steamId,
                    $level,
                    $durationDays
                );

                if ((int) $quote['amount_rial'] !== $expectedAmount) {
                    throw new RuntimeException('vip_quote_amount_mismatch');
                }

                $creditsEnabled = filter_var(
                    (string) (Settings::getSetting('credits_enabled')->value ?? false),
                    FILTER_VALIDATE_BOOLEAN
                );
                if (!$creditsEnabled) {
                    throw new RuntimeException('vip_wallet_not_enabled');
                }

                $credit = $user->credits()
                    ->where('currency_code', $settings['currency'])
                    ->lockForUpdate()
                    ->first();
                $available = (float) ($credit?->amount ?? 0);
                if (!$credit || $available < (float) $quote['amount_rial']) {
                    throw new RuntimeException('vip_wallet_insufficient');
                }

                $credit->decrement('amount', number_format((float) $quote['amount_rial'], 2, '.', ''));

                $now = now();
                $invoice = new Invoice([
                    'user_id' => (int) $user->id,
                    'due_at' => $now,
                    'currency_code' => $settings['currency'],
                ]);
                $invoice->send_create_email = false;
                $invoice->save();

                $description = 'FragCity VIP ' . $this->vipLevelName($level)
                    . ' — ' . $durationDays . ' days';
                $invoice->items()->create([
                    'reference_id' => (int) $product->id,
                    'reference_type' => Product::class,
                    'price' => number_format((float) $quote['amount_rial'], 2, '.', ''),
                    'quantity' => 1,
                    'description' => $description,
                ]);

                ExtensionHelper::addPayment(
                    $invoice,
                    null,
                    (float) $quote['amount_rial'],
                    null,
                    'fragcity-vip:' . $orderRef,
                    InvoiceTransactionStatus::Succeeded,
                    true
                );

                $invoice->status = Invoice::STATUS_PAID;
                $invoice->save();

                DB::table('fragcity_vip_orders')->insert([
                    'order_ref' => $orderRef,
                    'steamid64' => $steamId,
                    'paymenter_user_id' => (int) $user->id,
                    'player_name' => $playerName,
                    'product_id' => (int) $product->id,
                    'plan_id' => (int) $quote['plan_id'],
                    'config_option_id' => (int) $settings['level_option_id'],
                    'config_value_id' => (int) $quote['config_value_id'],
                    'vip_level' => $level,
                    'duration_days' => $durationDays,
                    'duration_seconds' => $durationDays * 86400,
                    'amount_rial' => number_format((float) $quote['amount_rial'], 2, '.', ''),
                    'tier_price_rial' => (int) $quote['tier_price_rial'],
                    'currency_code' => $settings['currency'],
                    'purchase_type' => (string) $quote['purchase_type'],
                    'upgrade_from_order_ref' => $quote['upgrade']['from_order_ref'] ?? null,
                    'upgrade_from_level' => $quote['upgrade']['from_level'] ?? null,
                    'upgrade_from_expires' => $quote['upgrade']['from_expires'] ?? null,
                    'upgrade_remaining_seconds' => $quote['upgrade']['remaining_seconds'] ?? null,
                    'payload_hash' => $payloadHash,
                    'paymenter_invoice_id' => (int) $invoice->id,
                    'status' => 'paid',
                    'paid_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return DB::table('fragcity_vip_orders')->where('order_ref', $orderRef)->firstOrFail();
            }, 3);

            if ((string) $createdOrder->status === 'provisioned') {
                return response()->json([
                    'success' => true,
                    'data' => $this->vipOrderResponse($createdOrder, true),
                ]);
            }

            return $this->provisionVipOrder($settings, $createdOrder, false);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'vip_wallet_insufficient' => 422,
                'vip_wallet_not_enabled' => 422,
                'vip_product_invalid', 'vip_plan_invalid', 'vip_level_option_invalid' => 422,
                'vip_store_not_configured', 'vipcore_not_configured' => 503,
                'vip_order_reference_conflict' => 409,
                'vip_paid_membership_upgrade_required', 'vip_membership_already_active',
                'vip_paid_membership_not_migrated', 'vip_upgrade_source_changed' => 409,
                'vip_quote_amount_mismatch', 'vip_upgrade_duration_mismatch',
                'vip_upgrade_price_invalid', 'vip_upgrade_amount_too_small' => 409,
                'vip_player_name_missing', 'vip_player_name_invalid' => 422,
                default => 503,
            };
            return response()->json(['success' => false, 'error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            Log::error('FragCity VIP order creation failed.', [
                'order_ref' => $orderRef,
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);
            return response()->json(['success' => false, 'error' => 'vip_order_creation_failed'], 500);
        }
    }

    public function listVipOrders(Request $request): JsonResponse
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
            return response()->json(['success' => false, 'error' => 'validation_failed'], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];
        $link = DB::table('fragcity_user_links')->where('steamid64', $steamId)->first();
        if (!$link) {
            return response()->json(['success' => false, 'error' => 'fragcity_user_not_linked'], 404);
        }

        $orders = DB::table('fragcity_vip_orders')
            ->where('steamid64', $steamId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn ($order): array => $this->vipOrderResponse($order, true))
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => ['orders' => $orders]]);
    }

    /**
     * Revoke VIPCore membership and return unused paid value to Paymenter's
     * native Wallet. The unique refund_ref makes the financial side idempotent
     * if Linux API revocation succeeds but the Paymenter transaction must retry.
     */
    public function revokeVipMembership(Request $request): JsonResponse
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
            return response()->json(['success' => false, 'error' => 'validation_failed'], 422);
        }

        $steamId = (string) $validator->validated()['steamid64'];

        try {
            $settings = $this->vipStoreSettings();
            $link = DB::table('fragcity_user_links')->where('steamid64', $steamId)->first();
            if (!$link) {
                return response()->json(['success' => false, 'error' => 'fragcity_user_not_linked'], 404);
            }

            $result = DB::transaction(function () use ($settings, $link, $steamId): array {
                $user = User::query()->lockForUpdate()->find((int) $link->user_id);
                if (!$user) {
                    throw new RuntimeException('linked_paymenter_user_missing');
                }

                $status = $this->requestVipCore($settings, [
                    'action' => 'get_status',
                    'steamid64' => $steamId,
                ]);
                if (($status['active'] ?? false) !== true) {
                    // If a previous attempt already removed VIPCore access but
                    // the HTTP response was lost, return the existing Paymenter
                    // refund ledger instead of failing or crediting twice.
                    $priorRefund = DB::table('fragcity_vip_refunds')
                        ->where('steamid64', $steamId)
                        ->where('status', 'credited')
                        ->orderByDesc('id')
                        ->first();
                    if ($priorRefund) {
                        $balance = $user->credits()
                            ->where('currency_code', $settings['currency'])
                            ->value('amount');
                        return [
                            'revoked' => true,
                            'refund_ref' => (string) $priorRefund->refund_ref,
                            'order_ref' => (string) ($priorRefund->revoked_order_ref ?? ''),
                            'refund_amount_rial' => number_format((float) $priorRefund->amount_rial, 2, '.', ''),
                            'wallet_balance' => number_format((float) ($balance ?? 0), 2, '.', ''),
                            'invoice_id' => (int) ($priorRefund->paymenter_invoice_id ?? 0),
                            'replayed' => true,
                        ];
                    }
                    throw new RuntimeException('vip_membership_not_active');
                }

                $paidPurchase = $status['paid_purchase'] ?? null;
                $paidOrderRef = null;
                $remainingSeconds = 0;
                $refundUnits = 0;
                $ordersToRevoke = [];

                if (is_array($paidPurchase)) {
                    $paidOrderRef = strtolower((string) ($paidPurchase['order_ref'] ?? ''));
                    if (!preg_match('/^[a-f0-9]{32}$/D', $paidOrderRef)) {
                        throw new RuntimeException('vip_paid_membership_not_migrated');
                    }

                    $currentOrder = DB::table('fragcity_vip_orders')
                        ->where('order_ref', $paidOrderRef)
                        ->where('steamid64', $steamId)
                        ->lockForUpdate()
                        ->first();
                    if (
                        !$currentOrder
                        || (string) $currentOrder->status !== 'provisioned'
                        || (int) $currentOrder->vip_level !== (int) ($status['level'] ?? 0)
                        || (int) $currentOrder->vip_expires_at !== (int) ($status['expires'] ?? 0)
                    ) {
                        throw new RuntimeException('vip_paid_membership_not_migrated');
                    }

                    $paidAt = $currentOrder->paid_at
                        ? strtotime((string) $currentOrder->paid_at)
                        : 0;
                    if ($paidAt < 1) {
                        throw new RuntimeException('vip_revoke_cooldown_unavailable');
                    }
                    $retryAfter = max(0, ($paidAt + 3600) - time());
                    if ($retryAfter > 0) {
                        throw new RuntimeException('vip_revoke_cooldown:' . $retryAfter);
                    }

                    $revokedAt = time();
                    $expires = (int) $currentOrder->vip_expires_at;
                    $remainingSeconds = max(0, $expires - $revokedAt);

                    $seen = [];
                    $cursor = $paidOrderRef;
                    $depth = 0;
                    while ($cursor !== null && $cursor !== '') {
                        if ($depth >= 12 || isset($seen[$cursor])) {
                            throw new RuntimeException('vip_refund_upgrade_chain_invalid');
                        }
                        $seen[$cursor] = true;
                        $depth++;

                        $order = DB::table('fragcity_vip_orders')
                            ->where('order_ref', $cursor)
                            ->where('steamid64', $steamId)
                            ->lockForUpdate()
                            ->first();
                        if (!$order || !in_array((string) $order->status, ['paid', 'provisioned'], true)) {
                            throw new RuntimeException('vip_refund_paid_order_not_found');
                        }

                        $window = (string) $order->purchase_type === 'upgrade'
                            ? (int) ($order->upgrade_remaining_seconds ?? 0)
                            : (int) $order->duration_seconds;
                        if ($window < 1 || $window > 7776000) {
                            throw new RuntimeException('vip_refund_window_invalid');
                        }

                        $amountUnits = (int) round(((float) $order->amount_rial) * 100);
                        $eligible = min($remainingSeconds, $window);
                        if ($eligible > 0 && $amountUnits > intdiv(PHP_INT_MAX, $eligible)) {
                            throw new RuntimeException('vip_refund_amount_out_of_safe_range');
                        }
                        $refundUnits += $eligible > 0
                            ? intdiv(($amountUnits * $eligible) + intdiv($window, 2), $window)
                            : 0;

                        $ordersToRevoke[] = (string) $order->order_ref;
                        $cursor = (string) $order->purchase_type === 'upgrade'
                            ? (string) ($order->upgrade_from_order_ref ?? '')
                            : '';
                        if ($cursor === '') {
                            $cursor = null;
                        }
                    }

                    // Any other captured-but-not-yet-provisioned Paymenter VIP order
                    // is fully refunded too; pending/unpaid invoices are not touched.
                    $pendingOrders = DB::table('fragcity_vip_orders')
                        ->where('steamid64', $steamId)
                        ->where('status', 'paid')
                        ->lockForUpdate()
                        ->get();
                    foreach ($pendingOrders as $pending) {
                        if (in_array((string) $pending->order_ref, $ordersToRevoke, true)) {
                            continue;
                        }
                        $amountUnits = (int) round(((float) $pending->amount_rial) * 100);
                        if ($refundUnits > PHP_INT_MAX - $amountUnits) {
                            throw new RuntimeException('vip_refund_amount_out_of_safe_range');
                        }
                        $refundUnits += $amountUnits;
                        $ordersToRevoke[] = (string) $pending->order_ref;
                    }
                }

                // Linux API records the revocation first and returns its immutable timestamp.
                // Retrying this exact operation returns the same audit event/order reference.
                $revocation = $this->requestVipCore($settings, [
                    'action' => 'revoke_membership',
                    'steamid64' => $steamId,
                ]);
                if (($revocation['revoked'] ?? false) !== true) {
                    throw new RuntimeException('vip_membership_not_active');
                }

                $revokedAt = (int) ($revocation['revoked_at'] ?? 0);
                $returnedOrderRef = $revocation['order_ref'] ?? null;
                if ($revokedAt < 1
                    || ($returnedOrderRef !== null
                        && (!is_string($returnedOrderRef) || !preg_match('/^[a-f0-9]{32}$/D', $returnedOrderRef)))) {
                    throw new RuntimeException('vip_revocation_response_invalid');
                }
                if ($paidOrderRef !== null && $returnedOrderRef !== $paidOrderRef) {
                    throw new RuntimeException('vip_revocation_order_mismatch');
                }

                $refundRef = substr(hash('sha256', $steamId . '|' . ($returnedOrderRef ?? 'manual') . '|' . $revokedAt), 0, 32);
                $existingRefund = DB::table('fragcity_vip_refunds')
                    ->where('refund_ref', $refundRef)
                    ->lockForUpdate()
                    ->first();
                if ($existingRefund) {
                    $balance = $user->credits()
                        ->where('currency_code', $settings['currency'])
                        ->value('amount');
                    return [
                        'revoked' => true,
                        'refund_ref' => $refundRef,
                        'refund_amount_rial' => number_format((float) $existingRefund->amount_rial, 2, '.', ''),
                        'wallet_balance' => number_format((float) ($balance ?? 0), 2, '.', ''),
                        'replayed' => true,
                    ];
                }

                $refundAmount = number_format($refundUnits / 100, 2, '.', '');
                $invoiceId = null;
                if ($refundUnits > 0) {
                    $credit = $user->credits()
                        ->where('currency_code', $settings['currency'])
                        ->lockForUpdate()
                        ->first();
                    if (!$credit) {
                        $credit = $user->credits()->create([
                            'currency_code' => $settings['currency'],
                            'amount' => '0.00',
                        ]);
                    }

                    // Credit is incremented in the same transaction as the
                    // refund ledger. The invoice item references Product (not
                    // Credit), so Paymenter's paid-invoice observer cannot add
                    // the same wallet amount a second time.
                    $credit->increment('amount', $refundAmount);

                    $invoice = new Invoice([
                        'user_id' => (int) $user->id,
                        'due_at' => now(),
                        'currency_code' => $settings['currency'],
                    ]);
                    $invoice->send_create_email = false;
                    $invoice->save();

                    $product = $this->loadVipProduct($settings);
                    $invoice->items()->create([
                        'reference_id' => (int) $product->id,
                        'reference_type' => Product::class,
                        'price' => $refundAmount,
                        'quantity' => 1,
                        'description' => 'FragCity VIP unused-time refund',
                    ]);
                    ExtensionHelper::addPayment(
                        $invoice,
                        null,
                        (float) $refundAmount,
                        null,
                        'fragcity-vip-refund:' . $refundRef,
                        InvoiceTransactionStatus::Succeeded,
                        false
                    );
                    $invoice->status = Invoice::STATUS_PAID;
                    $invoice->save();
                    $invoiceId = (int) $invoice->id;
                }

                DB::table('fragcity_vip_refunds')->insert([
                    'refund_ref' => $refundRef,
                    'steamid64' => $steamId,
                    'paymenter_user_id' => (int) $user->id,
                    'paymenter_invoice_id' => $invoiceId,
                    'revoked_order_ref' => $returnedOrderRef,
                    'amount_rial' => $refundAmount,
                    'currency_code' => $settings['currency'],
                    'status' => 'credited',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($ordersToRevoke) {
                    DB::table('fragcity_vip_orders')
                        ->whereIn('order_ref', $ordersToRevoke)
                        ->where('steamid64', $steamId)
                        ->whereIn('status', ['paid', 'provisioned'])
                        ->update([
                            'status' => 'revoked',
                            'revoked_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                $balance = $user->credits()
                    ->where('currency_code', $settings['currency'])
                    ->value('amount');

                return [
                    'revoked' => true,
                    'refund_ref' => $refundRef,
                    'order_ref' => (string) ($returnedOrderRef ?? ''),
                    'refund_amount_rial' => $refundAmount,
                    'wallet_balance' => number_format((float) ($balance ?? 0), 2, '.', ''),
                    'invoice_id' => $invoiceId,
                    'replayed' => false,
                ];
            }, 3);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'vip_revoke_cooldown:')) {
                $retryAfter = (int) substr($e->getMessage(), strlen('vip_revoke_cooldown:'));
                return response()->json([
                    'success' => false,
                    'error' => 'vip_revoke_cooldown',
                    'retry_after_seconds' => max(0, $retryAfter),
                ], 409);
            }

            $status = match ($e->getMessage()) {
                'fragcity_user_not_linked', 'linked_paymenter_user_missing' => 404,
                'vip_membership_not_active', 'vip_paid_membership_not_migrated',
                'vip_refund_upgrade_chain_invalid', 'vip_refund_paid_order_not_found' => 409,
                'vip_revoke_cooldown_unavailable', 'vipcore_unavailable',
                'vipcore_invalid_response' => 503,
                default => 500,
            };
            if ($status === 500) {
                Log::error('FragCity VIP revoke/refund failed.', [
                    'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                    'reason' => $e->getMessage(),
                ]);
            }
            return response()->json(['success' => false, 'error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            Log::error('FragCity VIP revoke/refund failed.', [
                'steamid_hash' => substr(hash('sha256', $steamId), 0, 16),
                'exception' => get_class($e),
            ]);
            return response()->json(['success' => false, 'error' => 'vip_refund_failed'], 500);
        }
    }

    private function vipStoreSettings(): array
    {
        $productId = (int) $this->config('vip_product_id');
        $levelOptionId = (int) $this->config('vip_level_option_id');
        $currency = strtoupper(trim((string) ($this->config('vip_currency_code') ?: $this->config('cs2_currency_code'))));
        $apiBase = rtrim(trim((string) $this->config('vipcore_api_base_url')), '/');
        $apiSecret = trim((string) $this->config('vipcore_shared_secret'));
        $planSettings = [
            7 => 'vip_plan_id_7_days',
            15 => 'vip_plan_id_15_days',
            30 => 'vip_plan_id_30_days',
            60 => 'vip_plan_id_60_days',
            90 => 'vip_plan_id_90_days',
        ];
        $planIds = [];
        foreach ($planSettings as $days => $setting) {
            $planIds[$days] = (int) $this->config($setting);
        }

        $parts = parse_url($apiBase);
        if (
            $productId < 1
            || $levelOptionId < 1
            || $currency === ''
            || in_array(0, $planIds, true)
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
            || !preg_match('/^[a-f0-9]{64,128}$/iD', $apiSecret)
        ) {
            throw new RuntimeException('vip_store_not_configured');
        }

        return [
            'product_id' => $productId,
            'level_option_id' => $levelOptionId,
            'currency' => $currency,
            'api_base' => $apiBase,
            'api_secret' => $apiSecret,
            'plan_ids' => $planIds,
        ];
    }

    private function loadVipProduct(array $settings): Product
    {
        $product = Product::query()
            ->with([
                'server',
                'plans.prices.currency',
                'configOptions.children.plans.prices.currency',
            ])
            ->find((int) $settings['product_id']);

        if (!$product || !(bool) $product->enabled || $product->server !== null) {
            throw new RuntimeException('vip_product_invalid');
        }

        $option = $product->configOptions->firstWhere('id', (int) $settings['level_option_id']);
        if (!$option || !in_array((string) $option->type, ['dropdown', 'select'], true)) {
            throw new RuntimeException('vip_level_option_invalid');
        }

        foreach ([1, 2, 3] as $level) {
            $matching = $option->children->filter(
                fn ($child): bool => trim((string) ($child->env_variable ?? '')) === (string) $level
            );
            if ($matching->count() !== 1) {
                throw new RuntimeException('vip_level_option_invalid');
            }
        }

        return $product;
    }

    private function resolveVipPlan(Product $product, array $settings, int $days): Plan
    {
        $planId = (int) ($settings['plan_ids'][$days] ?? 0);
        $plan = $product->plans->firstWhere('id', $planId);
        $expected = match ($days) {
            7 => [[7, 'day']],
            15 => [[15, 'day']],
            30 => [[30, 'day'], [1, 'month']],
            60 => [[60, 'day'], [2, 'month']],
            90 => [[90, 'day'], [3, 'month']],
            default => [],
        };

        $periodMatches = false;
        foreach ($expected as [$period, $unit]) {
            if ($plan && (int) $plan->billing_period === $period && (string) $plan->billing_unit === $unit) {
                $periodMatches = true;
                break;
            }
        }

        if (
            !$plan
            || in_array((string) $plan->type, ['free', 'one-time'], true)
            || !$periodMatches
        ) {
            throw new RuntimeException('vip_plan_invalid');
        }

        return $plan;
    }

    private function calculateVipPrice(
        Product $product,
        array $settings,
        ?User $user,
        int $level,
        int $days
    ): array {
        $option = $product->configOptions->firstWhere('id', (int) $settings['level_option_id']);
        $child = $option?->children->first(
            fn ($value): bool => trim((string) ($value->env_variable ?? '')) === (string) $level
        );
        if (!$child) {
            throw new RuntimeException('vip_level_option_invalid');
        }

        $plan = $this->resolveVipPlan($product, $settings, $days);
        $planPrice = $plan->price($settings['currency']);
        $childPrice = $child->price(
            null,
            (int) $plan->billing_period,
            (string) $plan->billing_unit,
            $settings['currency']
        );

        if (!$planPrice->available || !$childPrice->available) {
            throw new RuntimeException('vip_price_missing');
        }

        $baseRecurring = (float) $planPrice->price + (float) $childPrice->price;
        $setupFees = (float) ($planPrice->setup_fee ?? 0) + (float) ($childPrice->setup_fee ?? 0);
        $tierPrice = (int) round($baseRecurring + $setupFees);
        if ($tierPrice < 10 || $tierPrice % 10 !== 0 || $tierPrice > 10000000000000) {
            throw new RuntimeException('vip_price_invalid');
        }

        $taxed = new Price(
            [
                'price' => $baseRecurring,
                'setup_fee' => $setupFees,
                'currency' => $planPrice->currency,
            ],
            apply_exclusive_tax: true,
            tax: Settings::tax($user)
        );
        $amount = (float) $taxed->total;
        $amountRial = (int) round($amount);
        if (abs($amount - $amountRial) > 0.00001 || $amountRial < 10 || $amountRial % 10 !== 0) {
            throw new RuntimeException('vip_price_not_whole_toman');
        }

        return [
            'amount_rial' => $amountRial,
            'amount_toman' => intdiv($amountRial, 10),
            'tier_price_rial' => $tierPrice,
            'tax_rial' => $amountRial - $tierPrice,
            'plan_id' => (int) $plan->id,
            'config_value_id' => (int) $child->id,
            'duration_days' => $days,
        ];
    }

    private function quoteVipSelection(
        Product $product,
        array $settings,
        User $user,
        array $vipStatus,
        string $steamId,
        int $level,
        int $days
    ): array {
        $base = $this->calculateVipPrice($product, $settings, $user, $level, $days);
        $active = ($vipStatus['active'] ?? false) === true;
        $currentLevel = (int) ($vipStatus['level'] ?? 0);
        $currentExpires = (int) ($vipStatus['expires'] ?? 0);
        $paidPurchase = $vipStatus['paid_purchase'] ?? null;

        if (!$active) {
            return $base + ['purchase_type' => 'new', 'upgrade' => null];
        }

        if (is_array($paidPurchase)) {
            $sourceRef = strtolower((string) ($paidPurchase['order_ref'] ?? ''));
            $durationSeconds = (int) ($paidPurchase['duration_seconds'] ?? 0);
            $currentDurationDays = (int) round($durationSeconds / 86400);
            $sourceOrder = DB::table('fragcity_vip_orders')
                ->where('order_ref', $sourceRef)
                ->where('steamid64', $steamId)
                ->where('status', 'provisioned')
                ->first();

            if (
                !preg_match('/^[a-f0-9]{32}$/D', $sourceRef)
                || !$sourceOrder
                || (int) $sourceOrder->vip_level !== $currentLevel
                || (int) $sourceOrder->vip_expires_at !== $currentExpires
                || (int) $sourceOrder->duration_seconds !== $durationSeconds
                || !in_array($currentDurationDays, [7, 15, 30, 60, 90], true)
            ) {
                throw new RuntimeException('vip_paid_membership_not_migrated');
            }

            if ($level <= $currentLevel || $currentLevel > 2) {
                throw new RuntimeException('vip_paid_membership_upgrade_required');
            }
            if ($days !== $currentDurationDays || $currentExpires <= time()) {
                throw new RuntimeException('vip_upgrade_duration_mismatch');
            }

            $currentTierPrice = (int) ($paidPurchase['tier_price_rial'] ?? $sourceOrder->tier_price_rial);
            $difference = (int) $base['tier_price_rial'] - $currentTierPrice;
            if ($difference < 1) {
                throw new RuntimeException('vip_upgrade_price_invalid');
            }

            $remainingSeconds = max(0, $currentExpires - time());
            $proratedBase = (int) (round((($difference * $remainingSeconds) / $durationSeconds) / 1000) * 1000);
            if ($proratedBase < 10) {
                throw new RuntimeException('vip_upgrade_amount_too_small');
            }

            $plan = $this->resolveVipPlan($product, $settings, $days);
            $taxed = new Price(
                ['price' => $proratedBase, 'currency' => $plan->price($settings['currency'])->currency],
                apply_exclusive_tax: true,
                tax: Settings::tax($user)
            );
            $amount = (float) $taxed->total;
            $amountRial = (int) round($amount);
            if (abs($amount - $amountRial) > 0.00001 || $amountRial < 10 || $amountRial % 10 !== 0) {
                throw new RuntimeException('vip_price_not_whole_toman');
            }

            return [
                'amount_rial' => $amountRial,
                'amount_toman' => intdiv($amountRial, 10),
                'tier_price_rial' => (int) $base['tier_price_rial'],
                'tax_rial' => $amountRial - $proratedBase,
                'plan_id' => (int) $base['plan_id'],
                'config_value_id' => (int) $base['config_value_id'],
                'duration_days' => $days,
                'purchase_type' => 'upgrade',
                'upgrade' => [
                    'from_order_ref' => $sourceRef,
                    'from_level' => $currentLevel,
                    'from_expires' => $currentExpires,
                    'remaining_seconds' => $remainingSeconds,
                ],
            ];
        }

        if ($level < $currentLevel) {
            throw new RuntimeException('vip_membership_already_active');
        }

        return $base + ['purchase_type' => 'replacement', 'upgrade' => null];
    }

    private function vipLevelName(int $level): string
    {
        return match ($level) {
            1 => 'Bronze',
            2 => 'Silver',
            3 => 'Gold',
            default => 'VIP',
        };
    }

    private function requestVipCore(array $settings, array $payload): array
    {
        $base = rtrim((string) $settings['api_base'], '/');
        $path = '/v1/vipcore';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(32));
        $canonical = $timestamp . "\n" . $nonce . "\nPOST\n" . $path . "\n" . hash('sha256', $body);
        $signature = hash_hmac('sha256', $canonical, (string) $settings['api_secret']);

        $handle = curl_init($base . $path);
        if ($handle === false) {
            throw new RuntimeException('vipcore_unavailable');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-FragCity-Timestamp: ' . $timestamp,
                'X-FragCity-Nonce: ' . $nonce,
                'X-FragCity-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($handle);
        $curlError = curl_errno($handle);
        $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (!is_string($response) || $curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300) {
            throw new RuntimeException('vipcore_unavailable');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE || ($decoded['success'] ?? false) !== true) {
            $error = is_array($decoded) && is_string($decoded['error'] ?? null)
                ? $decoded['error']
                : 'vipcore_unavailable';
            if ($httpStatus === 409 && $error === 'membership_already_active') {
                throw new RuntimeException('vip_membership_already_active');
            }
            if ($httpStatus === 409 && $error === 'membership_not_active') {
                throw new RuntimeException('vip_membership_not_active');
            }
            if ($httpStatus === 409 && $error === 'membership_upgrade_source_changed') {
                throw new RuntimeException('vip_upgrade_source_changed');
            }
            throw new RuntimeException('vipcore_unavailable');
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('vipcore_invalid_response');
        }

        return $data;
    }

    private function vipOrderResponse(object $order, bool $replayed): array
    {
        $paidAt = $order->paid_at ?? null;
        $provisionedAt = $order->provisioned_at ?? null;
        $createdAt = $order->created_at ?? null;

        return [
            'id' => (int) $order->id,
            'order_ref' => (string) $order->order_ref,
            'invoice_id' => (int) ($order->paymenter_invoice_id ?? 0),
            'vip_level' => (int) $order->vip_level,
            'duration_days' => (int) $order->duration_days,
            'amount_rial' => number_format((float) $order->amount_rial, 2, '.', ''),
            'amount_toman' => number_format(((float) $order->amount_rial) / 10, 2, '.', ''),
            'status' => (string) $order->status,
            'payment_method' => 'paymenter_wallet',
            'purchase_type' => (string) ($order->purchase_type ?? 'new'),
            'upgrade_from_level' => isset($order->upgrade_from_level) ? (int) $order->upgrade_from_level : null,
            'created_at' => $createdAt ? (string) $createdAt : null,
            'paid_at' => $paidAt ? (string) $paidAt : null,
            'provisioned_at' => $provisionedAt ? (string) $provisionedAt : null,
            'revoke_available_at' => $paidAt ? strtotime((string) $paidAt) + 3600 : null,
            'provision_pending' => (string) $order->status === 'paid',
            'expires' => (int) ($order->vip_expires_at ?? 0),
            'replayed' => $replayed,
        ];
    }

    private function provisionVipOrder(array $settings, object $order, bool $replayed): JsonResponse
    {
        try {
            $upgrade = null;
            if ((string) $order->purchase_type === 'upgrade') {
                $upgrade = [
                    'from_order_ref' => (string) $order->upgrade_from_order_ref,
                    'from_level' => (int) $order->upgrade_from_level,
                    'from_expires' => (int) $order->upgrade_from_expires,
                    'duration_seconds' => (int) $order->duration_seconds,
                ];
            }

            $grant = $this->requestVipCore($settings, [
                'action' => 'grant_paid_membership',
                'order_ref' => (string) $order->order_ref,
                'steamid64' => (string) $order->steamid64,
                'player_name' => (string) $order->player_name,
                'level' => (int) $order->vip_level,
                'duration_seconds' => (int) $order->duration_seconds,
                'tier_price_rial' => (int) $order->tier_price_rial,
                'upgrade' => $upgrade,
            ]);

            if (
                ($grant['granted'] ?? false) !== true
                || (string) ($grant['order_ref'] ?? '') !== (string) $order->order_ref
                || (int) ($grant['level'] ?? 0) !== (int) $order->vip_level
                || (int) ($grant['duration_seconds'] ?? 0) !== (int) $order->duration_seconds
                || !is_numeric($grant['expires'] ?? null)
            ) {
                throw new RuntimeException('vipcore_invalid_response');
            }

            DB::table('fragcity_vip_orders')->where('order_ref', (string) $order->order_ref)->update([
                'status' => 'provisioned',
                'vip_granted_at' => (int) ($grant['granted_at'] ?? time()),
                'vip_expires_at' => (int) $grant['expires'],
                'provisioned_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

            $fresh = DB::table('fragcity_vip_orders')->where('order_ref', (string) $order->order_ref)->first();
            return response()->json([
                'success' => true,
                'data' => $this->vipOrderResponse($fresh, $replayed),
            ], $replayed ? 200 : 201);
        } catch (Throwable $e) {
            DB::table('fragcity_vip_orders')->where('order_ref', (string) $order->order_ref)->update([
                'provision_attempts' => DB::raw('provision_attempts + 1'),
                'last_error' => $e instanceof RuntimeException ? substr($e->getMessage(), 0, 96) : 'vipcore_unavailable',
                'updated_at' => now(),
            ]);
            Log::warning('Paymenter VIP order was paid but VIPCore provisioning is pending.', [
                'order_ref' => (string) $order->order_ref,
                'steamid_hash' => substr(hash('sha256', (string) $order->steamid64), 0, 16),
                'exception' => get_class($e),
            ]);

            $fresh = DB::table('fragcity_vip_orders')->where('order_ref', (string) $order->order_ref)->first();
            return response()->json([
                'success' => true,
                'data' => $this->vipOrderResponse($fresh, $replayed) + [
                    'message' => 'پرداخت ثبت شده است؛ فعال‌سازی VIP در صف پیگیری قرار دارد. مبلغ دوباره کسر نمی‌شود.',
                ],
            ], 202);
        }
    }



    /**
     * Start the browser-based SSO hand-off from Paymenter to the main FragCity
     * site. The state is held only in the Paymenter session.
     */
    public function startSso(Request $request): RedirectResponse
    {
        abort_unless($this->isSsoEnabled(), 404);

        $websiteOrigin = $this->ssoWebsiteOrigin();
        abort_if($websiteOrigin === null, 503, 'FragCity SSO is not configured.');

        $returnTo = $this->safeSsoReturnPath($request->query('redirect', '/'));

        // Always prove the FragCity identity, even when a Paymenter session
        // already exists. The callback accepts that session only when it is
        // the exact same linked Paymenter user; a different account is rejected.
        $state = bin2hex(random_bytes(32));
        Session::put('fragcity_sso_state', $state);
        Session::put('fragcity_sso_return_to', $returnTo);

        return redirect()->away(
            $websiteOrigin . '/api/paymenter-sso.php?state=' . rawurlencode($state)
        );
    }

    /**
     * Consume a short-lived, one-time HMAC ticket issued by the FragCity PHP
     * endpoint. A browser cannot choose a Paymenter user ID or SteamID.
     */
    public function completeSso(Request $request): RedirectResponse
    {
        abort_unless($this->isSsoEnabled(), 404);

        $websiteOrigin = $this->ssoWebsiteOrigin();
        $paymenterOrigin = $this->normalizeHttpsOrigin((string) config('app.url'));
        abort_if($websiteOrigin === null || $paymenterOrigin === null, 503, 'FragCity SSO is not configured.');

        // This is a cross-origin form POST but remains same-site (both hosts are
        // under fragcity.ir). Requiring the expected Origin adds a browser-side
        // check on top of the per-session random state and signed ticket.
        $requestOrigin = $this->normalizeHttpsOrigin((string) $request->header('Origin', ''));
        if ($requestOrigin === null || !hash_equals($websiteOrigin, $requestOrigin)) {
            return redirect()->route('login')->with('error', 'FragCity SSO origin validation failed.');
        }

        $expectedState = Session::get('fragcity_sso_state');
        $postedState = $request->input('state');
        $ticket = $request->input('ticket');

        if (
            !is_string($expectedState)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $expectedState)
            || !is_string($postedState)
            || !hash_equals($expectedState, $postedState)
            || !is_string($ticket)
            || strlen($ticket) > 4096
        ) {
            return redirect()->route('login')->with('error', 'FragCity SSO request expired. Please try again.');
        }

        $ticketParts = explode('.', $ticket);
        if (
            count($ticketParts) !== 2
            || !preg_match('/\A[a-f0-9]{64}\z/D', $ticketParts[1])
            || $ticketParts[0] === ''
        ) {
            return redirect()->route('login')->with('error', 'FragCity SSO ticket is invalid.');
        }

        $secret = (string) $this->config('shared_secret');
        if (!preg_match('/\A[a-f0-9]{64,256}\z/iD', $secret)) {
            return redirect()->route('login')->with('error', 'FragCity SSO is not configured.');
        }

        $expectedSignature = hash_hmac('sha256', $ticketParts[0], $secret);
        if (!hash_equals($expectedSignature, strtolower($ticketParts[1]))) {
            return redirect()->route('login')->with('error', 'FragCity SSO signature validation failed.');
        }

        $padding = str_repeat('=', (4 - (strlen($ticketParts[0]) % 4)) % 4);
        $decoded = base64_decode(
            strtr($ticketParts[0], '-_', '+/') . $padding,
            true
        );
        if ($decoded === false) {
            return redirect()->route('login')->with('error', 'FragCity SSO ticket is invalid.');
        }

        try {
            $claims = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $claims = null;
        }

        $now = time();
        if (
            !is_array($claims)
            || ($claims['iss'] ?? null) !== $websiteOrigin
            || ($claims['aud'] ?? null) !== $paymenterOrigin
            || !is_string($claims['sub'] ?? null)
            || !preg_match('/\A[0-9]{17}\z/D', $claims['sub'])
            || !is_int($claims['user_id'] ?? null)
            || $claims['user_id'] < 1
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > ($now + 30)
            || $claims['iat'] < ($now - 120)
            || $claims['exp'] <= $now
            || $claims['exp'] <= $claims['iat']
            || ($claims['exp'] - $claims['iat']) > 120
            || !is_string($claims['jti'] ?? null)
            || !preg_match('/\A[a-f0-9]{32}\z/D', $claims['jti'])
            || !is_string($claims['state'] ?? null)
            || !hash_equals($expectedState, $claims['state'])
            || !is_bool($claims['remember'] ?? null)
        ) {
            return redirect()->route('login')->with('error', 'FragCity SSO ticket is expired or invalid.');
        }

        $steamId = $claims['sub'];
        $paymenterUserId = $claims['user_id'];
        $returnTo = $this->safeSsoReturnPath(
            Session::pull('fragcity_sso_return_to', '/')
        );

        // A unique SHA-256 JTI key makes a valid ticket consumable only once.
        // Ticket contents and SteamID values are deliberately not logged.
        try {
            DB::table('fragcity_sso_used_tickets')
                ->where('expires_at', '<', now())
                ->delete();

            DB::table('fragcity_sso_used_tickets')->insert([
                'jti_hash' => hash('sha256', $claims['jti']),
                'expires_at' => now()->setTimestamp($claims['exp']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            Session::forget('fragcity_sso_state');

            return redirect()->route('login')->with('error', 'This FragCity SSO request has already been used or cannot be verified.');
        }

        $link = DB::table('fragcity_user_links')
            ->where('steamid64', $steamId)
            ->where('user_id', $paymenterUserId)
            ->first();

        $user = User::query()->find($paymenterUserId);
        if (!$link || !$user) {
            Session::forget('fragcity_sso_state');

            return redirect()->route('login')->with('error', 'This FragCity account is not linked to a Billing account.');
        }

        Session::forget('fragcity_sso_state');

        // FragCity is the identity source of truth. A valid one-time ticket
        // explicitly requests this linked identity, so replace any other
        // existing Billing customer session instead of falling through the
        // guest middleware and accidentally landing on /dashboard.
        if (Auth::check() && (int) Auth::id() !== (int) $user->id) {
            app(\\App\\Actions\\Auth\\Logout::class)->execute();
        }

        $destination = $returnTo === '/' ? route('home') : $returnTo;

        if (Auth::check()) {
            return redirect()->to($destination);
        }

        // Respect Paymenter's native two-factor flow; SSO must never bypass it.
        if ($user->tfa_secret) {
            Session::put('2fa', [
                'user_id' => $user->id,
                'remember' => $claims['remember'],
                'expires' => now()->addMinutes(5),
            ]);

            return redirect()->route('2fa');
        }

        (new \App\Actions\Auth\Login)->execute($user, $claims['remember']);

        return redirect()->to($destination);
    }

    private function isSsoEnabled(): bool
    {
        $value = $this->config('sso_enabled');

        return $value === null
            ? true
            : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function ssoWebsiteOrigin(): ?string
    {
        return $this->normalizeHttpsOrigin((string) ($this->config('website_base_url') ?: 'https://fragcity.ir'));
    }

    private function normalizeHttpsOrigin(string $value): ?string
    {
        $value = trim($value);
        $parts = parse_url($value);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
            || (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535))
        ) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return 'https://' . $host . $port;
    }

    private function safeSsoReturnPath(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 1024) {
            return route('home');
        }

        $value = trim($value);
        $parts = parse_url($value);

        if (
            !str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, chr(92))
            || preg_match('/[\r\n\x00-\x1F\x7F]/', $value)
            || !is_array($parts)
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return route('home');
        }

        return $value;
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
