<div class="container mt-14 fc-portal-page fc-account-page fc-account-credits-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading"><div><span class="fc-portal-kicker">FRAGCITY / WALLET</span><h1>کیف پول</h1><p>اعتبار حساب و شارژ کیف پول Paymenter را مدیریت کن.</p></div></div>
    <div class="fc-account-content px-2">
        @if (Auth::user()->credits->count() > 0)
        <div class="flex flex-wrap gap-4">
            @foreach (Auth::user()->credits as $credit)
            <div class="flex flex-col bg-background-secondary w-fit rounded-lg px-5 p-3 items-center gap-1">
                <h5 class="text-lg font-bold">{{ $credit->currency->code }}</h5>
                <p class="text-primary-100">{{ $credit->formattedAmount }}</p>
            </div>
            @endforeach
        </div>
        @else
        <p>{{ __('account.no_credit') }}</p>
        @endif

        <h4 class="text-xl font-bold pb-3">شارژ کیف پول</h4>

        <form wire:submit.prevent="addCredit">
            <!-- Currency and amount -->
            <div class="grid grid-cols-2 gap-4">
                <x-form.select name="currency" :label="__('account.input.currency')" wire:model.live="currency" required>
                    @foreach(\App\Models\Currency::all() as $currency)
                    <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input x-mask:dynamic="$money($input, '.', '', 2)" name="amount" type="number"
                    :label="__('account.input.amount')" :placeholder="__('account.input.amount_placeholder')"
                    wire:model.live.debounce.250ms="amount" required />

                <x-form.select name="gateway" :label="__('product.payment_method')" wire:model.live="gateway" required>
                    @foreach($gateways as $gatewayy)
                    <option value="{{ $gatewayy->id }}" wire:key="{{ $gatewayy->id }}" @if($gatewayy->id == $gateway) selected @endif>{{ $gatewayy->name }}</option>
                    @endforeach
                </x-form.select>
            </div>


            <x-button.primary type="submit" class="w-full mt-4">
                {{ __('account.add_credit') }}
            </x-button.primary>
        </form>
    </div>
</div>