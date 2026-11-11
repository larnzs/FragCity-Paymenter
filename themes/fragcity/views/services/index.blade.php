<div class="container mt-14 fc-portal-page fc-services-page" dir="rtl">
    <x-navigation.breadcrumb />
    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / MY SERVICES</span>
            <h1>{{ __('services.services') }}</h1>
            <p>وضعیت، منابع و جزئیات سرویس‌های خریداری‌شده را از این بخش مدیریت کن.</p>
        </div>
        <a href="{{ route('home') }}" wire:navigate class="fc-portal-heading__action"><i class="fa-solid fa-plus"></i> مشاهده فروشگاه</a>
    </div>

    <div class="fc-portal-list">
        @forelse ($services as $service)
        <a href="{{ route('services.show', $service) }}" wire:navigate class="fc-portal-list-card fc-service-card">
            <span class="fc-portal-list-card__icon"><x-ri-server-line class="size-6" /></span>
            <span class="fc-portal-list-card__body">
                <strong>{{ $service->label }}</strong>
                <small>{{ $service->product->name }} <span>·</span>
                    {{ in_array($service->plan->type, ['recurring']) ? __('services.every_period', [
                        'period' => $service->plan->billing_period > 1 ? $service->plan->billing_period : '',
                        'unit' => trans_choice(__('services.billing_cycles.' . $service->plan->billing_unit), $service->plan->billing_period)
                    ]) : 'اشتراک یک‌باره' }}
                </small>
                @if($service->expires_at)
                    <small><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ __('services.renews_in') }} {{ $service->expires_at->format('Y-m-d') }}</small>
                @endif
            </span>
            <span class="fc-portal-status fc-portal-status--{{ $service->status }}">{{ __('services.statuses.' . $service->status) }}</span>
            <span class="fc-portal-list-card__arrow"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
        @empty
        <div class="fc-portal-empty">
            <span><x-ri-server-line class="size-8" /></span>
            <strong>{{ __('services.no_services') }}</strong>
            <p>بعد از ثبت سفارش، سرویس‌های تو در همین بخش نمایش داده می‌شوند.</p>
            <a href="{{ route('home') }}" wire:navigate class="fc-portal-button">رفتن به فروشگاه</a>
        </div>
        @endforelse
    </div>
    <div class="fc-portal-pagination">{{ $services->links() }}</div>
</div>
