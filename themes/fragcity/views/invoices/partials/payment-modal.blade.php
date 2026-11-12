<x-modal variant="payment" :title="'پرداخت فاکتور ' . ($invoice->number ?: $invoice->id)" open>
    <x-slot name="closeTrigger">
        <div class="flex gap-4">
            <span class="fc-payment-modal__amount">مبلغ قابل پرداخت: {{ $invoice->formattedRemaining }}</span>
            <button wire:confirm="Are you sure?" wire:click="exitPay" @click="open = false" class="text-primary-100">
                <x-ri-close-fill class="size-6" />
            </button>
        </div>
    </x-slot>

    @if ($this->pay)
        <section class="fc-payment-provider-content fc-payment-provider-card">{{ $this->pay }}</section>
    @else
        <div class="fc-payment-options-shell">@include('invoices.partials.payment-options')</div>
    @endif
</x-modal>