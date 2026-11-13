<div class="fc-auth-page fc-auth-page--login" dir="rtl">
    <section class="fc-auth-card" aria-labelledby="fcLoginTitle">
        <a href="https://fragcity.ir/" class="fc-auth-back">
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            بازگشت به سایت اصلی FragCity
        </a>

        <header class="fc-auth-brand">
            <img
                src="https://fragcity.ir/img/webp/newlogo.webp?v=20261102-2"
                onerror="this.onerror=null;this.src='https://fragcity.ir/img/logoresponsive.png?v=20261102-2';"
                alt="FragCity"
                class="fc-auth-brand__logo"
            >
            <span class="fc-auth-brand__eyebrow">FRAGCITY / BILLING</span>
            <h1 id="fcLoginTitle">ورود به حساب کاربری</h1>
            <p>به حساب FragCity خود وارد شو و مدیریت سرویس‌ها و پرداخت‌هایت را ادامه بده.</p>
        </header>

        @if (session('error'))
            <div class="fc-auth-alert" role="alert" aria-live="polite">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (session('status'))
            <div class="fc-auth-alert fc-auth-alert--success" role="status" aria-live="polite">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form class="fc-auth-form" wire:submit="submit" id="login">
            <div class="fc-auth-field">
                <label for="fcLoginEmail">ایمیل حساب</label>
                <input
                    id="fcLoginEmail"
                    class="fc-auth-input fc-auth-input--ltr"
                    type="email"
                    name="email"
                    placeholder="name@example.com"
                    wire:model="email"
                    autocomplete="username"
                    inputmode="email"
                    required
                    autofocus
                >
                @error('email')
                    <p class="fc-auth-field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="fc-auth-field" x-data="{ visible: false }">
                <label for="fcLoginPassword">رمز عبور</label>
                <div class="fc-auth-input-wrap fc-auth-password">
                    <input
                        id="fcLoginPassword"
                        class="fc-auth-input"
                        x-bind:type="visible ? 'text' : 'password'"
                        name="password"
                        placeholder="رمز عبور حساب"
                        wire:model="password"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="fc-auth-password-toggle"
                        x-on:click="visible = !visible"
                        x-bind:aria-label="visible ? 'پنهان‌کردن رمز عبور' : 'نمایش رمز عبور'"
                        aria-controls="fcLoginPassword"
                    >
                        <i class="fa-solid fa-eye" x-show="!visible" aria-hidden="true"></i>
                        <i class="fa-solid fa-eye-slash" x-show="visible" aria-hidden="true"></i>
                    </button>
                </div>
                @error('password')
                    <p class="fc-auth-field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="fc-auth-form-options">
                <label class="fc-auth-remember" for="fcLoginRemember">
                    <input
                        id="fcLoginRemember"
                        type="checkbox"
                        name="remember"
                        value="1"
                        wire:model="remember"
                    >
                    <span class="fc-auth-remember__switch" aria-hidden="true">
                        <span class="fc-auth-remember__thumb"></span>
                    </span>
                    <span class="fc-auth-remember__text">
                        <strong>مرا به خاطر بسپار</strong>
                        <small>برای ورود سریع‌تر در دفعات بعد</small>
                    </span>
                </label>

                <a class="fc-auth-forgot" href="{{ route('password.request') }}" wire:navigate>
                    فراموشی رمز عبور
                </a>
            </div>

            <div class="fc-auth-captcha">
                <x-captcha :form="'login'" />
            </div>

            <button
                class="fc-auth-submit"
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    ورود به Billing
                </span>
                <span class="fc-auth-submit__loading" wire:loading wire:target="submit">
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    در حال ورود...
                </span>
            </button>
        </form>

        @if ($fragcitySsoEnabled ?? false)
            <div class="fc-auth-separator">یا با حساب اصلی FragCity وارد شو</div>

            <a
                href="{{ route('extensions.fragcity-api.sso.start', ['redirect' => request()->query('redirect', '/')]) }}"
                class="fc-auth-sso-button"
            >
                <i class="fa-brands fa-steam" aria-hidden="true"></i>
                ورود با Steam / حساب FragCity
            </a>

            <p class="fc-auth-sso-note">
                با انتخاب این گزینه به سایت اصلی FragCity منتقل می‌شوی. می‌توانی با Steam یا حساب کاربری FragCity وارد شوی.
            </p>
        @endif

        <div class="fc-auth-bottom">
            @if (!config('settings.registration_disabled', false))
                <span>حساب کاربری نداری؟</span>
                <a href="{{ route('register') }}" wire:navigate>ثبت‌نام کن</a>
                <span class="fc-auth-bottom__divider" aria-hidden="true">|</span>
            @endif
            <a href="https://fragcity.ir/">صفحه اصلی FragCity</a>
        </div>
    </section>
</div>
