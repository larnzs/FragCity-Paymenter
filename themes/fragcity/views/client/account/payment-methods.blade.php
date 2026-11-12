<div class="container fc-portal-page fc-account-page fc-account-payment-methods-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading">
        <div><span class="fc-portal-kicker">FRAGCITY / PAYMENT METHODS</span><h1>روش‌های پرداخت</h1><p>روش‌های پرداخت ذخیره‌شده و تراکنش‌های اخیر حساب خود را مدیریت کن.</p></div>
    </div>

    @if($setupModalVisible)
        <x-modal title="افزودن روش پرداخت" open="true">
            <x-slot name="closeTrigger">
                <button wire:click="$set('setupModalVisible', false)" class="text-primary-100" aria-label="بستن">
                    <x-ri-close-fill class="size-6" />
                </button>
            </x-slot>
            @if(count($this->gateways) > 1)
                <x-form.select name="gateway" label="درگاه پرداخت" wire:model.live="gateway" required>
                    @foreach($this->gateways as $gateway)
                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                    @endforeach
                </x-form.select>
            @elseif(count($this->gateways) === 0)
                <p class="text-sm text-red-300">در حال حاضر درگاه پرداختی برای ذخیره روش پرداخت فعال نیست.</p>
            @endif

            <x-button.primary class="w-full mt-4" wire:click="createBillingAgreement" wire:loading.attr="disabled">
                <x-loading target="createBillingAgreement" />
                <span wire:loading.remove wire:target="createBillingAgreement">ادامه و ثبت روش پرداخت</span>
            </x-button.primary>

            @if ($this->setup)
                <x-modal title="تأیید روش پرداخت" open>
                    <div class="mt-8">{{ $this->setup }}</div>
                    <x-slot name="closeTrigger">
                        <button wire:confirm="آیا از لغو فرایند ثبت روش پرداخت مطمئن هستی؟"
                                wire:click="cancelSetup" wire:loading.attr="disabled"
                                wire:target="cancelSetup" class="text-primary-100" aria-label="لغو">
                            <x-ri-close-fill class="size-6" />
                        </button>
                    </x-slot>
                </x-modal>
            @endif
        </x-modal>
    @endif

    <div class="fc-account-content fc-payment-methods-grid">
        <section class="fc-payment-methods-section">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h2>روش‌های ذخیره‌شده</h2>
                    <p>روش‌های پرداخت متصل به حساب تو</p>
                </div>
                @if(count($this->gateways) > 0)
                    <x-button.primary class="!w-fit shrink-0" wire:click="$set('setupModalVisible', true)"
                        wire:loading.attr="disabled" wire:target="setupModalVisible">
                        <x-ri-add-line class="size-4" /> افزودن
                    </x-button.primary>
                @endif
            </div>

            @php($groupedAgreements = $billingAgreements->groupBy('gateway.name'))

            @if($groupedAgreements->count() > 0)
                @foreach($groupedAgreements as $gatewayName => $agreements)
                    <div class="flex items-center justify-between gap-3 mt-3 mb-2">
                        <div class="flex items-center gap-3">
                            <div class="bg-background-secondary border border-neutral rounded-lg overflow-hidden size-9 flex items-center justify-center">
                                @if($agreements->first()?->gateway?->meta?->icon)
                                    <img src="{{ $agreements->first()->gateway->meta->icon }}" alt="{{ $gatewayName }}" class="size-9" />
                                @else
                                    <x-ri-secure-payment-line class="size-5 text-primary" />
                                @endif
                            </div>
                            <h3>{{ $gatewayName }}</h3>
                        </div>
                        <span class="rounded-full bg-orange-500/10 border border-orange-500/20 px-2 py-1 text-xs font-bold text-orange-200">
                            {{ $agreements->count() }} روش
                        </span>
                    </div>

                    @foreach($agreements as $agreement)
                        <article class="fc-account-card">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="rounded-lg overflow-hidden flex items-center justify-center shrink-0">
                                        @switch(strtolower($agreement->type))
                                            @case('visa')<x-icons.visa class="size-9" />@break
                                            @case('mastercard')<x-icons.mastercard class="size-9" />@break
                                            @case('amex')<x-icons.amex class="size-9" />@break
                                            @case('american express')<x-icons.american-express class="size-9" />@break
                                            @case('discover')<x-icons.discover class="size-9" />@break
                                            @case('paypal')<x-icons.paypal class="size-9" />@break
                                            @case('sepa_debit')<x-icons.sepa class="size-9" />@break
                                            @case('ideal')<x-icons.ideal class="size-9" />@break
                                            @case('bancontact')<x-icons.bancontact class="size-9" />@break
                                            @case('sofort')<x-icons.sofort class="size-9" />@break
                                            @case('us_bank_account')
                                            @case('bacs_debit')
                                            @case('au_becs_debit')<x-icons.bank-debit class="size-9" />@break
                                            @default<x-ri-bank-card-line class="size-6 text-primary" />
                                        @endswitch
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-sm">{{ $agreement->name }}</div>
                                        @if($agreement->expiry)
                                            <div class="mt-1 text-xs text-neutral-400">تاریخ انقضا: {{ \Carbon\Carbon::parse($agreement->expiry)->format('m/Y') }}</div>
                                        @endif
                                        @if($agreement->services()->count() > 0)
                                            <div class="mt-1 text-xs text-neutral-400">{{ $agreement->services()->count() }} سرویس متصل</div>
                                        @endif
                                    </div>
                                </div>

                                <x-button.danger class="!w-fit !px-3 shrink-0"
                                    x-on:click="$store.confirmation.confirm({
                                        title: 'حذف روش پرداخت',
                                        message: @js('آیا از حذف روش پرداخت ' . $agreement->name . ' مطمئن هستی؟'),
                                        confirmText: 'حذف',
                                        cancelText: 'انصراف',
                                        callback: () => $wire.removePaymentMethod('{{ $agreement->ulid }}')
                                    })">
                                    <x-ri-delete-bin-line class="size-4" />
                                    <span class="hidden sm:inline">حذف</span>
                                </x-button.danger>
                            </div>
                        </article>
                    @endforeach
                @endforeach
            @else
                <div class="fc-portal-empty">
                    <span><x-ri-bank-card-line class="size-8" /></span>
                    <strong>هنوز روش پرداختی ذخیره نکرده‌ای.</strong>
                    <p>پس از افزودن یک روش پرداخت، در این بخش نمایش داده می‌شود.</p>
                </div>
            @endif
        </section>

        <section class="fc-payment-methods-section">
            <div class="mb-4">
                <h2>تراکنش‌های اخیر</h2>
                <p>پرداخت‌های انجام‌شده و وضعیت آن‌ها</p>
            </div>

            @forelse ($transactions as $transaction)
                <a href="{{ route('invoices.show', $transaction->invoice) }}" wire:navigate class="fc-payment-transaction">
                    <div class="fc-payment-transaction__meta">
                        <strong class="block text-gray-100">
                            {{ $transaction->transaction_id ? 'شناسه تراکنش: ' . $transaction->transaction_id : 'شناسه تراکنش ثبت نشده' }}
                        </strong>
                        <span>{{ $transaction->formattedAmount }} <span class="text-gray-500">با درگاه</span> {{ $transaction->gateway ? $transaction->gateway->name : 'نامشخص' }}</span>
                        <span class="block mt-1">
                            @if($transaction->status === \App\Enums\InvoiceTransactionStatus::Succeeded)
                                <span class="text-green-300">● موفق</span>
                            @elseif($transaction->status === \App\Enums\InvoiceTransactionStatus::Processing)
                                <span class="text-yellow-300">● در حال پردازش</span>
                            @elseif($transaction->status === \App\Enums\InvoiceTransactionStatus::Failed)
                                <span class="text-red-300">● ناموفق</span>
                            @else
                                <span class="text-gray-400">● نامشخص</span>
                            @endif
                        </span>
                    </div>
                    <span class="fc-payment-transaction__date">{{ $transaction->created_at->format('Y/m/d H:i') }}</span>
                </a>
            @empty
                <div class="fc-portal-empty">
                    <span><x-ri-bill-line class="size-8" /></span>
                    <strong>تراکنشی برای نمایش وجود ندارد.</strong>
                    <p>سوابق پرداخت‌های بعدی در این بخش ثبت می‌شوند.</p>
                </div>
            @endforelse

            <div class="fc-portal-pagination">{{ $transactions->links() }}</div>
        </section>
    </div>
</div>