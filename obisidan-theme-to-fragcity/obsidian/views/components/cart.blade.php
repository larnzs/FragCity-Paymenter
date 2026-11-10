{{-- themes/obsidian/views/components/cart.blade.php --}}

@php
    use Illuminate\Support\Facades\DB;

    $raw = DB::table('settings')->where('key', 'obsidian.cart_editor')->value('value');
    $decoded = null;

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
    }

    $cfg = is_array($decoded) ? $decoded : [];

    $enabled = function (string $path, bool $default = true) use ($cfg): bool {
        $val = data_get($cfg, $path, $default);
        return filter_var($val, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $val;
    };

    $text = function (string $path, string $default = '') use ($cfg): string {
        $val = data_get($cfg, $path, $default);
        return is_string($val) ? $val : $default;
    };

    $arr = function (string $path, array $default = []) use ($cfg): array {
        $val = data_get($cfg, $path, $default);
        return is_array($val) ? $val : $default;
    };

    $headerEnabled   = $enabled('header.enabled', true);
    $headerTitle     = $text('header.title', 'Shopping Cart');
    $headerSubtitle  = $text('header.subtitle', 'Review your order and proceed to checkout');

    $stepperEnabled  = $enabled('stepper.enabled', true);
    $step1Label      = $text('stepper.step1', 'Review Cart');
    $step2Label      = $text('stepper.step2', 'Checkout');
    $step3Label      = $text('stepper.step3', 'Complete');

    $emptyEnabled    = $enabled('empty.enabled', true);
    $emptyTitle      = $text('empty.title', __('product.empty_cart'));
    $emptySubtitle   = $text('empty.subtitle', 'Add a product to get started.');

    $continueEnabled = $enabled('continue_shopping.enabled', true);
    $continueLabel   = $text('continue_shopping.label', 'Continue Shopping');

    $summaryEnabled  = $enabled('summary.enabled', true);
    $summaryTitle    = $text('summary.title', __('product.order_summary'));
    $couponLabel     = $text('summary.coupon_label', 'Have a coupon code?');
    $couponPlaceholder = $text('summary.coupon_placeholder', 'Enter code');
    $couponApplyText = $text('summary.coupon_apply', __('product.apply'));
    $ctaText         = $text('summary.cta', 'Proceed to Checkout');
    $stripeNote      = $text('summary.stripe_note', 'Secure payment powered by Stripe');

    $trustEnabled    = $enabled('trust.enabled', true);
    $trustItems      = $arr('trust.items', [
        ['text' => 'SSL Encrypted Payment'],
        ['text' => '30-Day Money Back Guarantee'],
        ['text' => '24/7 Support Available'],
    ]);

    $whyEnabled      = $enabled('why.enabled', true);
    $whyTitle        = $text('why.title', 'Why Choose Us?');
    $whyItems        = $arr('why.items', [
        ['title' => '99.9% Uptime Guarantee', 'subtitle' => 'Enterprise-grade infrastructure'],
        ['title' => 'Instant Setup', 'subtitle' => 'Server ready in minutes'],
        ['title' => '24/7 Expert Support', 'subtitle' => 'Always here to help'],
    ]);

    $helpEnabled     = $enabled('help.enabled', true);
    $helpTitle       = $text('help.title', 'Need Help?');
    $helpSubtitle    = $text('help.subtitle', 'Our team is available 24/7 to assist you with your purchase');
    $helpLinkLabel   = $text('help.link_label', 'Chat with us');
    $helpLinkUrl     = $text('help.link_url', '#');
@endphp

<div class="min-h-screen">
    <div class="container px-6 pt-10 pb-24">
        <div class="mx-auto max-w-[1200px]">

            {{-- Header --}}
            @if($headerEnabled)
                <div class="mb-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-base">
                                {{ $headerTitle }}
                            </h1>
                            <p class="mt-2 text-sm text-base/60">
                                {{ $headerSubtitle }}
                            </p>
                        </div>

                        {{-- Stepper (visual only) --}}
                        @if($stepperEnabled)
                            <div class="flex items-center gap-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center bg-primary text-white font-semibold">
                                        1
                                    </div>
                                    <div class="text-base font-medium">{{ $step1Label }}</div>
                                </div>

                                <div class="hidden sm:block w-10 h-px bg-neutral/60"></div>

                                <div class="flex items-center gap-2 text-base/50">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center border border-neutral bg-background-secondary/40">
                                        2
                                    </div>
                                    <div class="font-medium">{{ $step2Label }}</div>
                                </div>

                                <div class="hidden sm:block w-10 h-px bg-neutral/60"></div>

                                <div class="flex items-center gap-2 text-base/50">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center border border-neutral bg-background-secondary/40">
                                        3
                                    </div>
                                    <div class="font-medium">{{ $step3Label }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- LEFT --}}
                <div class="lg:col-span-2 space-y-6">

                    @if (Cart::items()->count() === 0 && $emptyEnabled)
                        <div class="rounded-3xl border border-neutral bg-background-secondary/40 p-8">
                            <h2 class="text-2xl font-semibold text-base">
                                {{ $emptyTitle }}
                            </h2>
                            <p class="mt-2 text-sm text-base/60">
                                {{ $emptySubtitle }}
                            </p>
                        </div>
                    @endif

                    @foreach (Cart::items() as $item)
                        <div class="rounded-3xl border border-neutral bg-background-secondary/40 p-6 md:p-7">

                            {{-- Top row --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-primary/20 border border-primary/30 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
                                            <path d="M7 6h6v2H7V6z" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="text-lg md:text-xl font-semibold text-base truncate">
                                            {{ $item->product->name }}
                                        </div>
                                        <div class="text-sm text-base/60">
                                            Monthly billing
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <div class="text-2xl md:text-3xl font-semibold text-base">
                                        {{ $item->price->format($item->price->total * $item->quantity) }}
                                    </div>
                                    <div class="text-sm text-base/60">
                                        per month
                                    </div>

                                    @if ($item->quantity > 1)
                                        <div class="mt-1 text-xs text-base/50">
                                            {{ $item->price }} each
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Config grid --}}
                            <div class="mt-6 pt-6 border-t border-neutral/60">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach ($item->config_options as $option)
                                        <div class="flex items-start gap-3">
                                            <div class="mt-1 w-7 h-7 rounded-xl bg-background-secondary/60 border border-neutral flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4 text-base/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </div>

                                            <div class="min-w-0">
                                                <div class="text-xs text-base/60">
                                                    {{ $option['option_name'] }}
                                                </div>
                                                <div class="text-sm font-medium text-base truncate">
                                                    {{ $option['value_name'] }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Actions row (Edit wide, Remove small) --}}
                            <div class="mt-6 pt-6 border-t border-neutral/60">
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <a
                                        href="{{ route('products.checkout', [$item->product->category, $item->product, 'edit' => $item->id]) }}"
                                        wire:navigate
                                        class="flex-1"
                                    >
                                        <x-button.secondary class="w-full h-11">
                                            Edit Configuration
                                        </x-button.secondary>
                                    </a>

                                    <x-button.danger wire:click="removeProduct({{ $item->id }})" class="sm:!w-fit w-full h-11 px-5">
                                        <x-loading target="removeProduct({{ $item->id }})" />
                                        <div wire:loading.remove wire:target="removeProduct({{ $item->id }})">
                                            {{ __('product.remove') }}
                                        </div>
                                    </x-button.danger>
                                </div>

                                @if ($item->product->allow_quantity == 'combined')
                                    <div class="mt-4 flex items-center gap-2 justify-between sm:justify-end">
                                        <x-button.secondary
                                            wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"
                                            class="!w-fit h-10 px-4"
                                        >
                                            -
                                        </x-button.secondary>

                                        <x-form.input
                                            class="h-10 text-center"
                                            disabled
                                            divClass="!mt-0 !w-20"
                                            value="{{ $item->quantity }}"
                                            name="quantity"
                                        />

                                        <x-button.secondary
                                            wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                            class="!w-fit h-10 px-4"
                                        >
                                            +
                                        </x-button.secondary>
                                    </div>
                                @endif
                            </div>

                        </div>
                    @endforeach

                    {{-- ObsidianCartUpsell --}}
                    @if (Cart::items()->count() > 0)
                        <livewire:obsidian-cart-upsell.cart-upsells />
                    @endif

                    {{-- Bottom left link --}}
                    @if (Cart::items()->count() > 0 && $continueEnabled)
                        <div>
                            <a href="{{ url()->previous() }}" class="text-sm text-base/60 hover:text-base inline-flex items-center gap-2">
                                <span aria-hidden="true">&larr;</span>
                                <span>{{ $continueLabel }}</span>
                            </a>
                        </div>
                    @endif

                </div>

                {{-- RIGHT --}}
                <div class="lg:col-span-1 space-y-6">
                    @if (Cart::items()->count() > 0 && $summaryEnabled)
                        <div class="rounded-3xl border border-neutral bg-background-secondary/40 p-6 lg:sticky lg:top-24">
                            <h2 class="text-base font-semibold text-base mb-5">
                                {{ $summaryTitle }}
                            </h2>

                            {{-- Coupon --}}
                            <div class="mb-6">
                                <div class="text-sm text-base/60 mb-2">{{ $couponLabel }}</div>

                                @if(!$coupon)
                                    <div class="flex gap-2">
                                        <div class="flex-1">
                                            <x-form.input wire:model="coupon" name="coupon" placeholder="{{ $couponPlaceholder }}" />
                                        </div>

                                        <x-button.secondary
                                            wire:click="applyCoupon"
                                            class="h-10 !w-fit px-4"
                                            wire:loading.attr="disabled"
                                        >
                                            <x-loading target="applyCoupon" />
                                            <div wire:loading.remove wire:target="applyCoupon">
                                                {{ $couponApplyText }}
                                            </div>
                                        </x-button.secondary>
                                    </div>
                                @else
                                    <div class="flex items-center justify-between gap-2 bg-background-secondary/30 border border-neutral rounded-2xl px-4 py-3">
                                        <div class="text-sm text-base font-semibold truncate">
                                            {{ $coupon->code }}
                                        </div>
                                        <x-button.secondary wire:click="removeCoupon" class="h-9 !w-fit px-3">
                                            {{ __('product.remove') }}
                                        </x-button.secondary>
                                    </div>
                                @endif
                            </div>

                            {{-- Totals --}}
                            <div class="space-y-3 border-t border-neutral/60 pt-5 text-sm">
                                <div class="flex justify-between items-center text-base/70">
                                    <span>{{ __('invoices.subtotal') }}</span>
                                    <span class="font-semibold text-base">
                                        {{ $total->format($total->subtotal) }}
                                    </span>
                                </div>

                                @if ($total->tax > 0)
                                    <div class="flex justify-between items-center text-base/70">
                                        <span>{{ \App\Classes\Settings::tax()->name }} ({{ \App\Classes\Settings::tax()->rate }}%)</span>
                                        <span class="font-semibold text-base">
                                            {{ $total->format($total->tax) }}
                                        </span>
                                    </div>
                                @endif

                                <div class="pt-4 mt-1 border-t border-neutral/60 flex justify-between items-baseline">
                                    <span class="text-base font-semibold">Total</span>
                                    <span class="text-2xl font-semibold text-base">
                                        {{ $total->format($total->total) }}
                                    </span>
                                </div>
                            </div>

                            {{-- TOS --}}
                            @if(config('settings.tos'))
                                <div class="mt-5">
                                    <label
                                        for="cart_tos"
                                        class="block w-full rounded-2xl border border-neutral bg-background-secondary/30 px-4 py-3 cursor-pointer"
                                    >
                                        <div class="flex items-start gap-3">
                                            <input
                                                id="cart_tos"
                                                type="checkbox"
                                                name="tos"
                                                wire:model="tos"
                                                class="mt-1 size-4 rounded border-neutral bg-background-secondary text-primary focus:ring-2 focus:ring-primary/40 focus:ring-offset-2 focus:ring-offset-background cursor-pointer"
                                            />

                                            <div class="min-w-0 text-sm text-base/80 leading-5">
                                                <span>{{ __('product.tos') }}</span>
                                                <a
                                                    href="{{ config('settings.tos') }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-primary hover:text-primary/80"
                                                    onclick="event.stopPropagation();"
                                                >
                                                    {{ __('product.tos_link') }}
                                                </a>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endif

                            {{-- CTA --}}
                            <div class="mt-4">
                                <x-button.primary wire:click="checkout" class="w-full h-12" wire:loading.attr="disabled">
                                    <x-loading target="checkout" />
                                    <div wire:loading.remove wire:target="checkout">
                                        {{ $ctaText }}
                                    </div>
                                </x-button.primary>
                            </div>

                            {{-- Checklist --}}
                            @if($trustEnabled)
                                <div class="mt-5 space-y-2 text-sm text-base/60">
                                    @foreach($trustItems as $trust)
                                        @php
                                            $trustText = is_array($trust) ? ($trust['text'] ?? '') : (string) $trust;
                                        @endphp
                                        @if($trustText !== '')
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex w-4 h-4 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/25">
                                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 0 000-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span>{{ $trustText }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-5 pt-5 border-t border-neutral/60">
                                <div class="text-xs text-base/50 text-center">
                                    {{ $stripeNote }}
                                </div>

                                <div class="mt-3 flex items-center justify-center gap-2">
                                    <div class="rounded-xl border border-neutral bg-background-secondary/40 px-3 py-2 text-xs text-base/60">
                                        Visa
                                    </div>
                                    <div class="rounded-xl border border-neutral bg-background-secondary/40 px-3 py-2 text-xs text-base/60">
                                        Stripe
                                    </div>
                                    <div class="rounded-xl border border-neutral bg-background-secondary/40 px-3 py-2 text-xs text-base/60">
                                        PayPal
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Extra cards --}}
                        @if($whyEnabled)
                            <div class="rounded-3xl border border-neutral bg-background-secondary/40 p-6">
                                <h3 class="text-base font-semibold text-base mb-4">{{ $whyTitle }}</h3>

                                <div class="space-y-3 text-sm">
                                    @foreach($whyItems as $why)
                                        @php
                                            $title = is_array($why) ? ($why['title'] ?? '') : '';
                                            $sub   = is_array($why) ? ($why['subtitle'] ?? '') : '';
                                        @endphp
                                        @if($title !== '')
                                            <div class="flex items-start gap-3">
                                                <div class="mt-0.5 w-7 h-7 rounded-xl bg-primary/15 border border-primary/25 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 0 000-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-base">{{ $title }}</div>
                                                    @if($sub !== '')
                                                        <div class="text-base/60">{{ $sub }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($helpEnabled)
                            <div class="rounded-3xl border border-primary/25 bg-primary/5 p-6">
                                <div class="flex items-start gap-4">
                                    <div class="w-11 h-11 rounded-2xl bg-primary text-white flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H7l-3 3v-3H4a2 2 0 01-2-2V5z" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="text-base font-semibold text-base">{{ $helpTitle }}</div>
                                        <div class="mt-1 text-sm text-base/60">
                                            {{ $helpSubtitle }}
                                        </div>

                                        <a href="{{ $helpLinkUrl }}" class="mt-3 inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80">
                                            <span>{{ $helpLinkLabel }}</span>
                                            <span aria-hidden="true">&rarr;</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
