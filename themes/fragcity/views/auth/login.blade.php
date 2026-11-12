<form
    class="fc-auth-login-card"
    wire:submit="submit" id="login">
    <div class="fc-auth-login-brand">
        <img src="https://fragcity.ir/img/webp/newlogo.webp?v=20261102-2"
             alt="FragCity" onerror="this.onerror=null;this.src='https://fragcity.ir/img/logoresponsive.png';">
        <h1>ورود به حساب کاربری</h1>
        <p>به فضای مدیریت سرور و خدمات FragCity خوش آمدی.</p>
    </div>

    <x-form.input name="email" type="email" label="ایمیل حساب"
        placeholder="ایمیل ثبت‌شده در حساب" wire:model="email"
        hideRequiredIndicator required autocomplete="email" />

    <x-form.input name="password" type="password" label="رمز عبور"
        placeholder="رمز عبور حساب" required hideRequiredIndicator
        wire:model="password" autocomplete="current-password" />

    <div class="flex flex-row items-center justify-between gap-3 mt-1">
        <x-form.checkbox name="remember" label="مرا به خاطر بسپار" wire:model="remember" />
        <a class="text-sm text-secondary-500 text-secondary hover:underline"
            href="{{ route('password.request') }}">
            فراموشی رمز عبور
        </a>
    </div>

    <x-captcha :form="'login'" />

    <x-button.primary class="w-full mt-4" type="submit">
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
        ورود به حساب
    </x-button.primary>

    @if ($fragcitySsoEnabled ?? false)
    <div class="fc-auth-separator">یا ورود سریع با حساب FragCity</div>
    <a href="{{ route('extensions.fragcity-api.sso.start', ['redirect' => request()->query('redirect', '/')]) }}"
        class="fc-steam-sso-button">
        <i class="fa-brands fa-steam text-xl" aria-hidden="true"></i>
        ورود با Steam
    </a>
    <p class="mt-3 text-center text-xs leading-6 text-gray-400">
        با انتخاب این گزینه به سایت اصلی FragCity منتقل می‌شوی و با Steam یا حساب FragCity وارد Billing خواهی شد.
    </p>
    @endif

    {!! hook('auth.login') !!}

    @if (config('settings.oauth_github') || config('settings.oauth_google') || config('settings.oauth_discord'))
    <div class="flex flex-row flex-wrap justify-center mt-5 gap-3">
        @foreach (['github', 'google', 'discord'] as $provider)
            @if (config('settings.oauth_' . $provider))
            <a href="{{ route('oauth.redirect', $provider) }}"
                class="flex items-center justify-center px-4 h-10 border border-neutral rounded-md text-primary-100">
                <img src="/assets/images/{{ $provider }}-dark.svg" alt="{{ $provider }}" class="size-5 ml-2">
                {{ __(ucfirst($provider)) }}
            </a>
            @endif
        @endforeach
    </div>
    @endif

    @if(!config('settings.registration_disabled', false))
    <div class="text-sm text-center rounded-md py-2 mt-5 text-gray-300">
        حساب کاربری نداری؟
        <a class="font-bold text-secondary hover:underline" href="{{ route('register') }}" wire:navigate>ثبت‌نام کن</a>
    </div>
    @endif
</form>