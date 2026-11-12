<div class="container mt-14 fc-portal-page fc-invoices-page" dir="rtl">
    <x-navigation.breadcrumb />
    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / BILLING HISTORY</span>
            <h1>فاکتورهای من</h1>
            <p>فاکتورها، مبالغ و وضعیت پرداخت سفارش‌ها را اینجا پیگیری کن.</p>
        </div>
    </div>

    <div class="fc-portal-list">
        @forelse ($invoices as $invoice)
        <a href="{{ route('invoices.show', $invoice) }}" wire:navigate class="fc-portal-list-card fc-invoice-card">
            <span class="fc-portal-list-card__icon"><x-ri-bill-line class="size-6" /></span>
            <span class="fc-portal-list-card__body">
                <strong>{{ !$invoice->number && config('settings.invoice_proforma', false) ? 'پیش‌فاکتور #' . $invoice->id : 'فاکتور #' . $invoice->number }}</strong>
                <small>{{ $invoice->created_at->format('Y/m/d') }} <span>·</span> {{ $invoice->items->count() }} مورد</small>
                <small>{{ $invoice->items->first()?->description }}</small>
            </span>
            <span class="fc-invoice-card__amount">{{ $invoice->formattedTotal }}</span>
            <span class="fc-portal-status fc-portal-status--{{ $invoice->status }}">@if($invoice->status === 'paid') پرداخت‌شده @elseif($invoice->status === 'pending') در انتظار پرداخت @elseif($invoice->status === 'cancelled') لغوشده @else {{ ucfirst($invoice->status) }} @endif</span>
            <span class="fc-portal-list-card__arrow"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
        @empty
        <div class="fc-portal-empty">
            <span><x-ri-bill-line class="size-8" /></span>
            <strong>هنوز فاکتوری برای حساب شما ثبت نشده است.</strong>
            <p>پس از ثبت سفارش یا ایجاد فاکتور، سوابق مالی اینجا دیده می‌شوند.</p>
            <a href="{{ route('home') }}" wire:navigate class="fc-portal-button">رفتن به فروشگاه</a>
        </div>
        @endforelse
    </div>
    <div class="fc-portal-pagination">{{ $invoices->links() }}</div>
</div>
