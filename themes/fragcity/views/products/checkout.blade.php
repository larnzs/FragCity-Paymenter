@php
    $availablePlans = $product->availablePlans();
    $checkoutFields = $this->getCheckoutConfig();

    // Itemize every selection using Paymenter's own Price calculations.
    // Never render the GSLT token value into the checkout summary.
    $checkoutBreakdown = [];
    $planPrice = $plan->price();
    $checkoutBreakdown[] = [
        'label' => $plan->name,
        'value' => 'پلن پایه',
        'price' => $planPrice->formatted->price,
        'setup_fee' => ($planPrice->setup_fee ?? 0) > 0 ? $planPrice->formatted->setup_fee : null,
        'kind' => 'priced',
    ];

    foreach ($product->configOptions as $configOption) {
        $optionEnv = strtoupper((string) ($configOption->env_variable ?? ''));
        $optionType = (string) $configOption->type;
        $optionValue = $optionEnv === 'STEAM_ACC'
            ? ($this->sensitiveConfigOptions[$configOption->id] ?? null)
            : ($this->configOptions[$configOption->id] ?? null);

        if (in_array($optionType, ['text', 'password', 'email', 'number'], true)) {
            if ($optionValue === null || $optionValue === '') continue;
            $isSecret = $optionEnv === 'STEAM_ACC';
            $checkoutBreakdown[] = [
                'label' => $configOption->name,
                'value' => $isSecret ? 'ثبت شده (مخفی)' : (string) $optionValue,
                'price' => null,
                'setup_fee' => null,
                'kind' => $isSecret ? 'secret' : 'text',
            ];
            continue;
        }

        if (!in_array($optionType, ['select', 'radio'], true) || $optionValue === null || $optionValue === '') continue;
        $selectedChild = $configOption->children->firstWhere('id', $optionValue);
        if (!$selectedChild) continue;
        $selectedPrice = $selectedChild->price(
            billing_period: $plan->billing_period,
            billing_unit: $plan->billing_unit
        );

        $checkoutBreakdown[] = [
            'label' => $configOption->name,
            'value' => $selectedChild->name,
            'price' => $selectedPrice->available ? $total->format($selectedPrice->price) : 'ناموجود',
            'setup_fee' => $selectedPrice->available && ($selectedPrice->setup_fee ?? 0) > 0
                ? $total->format($selectedPrice->setup_fee)
                : null,
            'kind' => 'priced',
        ];
    }

    foreach ($checkoutFields as $field) {
        $fieldName = (string) ($field['name'] ?? '');
        $fieldValue = $this->checkoutConfig[$fieldName] ?? ($field['default'] ?? null);
        if ($fieldName === '' || $fieldValue === null || $fieldValue === '') continue;
        $fieldOptions = $field['options'] ?? [];
        $fieldLabel = is_array($fieldOptions) && array_key_exists((string) $fieldValue, $fieldOptions)
            ? $fieldOptions[(string) $fieldValue]
            : (string) $fieldValue;
        $checkoutBreakdown[] = [
            'label' => (string) ($field['label'] ?? $fieldName),
            'value' => $fieldLabel,
            'price' => null,
            'setup_fee' => null,
            'kind' => 'included',
        ];
    }
@endphp

<div class="container fc-checkout-page" dir="rtl">
    <nav class="fc-store-breadcrumbs" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">فروشگاه</a><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        <a href="{{ route('category.show', ['category' => $category->slug]) }}" wire:navigate>{{ $category->name }}</a>
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        <a href="{{ route('products.show', ['category' => $category->slug, 'product' => $product->slug]) }}" wire:navigate>{{ $product->name }}</a>
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span aria-current="page">پیکربندی سفارش</span>
    </nav>

    <header class="fc-checkout-hero">
        <div class="fc-checkout-hero__identity">
            <span class="fc-checkout-hero__icon"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
            <div>
                <span class="fc-store-kicker">FRAGCITY / CONFIGURE YOUR SERVER</span>
                <h1>پیکربندی سرور</h1>
                <p>{{ $product->name }} <span aria-hidden="true">·</span> پلن و منابع موردنیازت را انتخاب کن.</p>
            </div>
        </div>
        <div class="fc-checkout-hero__steps" aria-label="مراحل خرید">
            <span class="is-complete"><i class="fa-solid fa-check" aria-hidden="true"></i> انتخاب محصول</span>
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            <span class="is-current"><i class="fa-solid fa-sliders" aria-hidden="true"></i> پیکربندی</span>
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            <span><i class="fa-solid fa-lock" aria-hidden="true"></i> پرداخت</span>
        </div>
    </header>

    <div class="fc-checkout-layout">
        <main class="fc-checkout-main">
            <section class="fc-checkout-section fc-checkout-plan-section" aria-labelledby="fcCheckoutPlanTitle">
                <div class="fc-checkout-section__heading">
                    <span class="fc-checkout-section__index">01</span>
                    <div>
                        <span class="fc-store-kicker">SUBSCRIPTION</span>
                        <h2 id="fcCheckoutPlanTitle">انتخاب پلن و مدت سرویس</h2>
                        <p>پلن موردنظرت را انتخاب کن؛ قیمت و جمع سفارش با انتخاب‌های تو به‌روزرسانی می‌شود.</p>
                    </div>
                </div>

                @if ($availablePlans->count() > 1)
                    <x-form.select wire:model.live="plan_id" name="plan_id" label="پلن سرویس" divClass="fc-checkout-plan-control">
                        @foreach ($availablePlans as $availablePlan)
                            <option value="{{ $availablePlan->id }}">
                                {{ $availablePlan->name }} - {{ $availablePlan->price()->formatted->price }}
                                @if ($availablePlan->price()->has_setup_fee)
                                    + {{ $availablePlan->price()->formatted->setup_fee }} {{ __('product.setup_fee') }}
                                @endif
                            </option>
                        @endforeach
                    </x-form.select>
                @else
                    <div class="fc-checkout-selected-plan">
                        <span class="fc-checkout-selected-plan__icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                        <span><small>پلن انتخاب‌شده</small><strong>{{ $plan->name }}</strong></span>
                        <b>{{ $plan->price()->formatted->price }}</b>
                    </div>
                @endif
            </section>

            <section class="fc-checkout-section" aria-labelledby="fcCheckoutResourcesTitle">
                <div class="fc-checkout-section__heading">
                    <span class="fc-checkout-section__index">02</span>
                    <div>
                        <span class="fc-store-kicker">SERVER RESOURCES & OPTIONS</span>
                        <h2 id="fcCheckoutResourcesTitle">منابع و امکانات سرور</h2>
                        <p>ظرفیت، حافظه و امکانات دلخواهت را از مقادیر مجاز این محصول انتخاب کن.</p>
                    </div>
                </div>

                <div class="fc-checkout-options-grid">
                    @foreach ($product->configOptions as $configOption)
                        @php
                            $showPriceTag = $configOption->children->filter(fn ($value) => !$value->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->is_free)->count() > 0;
                        @endphp
                        <div class="fc-checkout-option-card">
                            <x-form.configoption :config="$configOption" :name="'configOptions.' . $configOption->id" :showPriceTag="$showPriceTag" :plan="$plan">
                                @if ($configOption->type == 'select')
                                    @foreach ($configOption->children as $configOptionValue)
                                        <option value="{{ $configOptionValue->id }}">
                                            {{ $configOptionValue->name }}
                                            {{ ($showPriceTag && $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? ' - ' . $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : '' }}
                                        </option>
                                    @endforeach
                                @elseif($configOption->type == 'radio')
                                    @foreach ($configOption->children as $configOptionValue)
                                        <div class="flex items-center gap-2">
                                            <input type="radio" id="{{ $configOptionValue->id }}" name="{{ $configOption->id }}"
                                                wire:model.live="configOptions.{{ $configOption->id }}" value="{{ $configOptionValue->id }}" />
                                            <label for="{{ $configOptionValue->id }}">
                                                {{ $configOptionValue->name }}
                                                {{ ($showPriceTag && $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available) ? ' - ' . $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : '' }}
                                            </label>
                                        </div>
                                    @endforeach
                                @endif
                            </x-form.configoption>
                        </div>
                    @endforeach
                </div>
            </section>

            @if (count($checkoutFields))
                <section class="fc-checkout-section" aria-labelledby="fcCheckoutStartupTitle">
                    <div class="fc-checkout-section__heading">
                        <span class="fc-checkout-section__index">03</span>
                        <div>
                            <span class="fc-store-kicker">GAME SERVER SETUP</span>
                            <h2 id="fcCheckoutStartupTitle">تنظیمات راه‌اندازی</h2>
                            <p>مقادیر لازم برای راه‌اندازی اولیه سرور را بررسی و تکمیل کن.</p>
                        </div>
                    </div>

                    <div class="fc-checkout-options-grid fc-checkout-options-grid--startup">
                        @foreach ($checkoutFields as $configOption)
                            @php $configOption = (object) $configOption; @endphp
                            <div class="fc-checkout-option-card {{ in_array(strtoupper((string) $configOption->name), ['SRCDS_MAP', 'COOLDOWN'], true) ? 'fc-checkout-option-card--tall' : '' }}">
                                <x-form.configoption :config="$configOption" :name="'checkoutConfig.' . $configOption->name">
                                    @if ($configOption->type == 'select')
                                        @foreach ($configOption->options as $configOptionValue => $configOptionValueName)
                                            <option value="{{ $configOptionValue }}">{{ $configOptionValueName }}</option>
                                        @endforeach
                                    @elseif($configOption->type == 'radio')
                                        @foreach ($configOption->options as $configOptionValue => $configOptionValueName)
                                            <div class="flex items-center gap-2">
                                                <input type="radio" id="{{ $configOptionValue }}" name="{{ $configOption->name }}"
                                                    wire:model.live="checkoutConfig.{{ $configOption->name }}" value="{{ $configOptionValue }}" />
                                                <label for="{{ $configOptionValue }}">{{ $configOptionValueName }}</label>
                                            </div>
                                        @endforeach
                                    @endif
                                </x-form.configoption>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        <aside class="fc-checkout-summary" aria-labelledby="fcCheckoutSummaryTitle">
            <div class="fc-checkout-summary__heading">
                <span class="fc-checkout-summary__icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                <div><span class="fc-store-kicker">ORDER SUMMARY</span><h2 id="fcCheckoutSummaryTitle">{{ __('product.order_summary') }}</h2></div>
            </div>

            <div class="fc-checkout-summary__product">
                @if ($product->image)
                    <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" loading="lazy">
                @else
                    <span class="fc-checkout-summary__product-placeholder"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                @endif
                <span><small>{{ $category->name }}</small><strong>{{ $product->name }}</strong></span>
            </div>

            <div class="fc-checkout-summary__plan"><span>پلن فعلی</span><strong>{{ $plan->name }}</strong></div>

            <section class="fc-checkout-summary__breakdown" aria-label="ریزفاکتور انتخاب‌ها">
                <div class="fc-checkout-summary__breakdown-heading"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>ریزفاکتور انتخاب‌ها</span></div>
                @foreach ($checkoutBreakdown as $line)
                    <div class="fc-checkout-summary__breakdown-line">
                        <div class="fc-checkout-summary__breakdown-copy"><strong>{{ $line['label'] }}</strong><small>{{ $line['value'] }}</small></div>
                        <div class="fc-checkout-summary__breakdown-price">
                            @if ($line['price'] !== null)
                                <strong>{{ $line['price'] }}</strong>
                            @elseif ($line['kind'] === 'secret')
                                <span class="fc-checkout-summary__included">محرمانه</span>
                            @else
                                <span class="fc-checkout-summary__included">بدون هزینه جداگانه</span>
                            @endif
                            @if ($line['setup_fee'])<small>راه‌اندازی: {{ $line['setup_fee'] }}</small>@endif
                        </div>
                    </div>
                @endforeach
            </section>

            <div class="fc-checkout-summary__lines">
                <div class="fc-checkout-summary__line"><span>{{ __('invoices.subtotal') }}</span><strong>{{ $total->format($total->subtotal) }}</strong></div>
                @if ($total->total_tax > 0)
                    <div class="fc-checkout-summary__line">
                        <span>{{ \App\Classes\Settings::tax()->name }} ({{ \App\Classes\Settings::tax()->rate }}%)</span>
                        <strong>{{ $total->formatted->total_tax }}</strong>
                    </div>
                @endif
                @if ($total->setup_fee > 0 && $plan->type == 'recurring')
                    <div class="fc-checkout-summary__line"><span>{{ __('product.setup_fee') }}</span><strong>{{ $total->format($total->setup_fee) }}</strong></div>
                @endif
            </div>

            <div class="fc-checkout-summary__total">
                <span>{{ __('product.total_today') }}</span><strong>{{ $total }}</strong>
            </div>

            @if ($total->setup_fee > 0 && $plan->type == 'recurring')
                <div class="fc-checkout-summary__after">
                    <span>{{ __('product.then_after_x', ['time' => $plan->billing_period . ' ' . trans_choice(__('services.billing_cycles.' . $plan->billing_unit), $plan->billing_period)]) }}</span>
                    <strong>{{ $total->format($total->price) }}</strong>
                </div>
            @endif

            @if (($product->stock > 0 || !$product->stock) && $product->price()->available)
                <div class="fc-checkout-summary__submit">
                    <x-button.primary type="button" class="fc-checkout-submit" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout">
                        <x-loading target="checkout" />
                        <span wire:loading.remove wire:target="checkout">{{ __('product.checkout') }}</span>
                    </x-button.primary>
                </div>
            @endif

            <div class="fc-checkout-summary__secure-note">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>مبلغ نهایی و گزینه‌های خرید در Paymenter اعتبارسنجی می‌شوند.</span>
            </div>
        </aside>
    </div>
</div>
