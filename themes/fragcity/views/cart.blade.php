<div class="container mt-14 fc-portal-page fc-cart-page" dir="rtl">
    <x-navigation.breadcrumb />
    <header class="fc-portal-heading fc-cart-hero">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / SHOPPING CART</span>
            <h1>سبد خرید</h1>
            <p>سفارش خود را مرور کن و پیش از پرداخت، گزینه‌ها و مبلغ نهایی را بررسی کن.</p>
        </div>
        <div class="fc-cart-hero__count">
            <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i>
            <span>{{ Cart::items()->count() }} آیتم</span>
        </div>
    </header>

    @if (Cart::items()->count() === 0)
        <section class="fc-cart-empty">
            <span><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span>
            <h2>{{ __('product.empty_cart') }}</h2>
            <p>هنوز محصولی به سبد خرید اضافه نکرده‌ای.</p>
            <a href="{{ route('home') }}" wire:navigate>بازگشت به فروشگاه <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </section>
    @else
        <div class="fc-cart-layout">
            <section class="fc-cart-items" aria-label="اقلام سبد خرید">
                <div class="fc-cart-section-heading">
                    <div><span class="fc-portal-kicker">YOUR ORDER</span><h2>محصولات انتخاب‌شده</h2></div>
                    <span>{{ Cart::items()->count() }} مورد</span>
                </div>

                @foreach (Cart::items() as $item)
                    <article class="fc-cart-item">
                        <div class="fc-cart-item__main">
                            <span class="fc-cart-item__icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span>
                            <div class="fc-cart-item__content">
                                <span class="fc-cart-item__eyebrow">{{ $item->product->category->name ?? 'FRAGCITY / GAME SERVERS' }}</span>
                                <h3>{{ $item->product->name }}</h3>
                                <div class="fc-cart-item__options">
                                    @foreach ($item->config_options as $option)
                                        @php
                                            $optionEnv = strtoupper((string) ($option['option_env_variable'] ?? ''));
                                            $optionName = (string) ($option['option_name'] ?? 'تنظیم');
                                            $optionValue = $option['value_name'] ?? '—';
                                            $isSecretOption = $optionEnv === 'STEAM_ACC';
                                        @endphp
                                        @if ($optionValue !== null && $optionValue !== '')
                                            <span class="fc-cart-option">
                                                <small>{{ $optionName }}</small>
                                                <strong>{{ $isSecretOption ? 'ثبت شده (مخفی)' : $optionValue }}</strong>
                                            </span>
                                        @endif
                                    @endforeach
                                    @foreach (($item->checkout_config ?? []) as $fieldName => $fieldValue)
                                        @if ($fieldValue !== null && $fieldValue !== '')
                                            <span class="fc-cart-option">
                                                <small>{{ $fieldName }}</small>
                                                <strong>{{ $fieldValue }}</strong>
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="fc-cart-item__side">
                            <div class="fc-cart-item__price">
                                <small>مبلغ این ردیف</small>
                                <strong>{{ $item->price->format($item->price->total * $item->quantity) }}</strong>
                                @if ($item->quantity > 1)
                                    <span>{{ $item->price }} برای هر عدد</span>
                                @endif
                            </div>

                            <div class="fc-cart-item__actions">
                                @if ($item->product->allow_quantity == 'combined')
                                    <div class="fc-cart-quantity" aria-label="تعداد">
                                        <x-button.secondary wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="!w-fit">−</x-button.secondary>
                                        <span>{{ $item->quantity }}</span>
                                        <x-button.secondary wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="!w-fit">+</x-button.secondary>
                                    </div>
                                @endif
                                <a href="{{ route('products.checkout', [$item->product->category, $item->product, 'edit' => $item->id]) }}" wire:navigate class="fc-cart-edit">
                                    <i class="fa-solid fa-sliders" aria-hidden="true"></i> {{ __('product.edit') }}
                                </a>
                                <button type="button" wire:click="removeProduct({{ $item->id }})" class="fc-cart-remove">
                                    <span wire:loading wire:target="removeProduct({{ $item->id }})"><x-ri-loader-5-fill class="size-4 animate-spin" /></span>
                                    <span wire:loading.remove wire:target="removeProduct({{ $item->id }})"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> {{ __('product.remove') }}</span>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <aside class="fc-cart-summary" aria-label="خلاصه سفارش">
                <div class="fc-cart-summary__heading">
                    <span><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                    <div><small>ORDER SUMMARY</small><h2>{{ __('product.order_summary') }}</h2></div>
                </div>

                <div class="fc-cart-coupon">
                    @if(!$coupon)
                        <label>کد تخفیف</label>
                        <div class="fc-cart-coupon__entry">
                            <x-form.input wire:model="coupon" name="coupon" placeholder="کد تخفیف را وارد کن" />
                            <x-button.primary wire:click="applyCoupon" wire:loading.attr="disabled" class="!w-fit">
                                <x-loading target="applyCoupon" />
                                <span wire:loading.remove wire:target="applyCoupon">{{ __('product.apply') }}</span>
                            </x-button.primary>
                        </div>
                    @else
                        <div class="fc-cart-coupon__applied">
                            <span><i class="fa-solid fa-ticket" aria-hidden="true"></i> {{ $coupon->code }}</span>
                            <x-button.secondary wire:click="removeCoupon" class="!w-fit">{{ __('product.remove') }}</x-button.secondary>
                        </div>
                    @endif
                </div>

                <div class="fc-cart-summary__lines">
                    <div><span>{{ __('invoices.subtotal') }}</span><strong>{{ $total->format($total->subtotal) }}</strong></div>
                    @if ($total->tax > 0)
                        <div><span>{{ AppClassesSettings::tax()->name }} ({{ AppClassesSettings::tax()->rate }}%)</span><strong>{{ $total->format($total->tax) }}</strong></div>
                    @endif
                </div>

                <div class="fc-cart-summary__total">
                    <span>{{ __('invoices.total') }}</span><strong>{{ $total->format($total->total) }}</strong>
                </div>

                @if(config('settings.tos'))
                    <div class="fc-cart-tos">
                        <x-form.checkbox wire:model="tos" name="tos">
                            {{ __('product.tos') }}
                            <a href="{{ config('settings.tos') }}" target="_blank" rel="noopener noreferrer">{{ __('product.tos_link') }}</a>
                        </x-form.checkbox>
                    </div>
                @endif

                <x-button.primary wire:click="checkout" class="fc-cart-checkout" wire:loading.attr="disabled">
                    <x-loading target="checkout" />
                    <span wire:loading.remove wire:target="checkout">{{ __('product.checkout') }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></span>
                </x-button.primary>
                <p class="fc-cart-secure"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> پرداخت و مبلغ نهایی به‌صورت امن در Paymenter اعتبارسنجی می‌شود.</p>
            </aside>
        </div>
    @endif
</div>
