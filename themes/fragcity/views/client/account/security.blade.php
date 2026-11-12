<div class="container fc-portal-page fc-account-page fc-account-security-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading">
        <div><span class="fc-portal-kicker">FRAGCITY / امنیت</span><h1>امنیت حساب</h1><p>نشست‌های فعال، رمز عبور و ورود دومرحله‌ای را کنترل کن.</p></div>
    </div>

    <div class="fc-account-content fc-security-grid">
        <section class="fc-security-card">
            <h2><i class="fa-solid fa-laptop ml-2 text-orange-300" aria-hidden="true"></i> نشست‌های فعال</h2>
            @forelse (Auth::user()->sessions as $session)
                <div class="fc-security-session">
                    <div class="min-w-0">
                        <p><strong>{{ $session->ip_address }}</strong></p>
                        <p>{{ $session->formatted_device }}</p>
                        <p>{{ $session->last_activity->format('Y/m/d H:i') }}</p>
                    </div>
                    @if(!$session->is_current_device)
                        <x-button.primary wire:click="logoutSession('{{ $session->id }}')" class="!w-fit !px-3 text-xs">
                            خروج
                        </x-button.primary>
                    @else
                        <span class="shrink-0 rounded-full border border-green-500/20 bg-green-500/10 px-2 py-1 text-[.65rem] text-green-300">این دستگاه</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400">نشست فعالی برای نمایش وجود ندارد.</p>
            @endforelse
        </section>

        <section class="fc-security-card">
            <h2><i class="fa-solid fa-key ml-2 text-orange-300" aria-hidden="true"></i> تغییر رمز عبور</h2>
            <p class="mb-4 text-xs leading-6 text-gray-400">برای امنیت بیشتر از رمز عبور طولانی و غیرقابل حدس استفاده کن.</p>
            <form wire:submit="changePassword">
                <x-form.input divClass="w-full" name="current_password" type="password"
                    label="رمز عبور فعلی" placeholder="رمز فعلی" wire:model="current_password" required />
                <x-form.input name="password" type="password" label="رمز عبور جدید"
                    placeholder="رمز جدید" wire:model="password" required />
                <x-form.input name="password_confirmation" type="password" label="تکرار رمز جدید"
                    placeholder="رمز جدید را دوباره وارد کن" wire:model="password_confirmation" required />
                <x-button.primary type="submit" class="w-full mt-3">به‌روزرسانی رمز عبور</x-button.primary>
            </form>
        </section>

        <section class="fc-security-card">
            <h2><i class="fa-solid fa-shield-halved ml-2 text-orange-300" aria-hidden="true"></i> تأیید دومرحله‌ای</h2>
            @if ($twoFactorEnabled)
                <div class="rounded-xl border border-green-500/20 bg-green-500/5 p-3 text-sm text-green-300">
                    <i class="fa-solid fa-circle-check ml-1" aria-hidden="true"></i>
                    تأیید دومرحله‌ای برای حساب فعال است.
                </div>
                <x-button.primary class="w-full mt-4" x-on:click="$store.confirmation.confirm({
                    title: 'غیرفعال‌کردن تأیید دومرحله‌ای',
                    message: 'آیا از غیرفعال‌کردن لایه امنیتی دوم حساب مطمئن هستی؟',
                    confirmText: 'بله، غیرفعال کن',
                    cancelText: 'انصراف',
                    callback: () => $wire.disableTwoFactor()
                })">
                    غیرفعال‌کردن تأیید دومرحله‌ای
                </x-button.primary>
            @else
                <p class="text-xs leading-6 text-gray-400">با فعال‌سازی این قابلیت، هنگام ورود علاوه بر رمز عبور به کد یک‌بارمصرف نیاز خواهی داشت.</p>
                <x-button.primary wire:click="enableTwoFactor" class="w-full mt-4">
                    فعال‌سازی تأیید دومرحله‌ای
                </x-button.primary>
                @if ($showEnableTwoFactor)
                    <x-modal title="راه‌اندازی تأیید دومرحله‌ای" open="true">
                        <p class="text-sm leading-6 text-gray-300">کد QR را با برنامه Authenticator اسکن کن و کد شش‌رقمی را وارد کن.</p>
                        <div class="flex flex-col items-center mt-4">
                            <img src="{{ $twoFactorData['image'] }}" alt="کد QR ورود دومرحله‌ای" class="w-64 h-64 rounded-xl bg-white p-2" />
                            <p class="text-orange-200 mt-3 text-sm text-center">کلید پشتیبان: <span class="select-all font-mono">{{ $twoFactorData['secret'] }}</span></p>
                        </div>
                        <form wire:submit.prevent="enableTwoFactor" class="mt-5">
                            <x-form.input name="two_factor_code" type="text" label="کد تأیید"
                                placeholder="کد شش‌رقمی" wire:model="twoFactorCode" required />
                            <x-button.primary class="w-full mt-4" type="submit">تأیید و فعال‌سازی</x-button.primary>
                        </form>
                        <x-slot name="closeTrigger">
                            <button @click="document.location.reload()" class="text-primary-100" aria-label="بستن">
                                <x-ri-close-fill class="size-6" />
                            </button>
                        </x-slot>
                    </x-modal>
                @endif
            @endif
        </section>
    </div>
</div>