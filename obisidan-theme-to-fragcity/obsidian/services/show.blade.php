{{-- themes/obsidian/views/services/show.blade.php --}}

@php
    use Illuminate\Support\Facades\Storage;

    $statusMeta = [
        'active' => [
            'label' => __('services.statuses.active'),
            'pill'  => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
        ],
        'pending' => [
            'label' => __('services.statuses.pending'),
            'pill'  => 'bg-amber-500/15 text-amber-200 border-amber-500/25',
        ],
        'suspended' => [
            'label' => __('services.statuses.suspended'),
            'pill'  => 'bg-red-500/15 text-red-200 border-red-500/25',
        ],
        'cancelled' => [
            'label' => __('services.statuses.cancelled'),
            'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
        ],
    ];

    $meta = $statusMeta[$service->status] ?? [
        'label' => ucfirst($service->status),
        'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
    ];

    $productName  = $service->product?->name ?? 'Service';
    $categoryName = $service->product?->category?->name ?? null;

    $billingText = $service->plan->type === 'recurring'
        ? __('services.every_period', [
            'period' => $service->plan->billing_period > 1 ? $service->plan->billing_period : '',
            'unit' => trans_choice(__('services.billing_cycles.' . $service->plan->billing_unit), $service->plan->billing_period),
        ])
        : null;

    $expiresText = $service->expires_at
        ? $service->expires_at->format('M d, Y')
        : 'N/A';

    $img = !empty($service->product?->image)
        ? Storage::url($service->product->image)
        : null;

    $hasPendingInvoice = (bool) $service->invoices()->where('status', 'pending')->first();
    $canShowCancelButton = (bool) $service->cancellable && $service->status !== 'cancelled';
@endphp

<div class="w-full mt-10 pb-24">
    <div class="w-full px-6 lg:px-10 2xl:px-12">
        <div class="w-full max-w-[1650px] mx-auto space-y-6">
            <x-navigation.breadcrumb />

            {{-- Outstanding invoice banner --}}
            @if ($invoice = $service->invoices()->where('status', 'pending')->first())
                <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-amber-200">
                    <p class="text-sm font-semibold">
                        {{ __('services.outstanding_invoice') }}
                        <a href="{{ route('invoices.show', $invoice) }}" class="underline underline-offset-2 hover:text-amber-100">
                            {{ __('services.view_and_pay') }}
                        </a>.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Full width hero --}}
    <div class="w-full px-6 lg:px-10 2xl:px-12 mt-6">
        <div class="w-full rounded-3xl border border-neutral overflow-hidden">
            <div class="relative h-[360px] md:h-[460px] 2xl:h-[520px]">
                @if ($img)
                    <img
                        src="{{ $img }}"
                        alt="{{ $productName }}"
                        class="absolute inset-0 w-full h-full object-cover"
                        loading="lazy"
                    />
                @else
                    <div class="absolute inset-0 bg-background-secondary/25"></div>
                @endif

                <div class="absolute inset-0 bg-black/45"></div>
                <div class="absolute inset-x-0 bottom-0 h-60 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>

                <div class="absolute inset-x-0 bottom-0 p-6 lg:p-10">
                    <div class="w-full max-w-[1650px] mx-auto">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs uppercase tracking-widest text-white/60">
                                    {{ $categoryName ?? __('services.services') }}
                                </p>

                                <h1 class="mt-2 text-4xl md:text-5xl font-semibold tracking-tight text-white">
                                    {{ $productName }}
                                </h1>

                                <p class="mt-3 text-sm text-white/75 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span>{{ $service->formattedPrice }}</span>

                                    @if ($billingText)
                                        <span class="text-white/40">|</span>
                                        <span>{{ $billingText }}</span>
                                    @endif

                                    <span class="text-white/40">|</span>
                                    <span>{{ __('services.expires_at') }}: {{ $expiresText }}</span>
                                </p>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold border {{ $meta['pill'] }}">
                                    {{ $meta['label'] }}
                                </span>

                                @if ($service->upgradable)
                                    <a href="{{ route('services.upgrade', $service->id) }}">
                                        <x-button.primary class="h-fit !w-fit" type="button">
                                            {{ __('services.upgrade') }}
                                        </x-button.primary>
                                    </a>
                                @endif

                                {{-- FIX: no nested button, put wire:click on the x-button itself --}}
                                @if ($canShowCancelButton)
                                    <x-button.danger
                                        type="button"
                                        class="h-fit !w-fit"
                                        wire:click="$set('showCancel', true)"
                                    >
                                        {{ __('services.cancel') }}
                                    </x-button.danger>
                                @endif

                                @foreach ($buttons as $button)
                                    @php $isWire = !empty($button['function']); @endphp

                                    @if ($isWire)
                                        <x-button.primary
                                            type="button"
                                            class="h-fit !w-fit"
                                            wire:click="goto('{{ $button['function'] }}')"
                                        >
                                            {{ $button['label'] }}
                                        </x-button.primary>
                                    @else
                                        <a
                                            href="{{ $button['url'] }}"
                                            @if(!empty($button['target'])) target="{{ $button['target'] }}" @endif
                                            @if(($button['target'] ?? null) === '_blank') rel="noopener noreferrer" @endif
                                        >
                                            <x-button.primary class="h-fit !w-fit" type="button">
                                                {{ $button['label'] }}
                                            </x-button.primary>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Content --}}
        <div class="w-full max-w-[1650px] mx-auto mt-6">
            <div class="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-6 items-start">
                {{-- Product details --}}
                <div class="rounded-3xl border border-neutral bg-background-secondary/15 p-6">
                    <h2 class="text-lg font-semibold text-base">
                        {{ __('services.product_details') }}
                    </h2>

                    <div class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-6">
                            <span class="text-base/60">{{ __('services.name') }}</span>
                            <span class="font-medium text-right">{{ $productName }}</span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-base/60">{{ __('services.price') }}</span>
                            <span class="font-medium text-right">{{ $service->formattedPrice }}</span>
                        </div>

                        @if ($billingText)
                            <div class="flex justify-between gap-6">
                                <span class="text-base/60">{{ __('services.billing_cycle') }}</span>
                                <span class="font-medium text-right">{{ $billingText }}</span>
                            </div>
                        @endif

                        <div class="flex justify-between gap-6">
                            <span class="text-base/60">{{ __('services.expires_at') }}</span>
                            <span class="font-medium text-right">{{ $expiresText }}</span>
                        </div>

                        <div class="flex justify-between gap-6">
                            <span class="text-base/60">{{ __('services.status') }}</span>
                            <span class="font-semibold text-right">{{ $meta['label'] }}</span>
                        </div>

                        @foreach ($fields as $field)
                            <div class="flex justify-between gap-6">
                                <span class="text-base/60">{{ $field['label'] }}</span>
                                <span class="font-medium text-right">{{ $field['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Overview --}}
                <div class="rounded-3xl border border-neutral bg-background-secondary/15 p-6">
                    <h2 class="text-lg font-semibold text-base">Overview</h2>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-2xl border border-neutral bg-background-secondary/20 p-4">
                            <p class="text-xs uppercase tracking-widest text-base/50">Amount</p>
                            <p class="mt-2 text-2xl font-semibold">{{ $service->formattedPrice }}</p>
                            <p class="mt-1 text-xs text-base/60">Current plan price</p>
                        </div>

                        <div class="rounded-2xl border border-neutral bg-background-secondary/20 p-4">
                            <p class="text-xs uppercase tracking-widest text-base/50">Billing</p>
                            <p class="mt-2 font-semibold">{{ $billingText ?: 'One-time' }}</p>
                            <p class="mt-1 text-xs text-base/60">Billing cycle</p>
                        </div>

                        <div class="rounded-2xl border border-neutral bg-background-secondary/20 p-4">
                            <p class="text-xs uppercase tracking-widest text-base/50">Expires</p>
                            <p class="mt-2 font-semibold">{{ $expiresText }}</p>
                            <p class="mt-1 text-xs text-base/60">Renewal date</p>
                        </div>

                        <div class="rounded-2xl border border-neutral bg-background-secondary/20 p-4">
                            <p class="text-xs uppercase tracking-widest text-base/50">Invoice</p>
                            <p class="mt-2 font-semibold">{{ $hasPendingInvoice ? 'Payment required' : 'All good' }}</p>
                            <p class="mt-1 text-xs text-base/60">{{ $hasPendingInvoice ? 'Unpaid invoice exists' : 'No outstanding invoices' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Extension content --}}
                @if (!empty($extensionView))
                    <div class="rounded-3xl border border-neutral bg-background-secondary/15 p-6 xl:col-span-2 2xl:col-span-1">
                        {!! $extensionView !!}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cancel modal (PURE LIVEWIRE) --}}
    @if ($showCancel)
        <div class="fixed inset-0 z-[9999]">
            <div class="absolute inset-0 bg-black/70" wire:click="$set('showCancel', false)"></div>

            <div class="absolute inset-0 flex items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-3xl border border-neutral bg-background-secondary/95 backdrop-blur p-6">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-base">{{ __('services.cancel') }}</h3>

                        <button type="button" class="text-sm text-base/60 hover:text-base" wire:click="$set('showCancel', false)">
                            Close
                        </button>
                    </div>

                    <div class="mt-4">
                        <livewire:services.cancel :service="$service" />
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
