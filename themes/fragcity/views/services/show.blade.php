@php
    $isFragCityCs2 = ($service->product?->server?->extension ?? '') === 'FragCityCS2';
    $serviceStatusLabel = match ((string) $service->status) {
        'active' => 'فعال', 'pending' => 'در انتظار راه‌اندازی', 'cancelled' => 'لغو شده',
        'suspended' => 'معلق', 'failed' => 'ناموفق', default => 'در حال بررسی',
    };
    $period = (int) ($service->plan?->billing_period ?? 1);
    $billingCycle = match ((string) ($service->plan?->billing_unit ?? 'monthly')) {
        'day', 'daily' => 'روزانه', 'week', 'weekly' => 'هفتگی',
        'month', 'monthly' => 'ماهانه', 'year', 'yearly' => 'سالانه',
        'hour', 'hourly' => 'ساعتی', default => 'دوره‌ای',
    };
    $faNumber = static fn ($value): string => strtr((string) $value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
    $formatServiceDate = static function ($value) use ($faNumber): string {
        if (!$value) return '—';
        try { return $faNumber(\Illuminate\Support\Carbon::parse($value)->format('Y/m/d')); }
        catch (\Throwable) { return '—'; }
    };
@endphp

<div class="container mt-14 fc-portal-page fc-service-detail-page {{ $isFragCityCs2 ? 'fc-service-detail-page--cs2' : '' }}" dir="rtl">
    <x-navigation.breadcrumb />
    <header class="fc-service-hero">
        <div class="fc-service-hero__identity">
            <span class="fc-service-hero__icon"><i class="fa-solid {{ $isFragCityCs2 ? 'fa-crosshairs' : 'fa-server' }}" aria-hidden="true"></i></span>
            <div><span class="fc-portal-kicker">FRAGCITY / {{ $isFragCityCs2 ? 'CS2 SERVER CONTROL' : 'SERVICE MANAGEMENT' }}</span><h1>{{ $isFragCityCs2 ? 'مدیریت سرور CS2' : 'جزئیات سرویس' }}</h1><p>{{ $service->label ?: $service->product->name }} <span>•</span> شناسه سرویس <bdi dir="ltr">#{{ $faNumber($service->id) }}</bdi></p></div>
        </div>
        <div class="fc-service-hero__actions">
            <span class="fc-service-status fc-service-status--{{ $service->status === 'active' ? 'active' : ($service->status === 'cancelled' ? 'cancelled' : 'pending') }}"><span></span>{{ $service->cancellation && $service->status === 'active' ? 'در انتظار لغو' : $serviceStatusLabel }}</span>
            <a href="{{ route('services') }}" wire:navigate class="fc-service-back-link"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> بازگشت به سرورها</a>
        </div>
    </header>

    @if($invoice = $service->invoices()->where('status', 'pending')->first())
        <div class="fc-service-invoice-alert"><span><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><div><strong>برای این سرویس یک فاکتور پرداخت‌نشده وجود دارد.</strong><p>برای جلوگیری از اختلال در سرویس، وضعیت پرداخت را بررسی کن.</p></div><a href="{{ route('invoices.show', $invoice) }}" wire:navigate>مشاهده و پرداخت فاکتور <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a></div>
    @endif

    <div class="fc-service-overview-grid">
        <section class="fc-service-overview-card">
            <div class="fc-service-section-heading"><span><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span><div><h2>اطلاعات اشتراک</h2><p>مشخصات خرید و وضعیت سرویس شما</p></div></div>
            <div class="fc-service-facts">
                <div><span>محصول</span><strong>{{ $isFragCityCs2 ? 'سرور اختصاصی Counter-Strike 2' : $service->product->name }}</strong></div>
                <div><span>پلن خریداری‌شده</span><strong>{{ $service->plan?->name ?: 'پلن اختصاصی' }}</strong></div>
                <div><span>مبلغ اشتراک</span><strong class="fc-service-price">{{ $faNumber($service->formattedPrice) }}</strong></div>
                @if($service->plan?->type === 'recurring')
                    <div><span>دوره پرداخت</span><strong>{{ $period > 1 ? $faNumber($period) . ' ' : '' }}{{ $billingCycle }}</strong></div>
                @endif
                <div><span>تاریخ ثبت سرویس</span><strong>{{ $formatServiceDate($service->created_at) }}</strong></div>
                @if($service->expires_at)<div><span>تاریخ پایان اشتراک</span><strong>{{ $formatServiceDate($service->expires_at) }}</strong></div>@endif
                @if($service->plan?->type === 'recurring')<div><span>تمدید خودکار</span><strong>{{ $service->billing_agreement_id ? 'فعال' : 'غیرفعال' }}</strong></div>@endif
            </div>
            @include('services.partials.billing-agreement')
            @if(count($fields) > 0)
                <div class="fc-service-extra-fields"><h3>تنظیمات اختصاصی سرویس</h3>@foreach ($fields as $field)<div><span>{{ $field['label'] }}</span><strong>{{ $field['text'] }}</strong></div>@endforeach</div>
            @endif
        </section>

        @if($service->cancellable || $service->upgradable || count($buttons) > 0)
            <section class="fc-service-actions-card">
                <div class="fc-service-section-heading"><span><i class="fa-solid fa-bolt" aria-hidden="true"></i></span><div><h2>عملیات سرویس</h2><p>اقدام‌های قابل انجام برای این اشتراک</p></div></div>
                <div class="fc-service-actions-list">
                    @if($service->upgradable)
                        <a href="{{ route('services.upgrade', $service->id) }}" class="fc-service-action fc-service-action--primary"><i class="fa-solid fa-arrow-up-right-dots" aria-hidden="true"></i><span><strong>ارتقای سرور</strong><small>افزایش منابع یا تغییر تنظیمات پلن</small></span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                    @endif
                    @if($service->upgrade()->where('status', 'pending')->exists())
                        <div class="fc-service-action fc-service-action--muted"><i class="fa-solid fa-clock" aria-hidden="true"></i><span><strong>درخواست ارتقا در حال بررسی است</strong><small>پس از تکمیل درخواست، امکان ارتقای دوباره فعال می‌شود.</small></span></div>
                    @endif
                    @if($service->cancellable)
                        <button type="button" class="fc-service-action fc-service-action--danger" wire:click="$set('showCancel', true)"><i class="fa-solid fa-ban" aria-hidden="true"></i><span><strong>درخواست لغو سرویس</strong><small>لغو اشتراک و توقف تمدید آن</small></span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    @endif
                    @foreach ($buttons as $button)
                        @if (isset($button['function']))
                            <button type="button" class="fc-service-action" wire:click="goto('{{ $button['function'] }}')"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span><strong>{{ $button['label'] }}</strong></span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                        @else
                            <a href="{{ $button['url'] }}" @if(!empty($button['target'])) target="{{ $button['target'] }}" @endif @if(($button['target'] ?? null) === '_blank') rel="noopener noreferrer" @endif class="fc-service-action"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span><strong>{{ $button['label'] }}</strong></span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @if ($showCancel)
        <x-modal open="true" title="درخواست لغو {{ $service->product->name }}" width="max-w-3xl">
            <livewire:services.cancel :service="$service" />
            <x-slot name="closeTrigger"><div class="flex gap-4"><button wire:click="$set('showCancel', false)" @click="open = false" class="text-primary-100" aria-label="بستن"><x-ri-close-fill class="size-6" /></button></div></x-slot>
        </x-modal>
    @endif

    @if (count($views) > 0)
        <section class="fc-service-extension-workspace">
            <div class="fc-service-section-heading"><span><i class="fa-solid {{ $isFragCityCs2 ? 'fa-crosshairs' : 'fa-puzzle-piece' }}" aria-hidden="true"></i></span><div><h2>{{ $isFragCityCs2 ? 'داشبورد سرور بازی' : 'ابزارهای سرویس' }}</h2><p>وضعیت و اطلاعات زنده‌ی سرویس در این بخش نمایش داده می‌شود.</p></div></div>
            @if (count($views) > 1)
                <nav class="fc-service-tabs" aria-label="بخش‌های مدیریت سرویس">@foreach ($views as $view)<button type="button" wire:click="changeView('{{ $view['name'] }}')" class="{{ $view['name'] === $currentView ? 'is-active' : '' }}">{{ $view['label'] }}</button>@endforeach</nav>
            @endif
            <x-loading target="changeView" />
            <div wire:loading.remove wire:target="changeView" class="fc-service-extension-content">{!! $extensionView !!}</div>
        </section>
    @endif
</div>