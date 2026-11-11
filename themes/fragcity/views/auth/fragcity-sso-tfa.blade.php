<div class="container mt-20 fc-sso-tfa-page" dir="rtl">
    <div class="fc-sso-tfa-card">
        <span class="fc-sso-tfa-card__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
        <span class="fc-portal-kicker">FRAGCITY / SECURITY CHECK</span>
        <h1>تأیید دومرحله‌ای</h1>
        <p>برای ورود امن به Billing، کد یک‌بارمصرف اپلیکیشن احراز هویت Paymenter را وارد کن.</p>

        <form wire:submit="verify" class="fc-sso-tfa-form">
            <x-form.input
                name="code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                label="کد تأیید"
                placeholder="کد ۶ رقمی"
                wire:model="code"
                required
            />
            @error('code')
                <p class="fc-sso-tfa-error" role="alert">{{ $message }}</p>
            @enderror
            <x-button.primary type="submit" class="w-full" wire:loading.attr="disabled">
                <x-loading target="verify" />
                <span wire:loading.remove wire:target="verify">تأیید و ادامه به Billing</span>
            </x-button.primary>
        </form>

        <a href="{{ route('login') }}" class="fc-sso-tfa-back">بازگشت به ورود</a>
    </div>
</div>
