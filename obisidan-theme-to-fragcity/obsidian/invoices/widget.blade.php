{{-- themes/obsidian/views/invoices/widget.blade.php --}}

@php
    use Illuminate\Support\Facades\Auth;

    $statusMeta = [
        'paid' => [
            'label' => 'Paid',
            'pill'  => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
        ],
        'pending' => [
            'label' => 'Unpaid',
            'pill'  => 'bg-amber-500/15 text-amber-200 border-amber-500/25',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
        ],
        'overdue' => [
            'label' => 'Overdue',
            'pill'  => 'bg-red-500/15 text-red-200 border-red-500/25',
        ],
    ];

    $invoiceTitle = function ($invoice) {
        return !$invoice->number && config('settings.invoice_proforma', false)
            ? __('invoices.proforma_invoice', ['id' => $invoice->id])
            : __('invoices.invoice', ['id' => $invoice->number]);
    };

    $limitValue = isset($limit) ? (int) $limit : 4;
    if ($limitValue <= 0) $limitValue = 4;

    $user = Auth::user();

    $invoices = $user
        ? $user->invoices()
            ->with(['items'])
            ->orderByDesc('created_at')
            ->limit($limitValue)
            ->get()
        : collect();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @forelse ($invoices as $invoice)
        @php
            $meta = $statusMeta[$invoice->status] ?? [
                'label' => ucfirst((string) $invoice->status),
                'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
            ];

            $date = null;
            if (!empty($invoice->due_date)) {
                $date = optional($invoice->due_date)->format('D, F d, Y');
            } else {
                $date = optional($invoice->created_at)->format('D, F d, Y - H:i');
            }
        @endphp

        <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="group block">
            <div class="rounded-2xl border border-neutral bg-background-secondary/15 transition hover:bg-background-secondary/20 overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-base font-semibold text-base truncate">
                                {{ $invoiceTitle($invoice) }}
                            </div>

                            @if ($date)
                                <div class="mt-2 text-xs text-base/60">
                                    {{ $date }}
                                </div>
                            @endif
                        </div>

                        <div class="shrink-0">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold border {{ $meta['pill'] }}">
                                @if ($invoice->status === 'paid')
                                    <x-ri-check-line class="size-3" />
                                @endif
                                {{ $meta['label'] }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-neutral/60"></div>

                    <div class="mt-4">
                        <div class="text-xs text-base/60">Amount</div>
                        <div class="mt-2 text-2xl font-semibold text-base">
                            {{ $invoice->formattedTotal }}
                        </div>
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="col-span-full rounded-2xl border border-neutral bg-background-secondary/15 p-6 text-center text-sm text-base/60">
            No invoices yet.
        </div>
    @endforelse
</div>
