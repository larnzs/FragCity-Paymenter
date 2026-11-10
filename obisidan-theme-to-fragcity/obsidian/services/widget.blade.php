{{-- themes/obsidian/views/services/widget.blade.php --}}

@php
    use Illuminate\Support\Facades\Storage;

    $statusMeta = [
        'active' => [
            'label' => 'Online',
            'pill'  => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
        ],
        'pending' => [
            'label' => 'Provisioning',
            'pill'  => 'bg-amber-500/15 text-amber-200 border-amber-500/25',
        ],
        'suspended' => [
            'label' => 'Suspended',
            'pill'  => 'bg-red-500/15 text-red-200 border-red-500/25',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
        ],
    ];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    @forelse ($services as $service)
        @php
            $meta = $statusMeta[$service->status] ?? [
                'label' => ucfirst($service->status),
                'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
            ];

            $productName  = $service->product?->name ?? 'Service';
            $categoryName = $service->product?->category?->name ?? null;

            $billingText = in_array($service->plan->type, ['recurring'])
                ? __('services.every_period', [
                    'period' => $service->plan->billing_period > 1 ? $service->plan->billing_period : '',
                    'unit' => trans_choice(__('services.billing_cycles.' . $service->plan->billing_unit), $service->plan->billing_period),
                ])
                : null;

            $img = !empty($service->product?->image) ? Storage::url($service->product->image) : null;
        @endphp

        <a href="{{ route('services.show', $service) }}" wire:navigate class="group block">
            <div class="rounded-3xl border border-neutral bg-background-secondary/15 overflow-hidden transition hover:bg-background-secondary/20">
                {{-- Image header --}}
                <div class="relative h-44 w-full">
                    @if ($img)
                        <img
                            src="{{ $img }}"
                            alt="{{ $productName }}"
                            class="absolute inset-0 h-full w-full object-cover opacity-90 transition group-hover:opacity-100"
                            loading="lazy"
                        />
                        <div class="absolute inset-0 bg-black/45"></div>
                        <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/55 to-transparent"></div>
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-background-secondary/70 via-background-secondary/25 to-transparent"></div>
                        <div class="absolute inset-0 bg-black/25"></div>
                    @endif

                    {{-- Status pill --}}
                    <div class="absolute top-4 right-4">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold border {{ $meta['pill'] }}">
                            {{ $meta['label'] }}
                        </span>
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-5">
                    <h3 class="text-base font-semibold text-base truncate">
                        {{ $productName }}
                    </h3>

                    <div class="mt-1 text-xs text-base/60 flex flex-wrap items-center gap-x-2 gap-y-1">
                        @if ($categoryName)
                            <span>{{ $categoryName }}</span>
                        @endif

                        @if ($billingText)
                            @if ($categoryName)
                                <span class="text-base/40">-</span>
                            @endif
                            <span>{{ $billingText }}</span>
                        @endif
                    </div>

                    {{-- Manage button (theme primary) --}}
                    <div class="mt-4">
                        <div class="h-10 rounded-xl border border-neutral bg-primary text-white overflow-hidden transition hover:opacity-90">
                            <div class="h-full flex items-center justify-center text-sm font-semibold">
                                Manage
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="col-span-full rounded-3xl border border-neutral bg-background-secondary/15 p-8 text-center text-sm text-base/60">
            No active services yet.
        </div>
    @endforelse
</div>
