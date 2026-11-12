<div class="container mt-14 fc-portal-page fc-services-page" dir="rtl">
    <x-navigation.breadcrumb />
    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / سرویس‌های من</span>
            <h1>سرویس‌های من</h1>
            <p>وضعیت، منابع و جزئیات سرویس‌های خریداری‌شده را از این بخش مدیریت کن.</p>
        </div>
        <a href="{{ route('home') }}" wire:navigate class="fc-portal-heading__action"><i class="fa-solid fa-plus"></i> مشاهده فروشگاه</a>
    </div>

    <div class="fc-portal-list">
        @forelse ($services as $service)
        <a href="{{ route('services.show', $service) }}" wire:navigate class="fc-portal-list-card fc-service-card">
            <span class="fc-portal-list-card__icon"><x-ri-instance-line class="size-6" /></span>
            <span class="fc-portal-list-card__body">
                <strong>{{ $service->label }}</strong>
                @php
                    $periodUnit = match ($service->plan->billing_unit) {
                        'day' => 'روز',
                        'week' => 'هفته',
                        'month' => 'ماه',
                        'year' => 'سال',
                        default => 'دوره',
                    };
                    $periodCount = (int) $service->plan->billing_period;
                    $periodLabel = $periodCount > 1 ? 'هر ' . $periodCount . ' ' . $periodUnit : 'هر ' . $periodUnit;
                    $serviceStatusLabel = match ((string) $service->status) {
                        'active' => 'فعال',
                        'pending' => 'در انتظار',
                        'suspended' => 'معلق',
                        'cancelled' => 'لغو شده',
                        'expired' => 'منقضی شده',
                        default => 'نامشخص',
                    };
                @endphp
                <small>{{ $service->product->name }} <span>·</span>
                    {{ $service->plan->type === 'recurring' ? $periodLabel : 'اشتراک یک‌باره' }}
                </small>
                @if($service->expires_at)
                    <small><i class="fa-regular fa-calendar" aria-hidden="true"></i> تمدید در تاریخ {{ $service->expires_at->format('Y/m/d') }}</small>
                @endif
            </span>
            <span class="fc-portal-status fc-portal-status--{{ $service->status }}">{{ $serviceStatusLabel }}</span>
            <span class="fc-portal-list-card__arrow"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
        @empty
        <div class="fc-portal-empty">
            <span><x-ri-instance-line class="size-8" /></span>
            <strong>هنوز سرویسی برای حساب شما ثبت نشده است.</strong>
            <p>بعد از ثبت سفارش، سرویس‌های تو در همین بخش نمایش داده می‌شوند.</p>
            <a href="{{ route('home') }}" wire:navigate class="fc-portal-button">رفتن به فروشگاه</a>
        </div>
        @endforelse
    </div>
    <div class="fc-portal-pagination">{{ $services->links() }}</div>
</div>
