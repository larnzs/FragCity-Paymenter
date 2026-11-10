{{-- /var/www/paymenter-dev/themes/default/views/products/checkout.blade.php --}}

<div class="min-h-screen">

    {{-- TOP HEADER BOX (BACKGROUND IMAGE INSIDE THE BOX ONLY) --}}
    <div class="container px-6 pt-10">
        <div class="mx-auto max-w-[1200px]">
            <div class="relative overflow-hidden rounded-2xl border border-neutral bg-background-secondary/40">
                @if (!empty($product->image))
                    <img
                        src="{{ Storage::url($product->image) }}"
                        alt="{{ $product->name }}"
                        class="absolute inset-0 w-full h-full object-cover"
                    />
                    <div class="absolute inset-0 bg-black/70"></div>
                @endif

                <div class="relative p-6 md:p-8">
                    <h1 class="text-2xl font-semibold text-white">
                        {{ $product->name }}
                    </h1>

                    @if (!empty($product->description))
                        <div class="mt-3 text-sm text-white/80 max-w-3xl">
                            {!! $product->description !!}
                        </div>
                    @endif

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-neutral bg-black/40 px-4 py-3">
                            <div class="text-xs text-primary-400">Includes</div>
                            <div class="text-sm font-semibold text-white">Instant Setup</div>
                        </div>
                        <div class="rounded-xl border border-neutral bg-black/40 px-4 py-3">
                            <div class="text-xs text-primary-400">Includes</div>
                            <div class="text-sm font-semibold text-white">Full Control Panel</div>
                        </div>
                        <div class="rounded-xl border border-neutral bg-black/40 px-4 py-3">
                            <div class="text-xs text-primary-400">Includes</div>
                            <div class="text-sm font-semibold text-white">DDoS Protected</div>
                        </div>
                    </div>

                    @if ($product->availablePlans()->count() > 1)
                        <div class="mt-6 max-w-md">
                            <x-form.select wire:model.live="plan_id" name="plan_id" label="{{ __('Select a plan') }}">
                                @foreach ($product->availablePlans() as $availablePlan)
                                    <option value="{{ $availablePlan->id }}">
                                        {{ $availablePlan->name }} -
                                        {{ $availablePlan->price()->formatted->price }}
                                        @if ($availablePlan->price()->has_setup_fee)
                                            + {{ $availablePlan->price()->formatted->setup_fee }} {{ __('product.setup_fee') }}
                                        @endif
                                    </option>
                                @endforeach
                            </x-form.select>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="container px-6 py-10">
        <div class="mx-auto max-w-[1200px] grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- LEFT --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Configure Resources (sliders) --}}
                @php $sliderOptions = $product->configOptions->filter(fn($c) => $c->type === 'slider'); @endphp
                @if($sliderOptions->count())
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary-100">Configure Resources</h2>
                            <p class="text-sm text-primary-500">Adjust server resources to match your requirements</p>
                        </div>

                        @foreach($sliderOptions as $configOption)
                            @php
                                $showPriceTag = $configOption->children
                                    ->filter(fn ($value) => !$value->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->is_free)
                                    ->count() > 0;
                            @endphp

                            <div class="bg-background-secondary/40 border border-neutral rounded-2xl p-5" wire:key="config-slider-{{ $configOption->id }}">
                                <x-form.configoption
                                    :config="$configOption"
                                    :name="'configOptions.' . $configOption->id"
                                    :showPriceTag="$showPriceTag"
                                    :plan="$plan"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Additional Options (checkboxes) --}}
                @php $checkboxOptions = $product->configOptions->filter(fn($c) => $c->type === 'checkbox'); @endphp
                @if($checkboxOptions->count())
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary-100">Additional Options</h2>
                            <p class="text-sm text-primary-500">Enhance your server with optional features</p>
                        </div>

                        @foreach($checkboxOptions as $configOption)
                            @php
                                $showPriceTag = $configOption->children
                                    ->filter(fn ($value) => !$value->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->is_free)
                                    ->count() > 0;
                            @endphp

                            <div class="bg-background-secondary/40 border border-neutral rounded-2xl p-5" wire:key="config-checkbox-{{ $configOption->id }}">
                                <x-form.configoption
                                    :config="$configOption"
                                    :name="'configOptions.' . $configOption->id"
                                    :showPriceTag="$showPriceTag"
                                    :plan="$plan"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Server Configuration --}}
                @php $otherOptions = $product->configOptions->filter(fn($c) => !in_array($c->type, ['slider','checkbox'])); @endphp
                @if($otherOptions->count() || count($this->getCheckoutConfig()))
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-primary-100">Server Configuration</h2>
                            <p class="text-sm text-primary-500">Configure your server software and settings</p>
                        </div>

                        <div class="space-y-4">
                            @foreach($otherOptions as $configOption)
                                @php
                                    $showPriceTag = $configOption->children
                                        ->filter(fn ($value) => !$value->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->is_free)
                                        ->count() > 0;
                                @endphp

                                <div class="bg-background-secondary/40 border border-neutral rounded-2xl p-5" wire:key="config-other-{{ $configOption->id }}">
                                    <x-form.configoption
                                        :config="$configOption"
                                        :name="'configOptions.' . $configOption->id"
                                        :showPriceTag="$showPriceTag"
                                        :plan="$plan"
                                    >
                                        @if ($configOption->type === 'select')
                                            @foreach ($configOption->children as $configOptionValue)
                                                <option value="{{ $configOptionValue->id }}">
                                                    {{ $configOptionValue->name }}
                                                    {{ ($showPriceTag && $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available)
                                                        ? ' - ' . $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)
                                                        : '' }}
                                                </option>
                                            @endforeach
                                        @elseif($configOption->type === 'radio')
                                            <div class="mt-3 space-y-3">
                                                @foreach ($configOption->children as $configOptionValue)
                                                    <label class="flex items-center gap-3 rounded-xl border border-neutral bg-black/10 px-4 py-3 hover:bg-black/20 transition cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            id="{{ $configOptionValue->id }}"
                                                            name="{{ $configOption->id }}"
                                                            wire:model.live="configOptions.{{ $configOption->id }}"
                                                            value="{{ $configOptionValue->id }}"
                                                        />
                                                        <span class="text-sm text-primary-100">
                                                            {{ $configOptionValue->name }}
                                                            <span class="text-primary-500">
                                                                {{ ($showPriceTag && $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)->available)
                                                                    ? ' - ' . $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit)
                                                                    : '' }}
                                                            </span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </x-form.configoption>
                                </div>
                            @endforeach

                            @foreach ($this->getCheckoutConfig() as $configOption)
                                @php $configOption = (object) $configOption; @endphp

                                <div class="bg-background-secondary/40 border border-neutral rounded-2xl p-5" wire:key="checkout-config-{{ $configOption->name }}">
                                    <x-form.configoption :config="$configOption" :name="'checkoutConfig.' . $configOption->name">
                                        @if ($configOption->type === 'select')
                                            @foreach ($configOption->options as $k => $v)
                                                <option value="{{ $k }}">{{ $v }}</option>
                                            @endforeach
                                        @elseif($configOption->type === 'radio')
                                            <div class="mt-3 space-y-3">
                                                @foreach ($configOption->options as $k => $v)
                                                    <label class="flex items-center gap-3 rounded-xl border border-neutral bg-black/10 px-4 py-3 hover:bg-black/20 transition cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            id="{{ $k }}"
                                                            name="{{ $configOption->name }}"
                                                            wire:model.live="checkoutConfig.{{ $configOption->name }}"
                                                            value="{{ $k }}"
                                                        />
                                                        <span class="text-sm text-primary-100">{{ $v }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </x-form.configoption>
                                </div>
                            @endforeach
                        </div>

                        <p class="text-xs text-primary-500">
                            Selected options update your total automatically.
                        </p>
                    </div>
                @endif
            </div>

            {{-- RIGHT: Order Summary with Alpine.js sticky behavior and live item display --}}
            <div class="lg:col-span-1">
                <div id="order-summary" wire:ignore.self 
                     class="bg-background-secondary/40 border border-neutral rounded-2xl p-6 h-fit mt-0" 
                     x-data="{ 
                         isSticky: false, atFooter: false, originalOffsetTop: 0, originalWidth: 0, originalLeft: 0, placeholder: null, isMobile: window.innerWidth < 1024,
                         init() { this.$nextTick(() => { this.checkMobile(); if (!this.isMobile) { this.setupSticky(); window.addEventListener('scroll', this.handleScroll.bind(this)); } window.addEventListener('resize', () => { this.checkMobile(); this.updateDimensions(); }); }); },
                         checkMobile() { const wasMobile = this.isMobile; this.isMobile = window.innerWidth < 1024; if (this.isMobile && !wasMobile) { this.removeSticky(); this.isSticky = false; this.atFooter = false; } else if (!this.isMobile && wasMobile) { this.setupSticky(); } },
                         setupSticky() { const rect = this.$el.getBoundingClientRect(); const scrollTop = window.pageYOffset || document.documentElement.scrollTop; if (!this.isSticky && !this.atFooter) { this.originalOffsetTop = rect.top + scrollTop; this.originalWidth = rect.width; this.originalLeft = rect.left; } },
                         updateDimensions() { if (!this.isSticky && !this.atFooter) { const rect = this.$el.getBoundingClientRect(); this.originalWidth = rect.width; this.originalLeft = rect.left; } },
                         applySticky() { const stickyGap = 24; if (!this.placeholder) { this.placeholder = document.createElement('div'); this.placeholder.style.height = this.$el.offsetHeight + 'px'; this.placeholder.style.width = '100%'; this.$el.parentNode.insertBefore(this.placeholder, this.$el); } this.$el.style.position = 'fixed'; this.$el.style.top = stickyGap + 'px'; this.$el.style.left = this.originalLeft + 'px'; this.$el.style.width = this.originalWidth + 'px'; this.$el.style.zIndex = '10'; },
                         removeSticky() { if (this.placeholder) { this.placeholder.remove(); this.placeholder = null; } this.$el.style.position = ''; this.$el.style.top = ''; this.$el.style.left = ''; this.$el.style.width = ''; this.$el.style.zIndex = ''; },
                         handleScroll() { if (this.isMobile) return; const scrollTop = window.pageYOffset || document.documentElement.scrollTop; const stickyGap = 24; const shouldBeStickyAt = this.originalOffsetTop - stickyGap; const footer = document.querySelector('footer'); if (!footer) { if (scrollTop >= shouldBeStickyAt) { if (!this.isSticky) { this.isSticky = true; this.applySticky(); } } else { if (this.isSticky) { this.isSticky = false; this.removeSticky(); } } return; } const footerWrapper = footer.parentElement; const footerWrapperRect = footerWrapper.getBoundingClientRect(); const footerWrapperTop = footerWrapperRect.top + scrollTop; const cardHeight = this.$el.offsetHeight; const cardBottomWhenSticky = stickyGap + cardHeight; const stopAtScroll = footerWrapperTop - cardBottomWhenSticky - stickyGap; if (scrollTop < shouldBeStickyAt) { if (this.isSticky || this.atFooter) { this.isSticky = false; this.atFooter = false; this.removeSticky(); } } else if (scrollTop >= shouldBeStickyAt && scrollTop < stopAtScroll) { if (!this.isSticky || this.atFooter) { this.isSticky = true; this.atFooter = false; this.applySticky(); } } else { if (!this.atFooter) { this.atFooter = true; this.isSticky = false; if (!this.placeholder) { this.placeholder = document.createElement('div'); this.placeholder.style.height = this.$el.offsetHeight + 'px'; this.placeholder.style.width = '100%'; this.$el.parentNode.insertBefore(this.placeholder, this.$el); } const maxTop = footerWrapperTop - scrollTop - cardHeight - stickyGap; this.$el.style.position = 'fixed'; this.$el.style.top = maxTop + 'px'; this.$el.style.left = this.originalLeft + 'px'; this.$el.style.width = this.originalWidth + 'px'; this.$el.style.zIndex = '10'; } else { const maxTop = footerWrapperTop - scrollTop - cardHeight - stickyGap; this.$el.style.top = maxTop + 'px'; } } }
                     }">
                    <h2 class="text-sm font-semibold text-primary-100 mb-4">
                        {{ __('product.order_summary') }}
                    </h2>

                    <div class="space-y-3">
                        {{-- Product & Plan --}}
                        <div class="flex justify-between items-center">
                            <h4 class="text-sm font-semibold text-primary-100">{{ $product->name }} {{ $plan->name }}</h4> 
                            <span class="text-sm font-semibold text-primary-100">{{ $plan->price() }}</span>
                        </div>
                        
                        {{-- Display selected config options (THIS IS THE KEY PART) --}}
                        @foreach ($product->configOptions as $configOption)
                            @if(isset($configOptions[$configOption->id]) && $configOptions[$configOption->id])
                                @php
                                    $selectedOption = $configOption->children->find($configOptions[$configOption->id]);
                                    $optionPrice = $selectedOption ? $selectedOption->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) : null;
                                @endphp
                                @if($selectedOption)
                                <div class="flex justify-between items-center">
                                    <h4 class="text-sm text-primary-100">{{ $configOption->name }}: {{ $selectedOption->name }}</h4>
                                    <span class="text-sm text-primary-100">
                                        @if($optionPrice && !$optionPrice->is_free)
                                            {{ $optionPrice }}
                                        @else
                                            Free
                                        @endif
                                    </span>
                                </div>
                                @endif
                            @endif
                        @endforeach
                        
                        {{-- Display checkout config options --}}
                        @foreach ($this->getCheckoutConfig() as $configOption)
                            @php
                                if (is_array($configOption)) {
                                    $configOption = (object) $configOption;
                                }
                                if (!isset($configOption->name)) {
                                    continue;
                                }
                            @endphp
                            @if(isset($checkoutConfig[$configOption->name]) && $checkoutConfig[$configOption->name])
                                <div class="flex justify-between items-center">
                                    <h4 class="text-sm text-primary-100">{{ $configOption->label ?? $configOption->name }}</h4>
                                    <span class="text-sm text-primary-100">
                                        @if($configOption->type == 'checkbox')
                                            {{ $checkoutConfig[$configOption->name] ? 'Yes' : 'No' }}
                                        @else
                                            {{ $checkoutConfig[$configOption->name] }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        @endforeach
                        
                        {{-- Setup Fee if applicable --}}
                        @if ($total->setup_fee && $plan->type == 'recurring')
                        <div class="flex justify-between items-center">
                            <h4 class="text-sm text-primary-100">{{ __('product.setup_fee') }}</h4>
                            <span class="text-sm text-primary-100">{{ $plan->price()->formatted->setup_fee }}</span>
                        </div>
                        @endif
                        
                        {{-- Total section --}}
                        @if ($total->total_tax > 0)
                            <div class="font-semibold flex justify-between text-primary-100/80">
                                <span>{{ __('invoices.subtotal') }}</span>
                                <span>{{ $total->format($total->subtotal) }}</span>
                            </div>
                            <div class="font-semibold flex justify-between text-primary-100/80">
                                <span>{{ \App\Classes\Settings::tax()->name }} ({{ \App\Classes\Settings::tax()->rate }}%)</span>
                                <span>{{ $total->formatted->total_tax }}</span>
                            </div>
                        @endif

                        <div class="pt-4 mt-4 border-t border-neutral/60">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs uppercase tracking-wide text-primary-500">{{ __('product.total_today') }}</span> 
                                <div class="text-2xl font-semibold text-primary-100">{{ $total }}</div>
                            </div>
                            
                            @if ($total->setup_fee && $plan->type == 'recurring')
                            <div class="mt-3 pt-2 border-t border-neutral/10">
                                <div class="flex justify-between items-start">
                                    <h4 class="text-xs text-primary-500">{{ __('product.then_after_x', ['time' => $plan->billing_period . ' ' . trans_choice(__('services.billing_cycles.' . $plan->billing_unit), $plan->billing_period)]) }}:</h4> 
                                    <span class="text-sm text-primary-100 font-semibold">{{ $total->format($total->price - $total->setup_fee) }}</span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    @if (($product->stock > 0 || !$product->stock) && $product->price()->available)
                        <div class="mt-6">
                            <x-button.primary class="w-full" wire:click="checkout" wire:loading.attr="disabled">
                                <x-loading target="checkout" />
                                <div wire:loading.remove wire:target="checkout">
                                    {{ __('product.checkout') }}
                                </div>
                            </x-button.primary>

                            <p class="mt-3 text-xs text-primary-500 text-center">
                                Secure payment powered by Stripe
                            </p>
                        </div>
                    @else
                        <div class="mt-6 text-sm text-primary-500">
                            This product isn't available right now.
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>