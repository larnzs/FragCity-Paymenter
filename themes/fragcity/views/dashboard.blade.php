<div class="container mt-14 fc-portal-page fc-dashboard-page" dir="rtl">
    <x-navigation.breadcrumb />

    <section class="fc-portal-hero fc-dashboard-hero">
        <div class="fc-portal-hero__copy">
            <span class="fc-portal-eyebrow"><span class="fc-portal-eyebrow__mark">FC</span> FRAGCITY / CUSTOMER CENTER</span>
            <h1>سلام {{ Auth::user()->first_name ?: Auth::user()->name }} <span aria-hidden="true">👋</span></h1>
            <p>{{ __('dashboard.dashboard_description') }}</p>
            <div class="fc-portal-hero__meta">
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> حساب کاربری امن</span>
                <span><i class="fa-solid fa-server" aria-hidden="true"></i> مدیریت سرویس‌ها</span>
            </div>
        </div>
        <div class="fc-dashboard-hero__mark" aria-hidden="true">
            <span>FRAGCITY</span>
            <strong>FC<span>.</span></strong>
            <small>GAME COMMUNITY / BILLING</small>
        </div>
    </section>

    <section class="fc-portal-stat-grid" aria-label="خلاصه حساب">
        <a href="{{ route('services') }}" wire:navigate class="fc-portal-stat">
            <span class="fc-portal-stat__icon"><x-ri-server-line class="size-5" /></span>
            <span class="fc-portal-stat__label">سرویس‌های من</span>
            <strong>{{ Auth::user()->services()->count() }}</strong>
            <small>مشاهده و مدیریت سرویس‌ها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></small>
        </a>
        <a href="{{ route('invoices') }}" wire:navigate class="fc-portal-stat">
            <span class="fc-portal-stat__icon"><x-ri-receipt-line class="size-5" /></span>
            <span class="fc-portal-stat__label">فاکتورهای پرداخت‌نشده</span>
            <strong>{{ Auth::user()->invoices()->where('status', 'pending')->count() }}</strong>
            <small>بررسی وضعیت پرداخت <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></small>
        </a>
        @if(!config('settings.tickets_disabled', false))
        <a href="{{ route('tickets') }}" wire:navigate class="fc-portal-stat">
            <span class="fc-portal-stat__icon"><x-ri-customer-service-line class="size-5" /></span>
            <span class="fc-portal-stat__label">تیکت‌های باز</span>
            <strong>{{ Auth::user()->tickets()->where('status', '!=', 'closed')->count() }}</strong>
            <small>پیگیری درخواست‌های پشتیبانی <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></small>
        </a>
        @endif
        <a href="{{ route('account.credits') }}" wire:navigate class="fc-portal-stat">
            <span class="fc-portal-stat__icon"><x-ri-wallet-3-line class="size-5" /></span>
            <span class="fc-portal-stat__label">کیف پول</span>
            <strong>{{ Auth::user()->credits->count() }}</strong>
            <small>مشاهده اعتبار حساب <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></small>
        </a>
    </section>

    <div class="fc-dashboard-grid">
        <div class="fc-dashboard-column">
            <section class="fc-portal-panel">
                <div class="fc-portal-panel__heading">
                    <div>
                        <span class="fc-portal-kicker">01 / SERVICES</span>
                        <h2><x-ri-server-line class="size-5" /> {{ __('dashboard.active_services') }}</h2>
                        <p>سرویس‌های فعال و جزئیات اشتراک خود را مدیریت کن.</p>
                    </div>
                    <span class="fc-portal-count">{{ Auth::user()->services()->where('status', 'active')->count() }}</span>
                </div>
                <div class="fc-portal-widget"><livewire:services.widget status="active" /></div>
                <x-navigation.link class="fc-portal-view-all" :href="route('services')">
                    {{ __('dashboard.view_all') }}
                    <x-ri-arrow-left-line class="size-5" />
                </x-navigation.link>
            </section>

            @if(!config('settings.tickets_disabled', false))
            <section class="fc-portal-panel">
                <div class="fc-portal-panel__heading">
                    <div>
                        <span class="fc-portal-kicker">02 / SUPPORT</span>
                        <h2><x-ri-customer-service-line class="size-5" /> {{ __('dashboard.open_tickets') }}</h2>
                        <p>آخرین پاسخ‌ها و درخواست‌های پشتیبانی را دنبال کن.</p>
                    </div>
                    <span class="fc-portal-count">{{ Auth::user()->tickets()->where('status', '!=', 'closed')->count() }}</span>
                </div>
                <div class="fc-portal-widget"><livewire:tickets.widget /></div>
                <x-navigation.link class="fc-portal-view-all" :href="route('tickets')">
                    {{ __('dashboard.view_all') }}
                    <x-ri-arrow-left-line class="size-5" />
                </x-navigation.link>
            </section>
            @endif
        </div>

        <div class="fc-dashboard-column">
            <section class="fc-portal-panel">
                <div class="fc-portal-panel__heading">
                    <div>
                        <span class="fc-portal-kicker">03 / BILLING</span>
                        <h2><x-ri-receipt-line class="size-5" /> {{ __('dashboard.unpaid_invoices') }}</h2>
                        <p>فاکتورهای در انتظار پرداخت و سوابق مالی.</p>
                    </div>
                    <span class="fc-portal-count">{{ Auth::user()->invoices()->where('status', 'pending')->count() }}</span>
                </div>
                <div class="fc-portal-widget"><livewire:invoices.widget :limit="3" /></div>
                <x-navigation.link class="fc-portal-view-all" :href="route('invoices')">
                    {{ __('dashboard.view_all') }}
                    <x-ri-arrow-left-line class="size-5" />
                </x-navigation.link>
            </section>

            <section class="fc-portal-panel fc-dashboard-shortcuts">
                <div class="fc-portal-panel__heading">
                    <div>
                        <span class="fc-portal-kicker">04 / ACCOUNT</span>
                        <h2><x-ri-settings-3-line class="size-5" /> تنظیمات حساب</h2>
                        <p>اطلاعات شخصی، امنیت و روش‌های پرداخت خود را به‌روز نگه دار.</p>
                    </div>
                </div>
                <div class="fc-dashboard-shortcuts__grid">
                    <a href="{{ route('account') }}" wire:navigate><x-ri-user-line class="size-5" /><span>اطلاعات شخصی</span><i class="fa-solid fa-arrow-left"></i></a>
                    <a href="{{ route('account.security') }}" wire:navigate><x-ri-shield-keyhole-line class="size-5" /><span>امنیت حساب</span><i class="fa-solid fa-arrow-left"></i></a>
                    <a href="{{ route('account.payment-methods') }}" wire:navigate><x-ri-bank-card-line class="size-5" /><span>روش‌های پرداخت</span><i class="fa-solid fa-arrow-left"></i></a>
                    <a href="{{ route('account.notifications') }}" wire:navigate><x-ri-notification-3-line class="size-5" /><span>اعلان‌ها</span><i class="fa-solid fa-arrow-left"></i></a>
                </div>
            </section>
            {!! hook('pages.dashboard') !!}
        </div>
    </div>
</div>
