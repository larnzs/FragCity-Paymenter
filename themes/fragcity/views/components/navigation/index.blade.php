@php
    $fragcityMain = 'https://fragcity.ir';
    $fragcityFallbackAvatar = $fragcityMain . '/img/logoresponsive.png';

    // Keep account-menu copy Persian even when Paymenter's global locale is English.
    $fragcityAccountNav = static function (array $nav): array {
        return match (mb_strtolower(trim((string) ($nav['name'] ?? '')))) {
            'dashboard' => ['label' => 'داشبورد', 'icon' => 'fa-gauge-high'],
            'tickets' => ['label' => 'تیکت‌ها و پشتیبانی', 'icon' => 'fa-headset'],
            'account' => ['label' => 'تنظیمات حساب', 'icon' => 'fa-user-gear'],
            'admin' => ['label' => 'پنل مدیریت', 'icon' => 'fa-shield-halved'],
            default => ['label' => (string) ($nav['name'] ?? 'حساب کاربری'), 'icon' => 'fa-arrow-up-right-from-square'],
        };
    };
@endphp

<header class="shock-header" id="siteHeader">
    <div class="shock-container">
        <div class="shock-brand">
            <a href="{{ $fragcityMain }}/support/" class="fc-support-toggle" aria-label="پشتیبانی" title="پشتیبانی">
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                <span class="fc-support-toggle__label">پشتیبانی</span>
            </a>
            <a href="{{ $fragcityMain }}/" class="shock-logo-link" aria-label="صفحه اصلی FragCity">
                <img src="{{ $fragcityMain }}/img/webp/newlogo.webp?v=20261102-2"
                     onerror="this.onerror=null;this.src='{{ $fragcityFallbackAvatar }}';"
                     alt="FragCity" class="shock-logo-img">
            </a>
        </div>

        <nav class="shock-nav" aria-label="منوی اصلی">
            <a href="{{ $fragcityMain }}/servers/" class="shock-nav-item">
                <i class="fa-solid fa-server" aria-hidden="true"></i><span>لیست سرورها</span>
            </a>
            <a href="{{ $fragcityMain }}/skins/" class="shock-nav-item">
                <i class="fa-solid fa-palette" aria-hidden="true"></i><span>انتخاب اسکین</span>
            </a>
            <a href="/" class="shock-nav-item active" aria-current="page">
                <i class="fa-solid fa-store" aria-hidden="true"></i><span>فروشگاه</span>
            </a>
            <a href="{{ $fragcityMain }}/leaderboard/" class="shock-nav-item">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i><span>لیدربورد</span>
            </a>
            <a href="{{ $fragcityMain }}/banlist/" class="shock-nav-item">
                <i class="fa-solid fa-gavel" aria-hidden="true"></i><span>لیست محرومیت‌ها</span>
            </a>
            <a href="{{ $fragcityMain }}/vip/" class="shock-nav-item shock-nav-item--vip">
                <i class="fa-solid fa-crown" aria-hidden="true"></i><span>VIP</span>
            </a>

            <div class="frag-dropdown" id="shockMoreDropdown">
                <button type="button" class="shock-nav-item" id="shockMoreButton"
                        aria-label="بیشتر" title="بیشتر" aria-expanded="false" aria-controls="shockMoreMenu">
                    <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                    <span>بیشتر</span>
                    <i class="fa-solid fa-chevron-down frag-arrow" aria-hidden="true"></i>
                </button>
                <div class="frag-dropdown-menu" id="shockMoreMenu">
                    <a href="{{ $fragcityMain }}/serverguide/" class="frag-drop-link">
                        <i class="fa-solid fa-book-open" aria-hidden="true"></i><span>راهنمای سرور</span>
                    </a>
                    <a href="{{ $fragcityMain }}/launcher/" class="frag-drop-link">
                        <i class="fa-solid fa-download" aria-hidden="true"></i><span>دانلود لانچر</span>
                    </a>
                    <a href="{{ $fragcityMain }}/status/" class="frag-drop-link">
                        <i class="fa-solid fa-heart-pulse" aria-hidden="true"></i><span>وضعیت سرویس‌ها</span>
                    </a>
                    <a href="{{ $fragcityMain }}/about/" class="frag-drop-link">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>درباره FragCity</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="shock-user-area-wrapper">
            <button type="button" class="fc-motion-toggle" aria-label="حالت عملکرد"
                    data-motion-toggle aria-pressed="false" title="حالت بدون انیمیشن">
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span class="fc-motion-toggle__label">حالت عملکرد</span>
            </button>

            @auth
                <div class="frag-user-area">
                    <div class="frag-dropdown" id="shockUserDropdown">
                        <button type="button" class="frag-user-trigger" id="shockUserButton"
                                aria-label="باز کردن منوی حساب کاربری" title="حساب کاربری"
                                aria-expanded="false" aria-controls="shockUserMenu">
                            <img src="{{ auth()->user()->avatar ?: $fragcityFallbackAvatar }}"
                                 class="frag-avatar" alt="آواتار {{ auth()->user()->name }}"
                                 onerror="this.onerror=null;this.src='{{ $fragcityFallbackAvatar }}';">
                            <span class="frag-username">{{ auth()->user()->name }}</span>
                            <i class="fa-solid fa-chevron-down frag-arrow" aria-hidden="true"></i>
                        </button>

                        <div class="frag-dropdown-menu" id="shockUserMenu">
                            <div class="frag-drop-header frag-drop-header--profile">
                                <span class="frag-drop-avatar-frame">
                                    <img src="{{ auth()->user()->avatar ?: $fragcityFallbackAvatar }}"
                                         class="frag-drop-avatar" alt=""
                                         onerror="this.onerror=null;this.src='{{ $fragcityFallbackAvatar }}';">
                                </span>
                                <div class="frag-drop-info">
                                    <span class="frag-drop-eyebrow">حساب کاربری FragCity</span>
                                    <span class="frag-drop-name">{{ auth()->user()->name }}</span>
                                    <span class="frag-drop-account-label">ایمیل حساب</span>
                                    <span class="frag-drop-steam">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                            <div class="frag-drop-divider"></div>
                            <a href="{{ route('services') }}" class="frag-drop-link" wire:navigate>
                                <i class="fa-solid fa-server" aria-hidden="true"></i><span>سرورهای من</span>
                            </a>
                            <a href="{{ route('invoices') }}" class="frag-drop-link" wire:navigate>
                                <i class="fa-solid fa-receipt" aria-hidden="true"></i><span>فاکتورها و پرداخت‌ها</span>
                            </a>
                            @if(!config('settings.tickets_disabled', false))
                            <a href="{{ route('tickets') }}" class="frag-drop-link" wire:navigate>
                                <i class="fa-solid fa-headset" aria-hidden="true"></i><span>تیکت‌های پشتیبانی</span>
                            </a>
                            @endif
                            <a href="{{ route('account') }}" class="frag-drop-link" wire:navigate>
                                <i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>تنظیمات حساب</span>
                            </a>
                            @foreach (\App\Classes\Navigation::getAccountDropdownLinks() as $nav)
                                @if(mb_strtolower(trim((string) ($nav['name'] ?? ''))) === 'admin')
                                    <a href="{{ $nav['url'] }}" class="frag-drop-link">
                                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>پنل مدیریت</span>
                                    </a>
                                @endif
                            @endforeach
                            <div class="frag-drop-divider"></div>
                            <div class="frag-drop-wallet">
                                <div class="frag-drop-wallet__balances">
                                    <span class="frag-drop-wallet__label">موجودی کیف پول</span>
                                    @forelse(auth()->user()->credits as $credit)
                                        <strong class="frag-drop-wallet__balance">{{ $credit->formattedAmount }}</strong>
                                        <span class="frag-drop-wallet__currency">{{ $credit->currency->code }}</span>
                                    @empty
                                        <strong class="frag-drop-wallet__balance">۰</strong>
                                        <span class="frag-drop-wallet__currency">اعتباری ثبت نشده</span>
                                    @endforelse
                                </div>
                                <a href="{{ route('account.credits') }}" class="frag-drop-wallet__add"
                                   aria-label="شارژ کیف پول" title="شارژ کیف پول" wire:navigate>
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </a>
                            </div>
                            <div class="frag-drop-divider"></div>
                            <div class="frag-drop-logout">
                                <livewire:auth.logout />
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="shock-auth-actions">
                    <a href="{{ route('login') }}" class="frag-login-btn" data-auth-modal-open
                       aria-haspopup="dialog" aria-controls="fcLoginModal" aria-expanded="false">
                        <i class="fa-brands fa-steam" aria-hidden="true"></i><span>ورود با استیم</span>
                    </a>
                    @if (!config('settings.registration_disabled', false))
                        <a href="{{ route('register') }}" class="shock-register-btn">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>ثبت‌نام</span>
                        </a>
                    @endif
                </div>
            @endauth

            <button type="button" class="shock-mobile-toggle" id="mobileNavOpen"
                    aria-label="باز کردن منو" aria-expanded="false" aria-controls="mobileNav">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</header>

<div class="mobile-overlay" id="mobileOverlay" aria-hidden="true"></div>

<nav class="mobile-nav" id="mobileNav" aria-label="منوی موبایل" aria-hidden="true">
    <div class="mobile-nav-header">
        <a href="{{ $fragcityMain }}/" class="mobile-nav-logo" aria-label="صفحه اصلی FragCity">
            <img src="{{ $fragcityMain }}/img/webp/newlogo.webp?v=20261102-2"
                 onerror="this.onerror=null;this.src='{{ $fragcityFallbackAvatar }}';" alt="FragCity">
            <span>FRAGCITY</span>
        </a>
        <button type="button" class="mobile-nav-close" id="mobileNavClose" aria-label="بستن منو">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <div class="mobile-nav-links">
        <button type="button" class="mobile-nav-link fc-motion-toggle-mobile"
                aria-label="حالت عملکرد" data-motion-toggle aria-pressed="false">
            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
            <span class="fc-motion-toggle__label">حالت عملکرد</span>
            <span class="fc-motion-toggle__state" aria-hidden="true"></span>
        </button>

        <a href="{{ $fragcityMain }}/servers/" class="mobile-nav-link"><i class="fa-solid fa-server" aria-hidden="true"></i><span>لیست سرورها</span></a>
        <a href="{{ $fragcityMain }}/skins/" class="mobile-nav-link"><i class="fa-solid fa-palette" aria-hidden="true"></i><span>انتخاب اسکین</span></a>
        <a href="/" class="mobile-nav-link active" aria-current="page"><i class="fa-solid fa-store" aria-hidden="true"></i><span>فروشگاه</span></a>
        <a href="{{ $fragcityMain }}/leaderboard/" class="mobile-nav-link"><i class="fa-solid fa-trophy" aria-hidden="true"></i><span>لیدربورد</span></a>
        <a href="{{ $fragcityMain }}/banlist/" class="mobile-nav-link"><i class="fa-solid fa-gavel" aria-hidden="true"></i><span>لیست محرومیت‌ها</span></a>
        <a href="{{ $fragcityMain }}/vip/" class="mobile-nav-link mobile-nav-link--vip"><i class="fa-solid fa-crown" aria-hidden="true"></i><span>VIP</span></a>

        <details class="mobile-nav-more">
            <summary class="mobile-nav-link mobile-nav-more-toggle">
                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i><span>بیشتر</span>
                <i class="fa-solid fa-chevron-down mobile-nav-more-arrow" aria-hidden="true"></i>
            </summary>
            <div class="mobile-nav-more-menu">
                <a href="{{ $fragcityMain }}/serverguide/" class="mobile-nav-link"><i class="fa-solid fa-book-open" aria-hidden="true"></i><span>راهنمای سرور</span></a>
                <a href="{{ $fragcityMain }}/launcher/" class="mobile-nav-link"><i class="fa-solid fa-download" aria-hidden="true"></i><span>دانلود لانچر</span></a>
                <a href="{{ $fragcityMain }}/status/" class="mobile-nav-link"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i><span>وضعیت سرویس‌ها</span></a>
                <a href="{{ $fragcityMain }}/about/" class="mobile-nav-link"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>درباره FragCity</span></a>
            </div>
        </details>

        @auth
            <div class="mobile-nav-profile">
                <img src="{{ auth()->user()->avatar ?: $fragcityFallbackAvatar }}" alt=""
                     onerror="this.onerror=null;this.src='{{ $fragcityFallbackAvatar }}';">
                <div>
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->email }}</span>
                </div>
            </div>
            <a href="{{ route('services') }}" class="mobile-nav-link" wire:navigate><i class="fa-solid fa-server" aria-hidden="true"></i><span>سرورهای من</span></a>
            <a href="{{ route('invoices') }}" class="mobile-nav-link" wire:navigate><i class="fa-solid fa-receipt" aria-hidden="true"></i><span>فاکتورها و پرداخت‌ها</span></a>
            <a href="{{ route('account.credits') }}" class="mobile-nav-link" wire:navigate><i class="fa-solid fa-wallet" aria-hidden="true"></i><span>کیف پول و شارژ</span></a>
            <a href="{{ route('account') }}" class="mobile-nav-link" wire:navigate><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>تنظیمات حساب</span></a>
            @foreach (\App\Classes\Navigation::getAccountDropdownLinks() as $nav)
                @if(mb_strtolower(trim((string) ($nav['name'] ?? ''))) === 'admin')
                    <a href="{{ $nav['url'] }}" class="mobile-nav-link"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>پنل مدیریت</span></a>
                @endif
            @endforeach
            @endif
            <div class="mobile-nav-logout"><livewire:auth.logout /></div>
        @else
            <a href="{{ route('login') }}" class="mobile-nav-link mobile-steam-login-link"
               data-auth-modal-open aria-haspopup="dialog" aria-controls="fcLoginModal" aria-expanded="false">
                <i class="fa-brands fa-steam" aria-hidden="true"></i><span>ورود با استیم</span>
            </a>
            @if (!config('settings.registration_disabled', false))
                <a href="{{ route('register') }}" class="mobile-nav-link"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>ساخت حساب کاربری</span></a>
            @endif
        @endauth
    </div>
</nav>
