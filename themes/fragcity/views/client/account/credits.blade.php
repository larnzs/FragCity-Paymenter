<div class="container fc-portal-page fc-account-page fc-account-credits-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / کیف پول</span>
            <h1>کیف پول</h1>
            <p>موجودی اعتبار و افزایش موجودی حساب Billing را مدیریت کن.</p>
        </div>
    </div>

    <div class="fc-account-content fc-credits-layout">
        <section class="fc-credits-balance-card">
            <div class="fc-account-card__heading">
                <span><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                <div><h2>موجودی حساب</h2><p>اعتبار ثبت‌شده در Paymenter</p></div>
            </div>

            @forelse (Auth::user()->credits as $credit)
                <div class="fc-credit-balance-item">
                    <span class="text-sm text-gray-300">موجودی {{ $credit->currency->code }}</span>
                    <strong>{{ $credit->formattedAmount }}</strong>
                </div>
            @empty
                <div class="fc-portal-empty mt-3">
                    <span><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
                    <strong>هنوز اعتباری در کیف پول ثبت نشده است.</strong>
                    <p>پس از اولین شارژ، موجودی این بخش نمایش داده می‌شود.</p>
                </div>
            @endforelse

            <div class="mt-5 rounded-xl border border-orange-500/15 bg-orange-500/5 p-3 text-xs leading-6 text-gray-400">
                <i class="fa-solid fa-circle-info ml-1 text-orange-300" aria-hidden="true"></i>
                موجودی این صفحه مستقیماً از کیف پول Paymenter خوانده می‌شود.
            </div>
        </section>

        <section class="fc-credits-form-card">
            <div class="fc-account-card__heading">
                <span><i class="fa-solid fa-circle-plus" aria-hidden="true"></i></span>
                <div><h2>افزایش موجودی</h2><p>مبلغ و درگاه پرداخت را انتخاب کن.</p></div>
            </div>

            <form wire:submit.prevent="addCredit" class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <x-form.select name="currency" label="واحد پول" wire:model.live="currency" required>
                        @foreach(\App\Models\Currency::all() as $currency)
                            <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                        @endforeach
                    </x-form.select>

                    <x-form.input x-mask:dynamic="$money($input, '.', '', 2)" name="amount" type="number"
                        label="مبلغ شارژ" placeholder="مبلغ مورد نظر" wire:model.live.debounce.250ms="amount" required />

                    <div class="col-span-2">
                        <x-form.select name="gateway" label="درگاه پرداخت" wire:model.live="gateway" required>
                            @foreach($gateways as $gatewayy)
                                <option value="{{ $gatewayy->id }}" wire:key="{{ $gatewayy->id }}" @if($gatewayy->id == $gateway) selected @endif>{{ $gatewayy->name }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-black/20 p-3 text-xs leading-6 text-gray-400">
                    مبلغ نهایی پیش از پرداخت به‌صورت خودکار در فاکتور نمایش داده می‌شود. اطلاعات مبلغ را فقط از فرم همین صفحه وارد کن.
                </div>

                <x-button.primary type="submit" class="w-full">
                    <i class="fa-solid fa-lock ml-2" aria-hidden="true"></i>
                    ادامه و پرداخت امن
                </x-button.primary>
            </form>
        </section>
    </div>
</div>