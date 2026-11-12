<div class="container mt-14 fc-portal-page fc-invoice-detail-page" dir="rtl">
    <x-navigation.breadcrumb />
    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / BILLING DOCUMENT</span>
            <h1>فاکتور شماره {{ $invoice->number ?: $invoice->id }}</h1>
            <p>سند مالی سفارش، جزئیات اقلام و وضعیت پرداخت.</p>
        </div>
        <a href="{{ route('invoices') }}" wire:navigate class="fc-portal-heading__action">
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> بازگشت به فاکتورها
        </a>
    </div>

    <div class="fc-invoice-actions">
        <span class="fc-invoice-actions__reference"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> سند مالی FragCity</span>
        <button type="button" class="fc-invoice-download" wire:click="downloadPDF">
            <span wire:loading wire:target="downloadPDF"><x-ri-loader-5-fill class="size-4 animate-spin" /></span>
            <span wire:loading.remove wire:target="downloadPDF"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> دانلود PDF فاکتور</span>
        </button>
    </div>

    <div class="fc-invoice-live-region" @if ($checkPayment) wire:poll.5s="checkPaymentStatus" @endif>
        @if ($this->pay || $showPayModal)
            @include('invoices.partials.payment-modal')
        @endif

        <article class="fc-invoice-paper" aria-labelledby="fcInvoicePaperTitle">
            <div class="fc-invoice-paper__accent" aria-hidden="true"></div>
            <header class="fc-invoice-paper__header">
                <div class="fc-invoice-brand">
                    <span class="fc-invoice-brand__mark">FC<span>.</span></span>
                    <span class="fc-invoice-brand__name">
                        <strong>FRAGCITY</strong>
                        <small>GAME COMMUNITY / SERVER HOSTING</small>
                    </span>
                </div>
                <div class="fc-invoice-paper__title">
                    <span class="fc-invoice-paper__eyebrow">INVOICE / صورتحساب</span>
                    <h2 id="fcInvoicePaperTitle">فاکتور شماره {{ $invoice->number ?: $invoice->id }}</h2>
                    <code>#{{ $invoice->number ?: $invoice->id }}</code>
                </div>
            </header>

            <div class="fc-invoice-paper__rule"></div>

            <section class="fc-invoice-parties" aria-label="اطلاعات صورتحساب">
                <div class="fc-invoice-party">
                    <span class="fc-invoice-paper__label">BILL TO / صورتحساب برای</span>
                    <strong>{{ $invoice->user_name }}</strong>
                    @foreach ($invoice->user_properties as $property)
                        <span>{{ $property }}</span>
                    @endforeach
                </div>
                <div class="fc-invoice-party">
                    <span class="fc-invoice-paper__label">BILLING ADDRESS / نشانی صورتحساب</span>
                    <p>{!! nl2br(e($invoice->bill_to)) !!}</p>
                </div>
                <div class="fc-invoice-meta">
                    <div><span>تاریخ صدور</span><strong>{{ $invoice->created_at->format('d M Y') }}</strong></div>
                    @if ($invoice->due_at)
                        <div><span>مهلت پرداخت</span><strong>{{ $invoice->due_at->format('d M Y') }}</strong></div>
                    @endif
                    <div><span>شماره سند</span><strong>#{{ $invoice->number ?: $invoice->id }}</strong></div>
                </div>
            </section>

            <section class="fc-invoice-state" aria-live="polite">
                <div class="fc-invoice-state__status">
                    <span class="fc-invoice-paper__label">PAYMENT STATUS / وضعیت پرداخت</span>
                    @if ($invoice->status == 'paid')
                        <span class="fc-invoice-badge fc-invoice-badge--paid"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> پرداخت‌شده</span>
                        <p>این فاکتور با موفقیت تسویه شده است.</p>
                    @elseif ($invoice->status == 'pending')
                        @if ($checkPayment || $invoice->transactions->where('status', \App\Enums\InvoiceTransactionStatus::Processing)->where('created_at', '>=', now()->subDays(1))->count() > 0)
                            <span class="fc-invoice-badge fc-invoice-badge--processing"><x-ri-loader-5-fill class="size-4 animate-spin" /> در حال بررسی پرداخت</span>
                            <p>وضعیت تراکنش در حال بررسی است؛ از ثبت پرداخت تکراری خودداری کن.</p>
                        @else
                            <span class="fc-invoice-badge fc-invoice-badge--pending"><i class="fa-solid fa-clock" aria-hidden="true"></i> در انتظار پرداخت</span>
                            @if ($invoice->transactions->where('status', \App\Enums\InvoiceTransactionStatus::Processing)->count() > 0)
                                <p>یک تراکنش قبلی هنوز در حال پردازش است. پیش از پرداخت دوباره، وضعیت آن را بررسی کن.</p>
                            @else
                                <p>برای تکمیل سفارش، مبلغ این فاکتور را پرداخت کن.</p>
                            @endif
                            <x-button.primary wire:click="$set('showPayModal', true)" class="fc-invoice-pay-button"
                                wire:loading.attr="disabled" wire:target="$set('showPayModal')">
                                <span wire:loading wire:target="pay">در حال آماده‌سازی پرداخت…</span>
                                <span wire:loading.remove wire:target="pay"><i class="fa-solid fa-lock" aria-hidden="true"></i> پرداخت فاکتور</span>
                            </x-button.primary>
                        @endif
                    @else
                        <span class="fc-invoice-badge fc-invoice-badge--cancelled"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> لغوشده یا غیرفعال</span>
                        <p>این فاکتور در حال حاضر قابل پرداخت نیست.</p>
                    @endif
                </div>
                <div class="fc-invoice-state__amount">
                    <span>مبلغ کل فاکتور</span>
                    <strong>{{ $invoice->formattedTotal }}</strong>
                    <small>مبلغ نهایی ثبت‌شده در Paymenter</small>
                </div>
            </section>

            <section class="fc-invoice-items" aria-labelledby="fcInvoiceItemsTitle">
                <div class="fc-invoice-section-heading">
                    <div><span class="fc-invoice-paper__label">ORDER DETAILS</span><h3 id="fcInvoiceItemsTitle">شرح اقلام فاکتور</h3></div>
                    <span class="fc-invoice-item-count">{{ $invoice->items->count() }} مورد</span>
                </div>
                <div class="fc-invoice-table-wrap">
                    <table class="fc-invoice-table">
                        <thead><tr><th scope="col">شرح کالا یا سرویس</th><th scope="col">قیمت واحد</th><th scope="col">تعداد</th><th scope="col">جمع ردیف</th></tr></thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="fc-invoice-item-description">
                                        @if (in_array($item->reference_type, ['App\\Models\\Service', 'App\\Models\\ServiceUpgrade']))
                                            <a href="{{ route('services.show', $item->reference_type == 'App\\Models\\Service' ? $item->reference_id : $item->reference->service_id) }}" class="hover:underline underline-offset-2">{{ $item->description }}</a>
                                        @else
                                            {{ $item->description }}
                                        @endif
                                    </td>
                                    <td>{{ $item->formattedPrice }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="fc-invoice-item-total">{{ $item->formattedTotal }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="fc-invoice-summary">
                <div class="fc-invoice-summary__note">
                    <span class="fc-invoice-summary__note-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <div>
                        <strong>پرداخت امن و قابل پیگیری</strong>
                        <p>وضعیت پرداخت و شناسه تراکنش‌ها از طریق سیستم مالی Paymenter ثبت و بررسی می‌شود.</p>
                    </div>
                </div>
                <dl class="fc-invoice-totals">
                    @if ($invoice->formattedTotal->tax > 0)
                        <div><dt>جمع جزء</dt><dd>{{ $invoice->formattedTotal->format($invoice->formattedTotal->subtotal) }}</dd></div>
                        <div><dt>{{ $invoice->tax->name }} ({{ $invoice->tax->rate }}%)</dt><dd>{{ $invoice->formattedTotal->formatted->tax }}</dd></div>
                    @endif
                    <div class="fc-invoice-totals__grand"><dt>مبلغ قابل پرداخت</dt><dd>{{ $invoice->formattedTotal }}</dd></div>
                </dl>
            </section>

            @if ($invoice->transactions->isNotEmpty())
                <section class="fc-invoice-transactions" aria-labelledby="fcInvoiceTransactionsTitle">
                    <div class="fc-invoice-section-heading">
                        <div><span class="fc-invoice-paper__label">TRANSACTION HISTORY</span><h3 id="fcInvoiceTransactionsTitle">سوابق تراکنش</h3></div>
                        <span class="fc-invoice-item-count">{{ $invoice->transactions->count() }} تراکنش</span>
                    </div>
                    <div class="fc-invoice-table-wrap">
                        <table class="fc-invoice-table fc-invoice-table--transactions">
                            <thead><tr><th scope="col">تاریخ</th><th scope="col">شناسه تراکنش</th><th scope="col">درگاه</th><th scope="col">مبلغ</th><th scope="col">وضعیت</th></tr></thead>
                            <tbody>
                                @foreach ($invoice->transactions->sortByDesc('created_at') as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
                                        <td class="fc-invoice-transaction-id">{{ $transaction->transaction_id ?: '—' }}</td>
                                        <td>
                                            @if ($transaction->is_credit_transaction)
                                                {{ __('invoices.paid_with_credits') }}
                                            @else
                                                {{ $transaction->gateway?->name ?: '—' }}
                                            @endif
                                        </td>
                                        <td>{{ $transaction->formattedAmount }}</td>
                                        <td>
                                            @if ($transaction->status == \App\Enums\InvoiceTransactionStatus::Succeeded)
                                                <span class="fc-invoice-transaction-status fc-invoice-transaction-status--success">{{ __('invoices.transaction_statuses.succeeded') }}</span>
                                            @elseif ($transaction->status == \App\Enums\InvoiceTransactionStatus::Processing)
                                                <span class="fc-invoice-transaction-status fc-invoice-transaction-status--processing"><x-ri-loader-5-fill class="size-3 animate-spin" /> {{ __('invoices.transaction_statuses.processing') }}</span>
                                            @elseif ($transaction->status == \App\Enums\InvoiceTransactionStatus::Failed)
                                                <span class="fc-invoice-transaction-status fc-invoice-transaction-status--failed">{{ __('invoices.transaction_statuses.failed') }}</span>
                                            @else
                                                <span class="fc-invoice-transaction-status">{{ __('invoices.status') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <footer class="fc-invoice-paper__footer">
                <span>FRAGCITY / BILLING</span>
                <p>این سند بر اساس اطلاعات سفارش و تراکنش‌های ثبت‌شده در سیستم مالی صادر شده است.</p>
                <strong>THANK YOU FOR CHOOSING FRAGCITY</strong>
            </footer>
        </article>
    </div>
</div>