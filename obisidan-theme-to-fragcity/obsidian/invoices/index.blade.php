{{-- themes/obsidian/views/invoices/index.blade.php --}}

@php
    $statusMeta = [
        'paid' => [
            'label' => __('invoices.paid'),
            'pill'  => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
        ],
        'pending' => [
            'label' => __('invoices.pending'),
            'pill'  => 'bg-amber-500/15 text-amber-200 border-amber-500/25',
        ],
        'cancelled' => [
            'label' => __('invoices.cancelled'),
            'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
        ],
    ];
@endphp

<div class="w-full px-6 lg:px-10 2xl:px-12 mt-10 pb-24">
    <div class="max-w-[1650px] mx-auto space-y-8">
        <x-navigation.breadcrumb />

        {{-- Header --}}
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-base">
                {{ __('invoices.invoices') }}
            </h1>
            <p class="mt-2 text-sm text-base/60">
                {{ __('invoices.manage_invoices') ?? 'View and manage your invoices.' }}
            </p>
        </div>

        {{-- Invoices list --}}
        <div class="space-y-4">
            @forelse ($invoices as $invoice)
                @php
                    $meta = $statusMeta[$invoice->status] ?? [
                        'label' => ucfirst($invoice->status),
                        'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
                    ];

                    $title = !$invoice->number && config('settings.invoice_proforma', false)
                        ? __('invoices.proforma_invoice', ['id' => $invoice->id])
                        : __('invoices.invoice', ['id' => $invoice->number]);

                    $date = $invoice->created_at->format('M d, Y');
                @endphp

                <a href="{{ route('invoices.show', $invoice) }}" class="group block">
                    <div class="rounded-3xl border border-neutral bg-background-secondary/15 p-5 transition hover:bg-background-secondary/20">
                        <div class="flex items-start justify-between gap-6">
                            {{-- Left --}}
                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-xl border border-neutral bg-background-secondary/20 p-2">
                                        <x-ri-bill-line class="size-5 text-base" />
                                    </div>

                                    <h3 class="text-base font-semibold text-base truncate">
                                        {{ $title }}
                                    </h3>
                                </div>

                                <p class="text-sm text-base/60">
                                    {{ __('invoices.invoice_date') }}: {{ $date }}
                                </p>

                                <p class="text-sm font-medium text-base">
                                    {{ $invoice->formattedTotal }}
                                </p>
                            </div>

                            {{-- Right --}}
                            <div class="shrink-0">
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold border {{ $meta['pill'] }}">
                                    @if ($invoice->status === 'paid')
                                        <x-ri-checkbox-circle-fill class="size-4" />
                                    @elseif ($invoice->status === 'cancelled')
                                        <x-ri-forbid-fill class="size-4" />
                                    @elseif ($invoice->status === 'pending')
                                        <x-ri-error-warning-fill class="size-4" />
                                    @else
                                        <x-ri-circle-fill class="size-2 text-base/50" />
                                    @endif

                                    {{ $meta['label'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Items preview --}}
                        @if ($invoice->items->count() > 0)
                            <div class="mt-4 text-xs text-base/60 space-y-1">
                                @foreach ($invoice->items as $item)
                                    <p class="truncate">
                                        {{ $item->description }}
                                    </p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-3xl border border-neutral bg-background-secondary/15 p-10 text-center text-sm text-base/60">
                    {{ __('invoices.no_invoices') }}
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div>
            {{ $invoices->links() }}
        </div>
    </div>
</div>
