@php
    $fragcityMain = 'https://fragcity.ir';
    $fragcityFallbackAvatar = $fragcityMain . '/img/logoresponsive.png';
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
            <button type="button" class="fc-motion-toggle" aria-label="Performance Mode"
                    data-motion-toggle aria-pressed="false" title="حالت بدون انیمیشن">
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span class="fc-motion-toggle__label">Performance Mode</span>
            </button>

            @auth
                <div class="frag-user-area">
                    <div class="frag-dropdown" id="shockUserDropdown">
                        <button type="button" class="frag-user-trigger" id="shockUserButton"
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
                                    <span class="frag-drop-steam">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                            <div class="frag-drop-divider"></div>
                            <div class="frag-drop-actions">
                                @foreach (\App\Classes\Navigation::getAccountDropdownLinks() as $nav)
                                    <a href="{{ $nav['url'] }}" class="frag-drop-link"
                                       @if(isset($nav['spa']) && $nav['spa']) wire:navigate @endif>
                                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                        <span>{{ $nav['name'] }}</span>
                                    </a>
                                @endforeach
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
                    <a href="{{ route('login') }}" class="frag-login-btn">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>ورود</span>
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
                aria-label="Performance Mode" data-motion-toggle aria-pressed="false">
            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
            <span class="fc-motion-toggle__label">Performance Mode</span>
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
            @foreach (\App\Classes\Navigation::getAccountDropdownLinks() as $nav)
                <a href="{{ $nav['url'] }}" class="mobile-nav-link"
                   @if(isset($nav['spa']) && $nav['spa']) wire:navigate @endif>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>{{ $nav['name'] }}</span>
                </a>
            @endforeach
            <div class="mobile-nav-logout"><livewire:auth.logout /></div>
        @else
            <a href="{{ route('login') }}" class="mobile-nav-link"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>ورود به حساب</span></a>
            @if (!config('settings.registration_disabled', false))
                <a href="{{ route('register') }}" class="mobile-nav-link"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>ساخت حساب کاربری</span></a>
            @endif
        @endauth
    </div>
</nav>
