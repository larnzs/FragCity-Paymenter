@php
    $faDigits = static fn ($value): string => strtr((string) $value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
    $formatMegabytes = static function ($value) use ($faDigits): string {
        $value = (int) $value;
        if ($value <= 0) return 'نامحدود';
        if ($value >= 1024) return $faDigits(rtrim(rtrim(number_format($value / 1024, 1, '.', ''), '0'), '.')) . ' گیگابایت';
        return $faDigits(number_format($value)) . ' مگابایت';
    };
    $formatDate = static function ($value) use ($faDigits): string {
        if (!$value) return '—';
        try { return $faDigits(\Illuminate\Support\Carbon::parse($value)->format('Y/m/d H:i')); }
        catch (\Throwable) { return '—'; }
    };
    $state = (string) ($summary['state'] ?? 'unavailable');
    $server = is_array($summary['server'] ?? null) ? $summary['server'] : [];
    $panel = is_array($summary['panel'] ?? null) ? $summary['panel'] : [];
    $limits = is_array($server['limits'] ?? null) ? $server['limits'] : [];
    $features = is_array($server['feature_limits'] ?? null) ? $server['feature_limits'] : [];
    $serverStatus = strtolower((string) ($server['server_status'] ?? 'unknown'));
    $serverStatusLabel = match ($serverStatus) {
        'running' => 'آنلاین',
        'offline' => 'آفلاین',
        'starting' => 'در حال روشن شدن',
        'stopping' => 'در حال خاموش شدن',
        'installing' => 'در حال نصب',
        'restoring_backup' => 'در حال بازیابی پشتیبان',
        'transferring' => 'در حال انتقال',
        default => 'نامشخص',
    };
    $addressHost = trim((string) ($server['allocation_alias'] ?? ''));
    if ($addressHost === '') $addressHost = trim((string) ($server['allocation_ip'] ?? ''));
    $addressPort = (int) ($server['allocation_port'] ?? 0);
    $connectAddress = $addressHost !== '' && $addressPort > 0 ? $addressHost . ':' . $addressPort : '';
    $memory = (int) ($limits['memory'] ?? 0);
    $disk = (int) ($limits['disk'] ?? 0);
    $cpu = (int) ($limits['cpu'] ?? 0);
@endphp

<section class="fc-cs2-dashboard" dir="rtl">
    @if ($dashboardError)
        <div class="fc-cs2-alert fc-cs2-alert--warning">
            <span class="fc-cs2-alert__icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <div><strong>اطلاعات سرور در دسترس نیست</strong><p>اتصال به پنل مدیریت موقتاً برقرار نشد. اطلاعات حساب و سرویس شما محفوظ است؛ کمی بعد دوباره این صفحه را بررسی کن.</p></div>
        </div>
    @elseif ($state === 'provisioning')
        <div class="fc-cs2-provisioning">
            <span class="fc-cs2-provisioning__icon"><i class="fa-solid fa-gears" aria-hidden="true"></i></span>
            <div><span class="fc-cs2-eyebrow">FRAGCITY / CS2 DEPLOYMENT</span><h2>سرورت در حال آماده‌سازی است</h2><p>سفارش ثبت شده و سرویس در مرحله‌ی ساخت یا اتصال به پنل قرار دارد. پس از آماده‌شدن سرور، مشخصات اتصال و منابع اینجا نمایش داده می‌شود.</p></div>
            <span class="fc-cs2-status fc-cs2-status--pending"><span class="fc-cs2-status__dot"></span>در حال راه‌اندازی</span>
        </div>
    @elseif ($state === 'ready' && $server !== [])
        <div class="fc-cs2-topline">
            <div class="fc-cs2-server-identity">
                <span class="fc-cs2-game-mark"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i></span>
                <div><span class="fc-cs2-eyebrow">COUNTER-STRIKE 2 / GAME SERVER</span><h2>{{ $server['name'] ?: ($service->label ?: 'سرور CS2') }}</h2><p>شناسه سرویس <bdi dir="ltr">#{{ $faDigits($service->id) }}</bdi> <span>•</span> {{ $summary['product_name'] ?? 'سرور اختصاصی CS2' }}</p></div>
            </div>
            @php
                $statusClass = !empty($server['suspended']) ? 'fc-cs2-status--suspended' : ($serverStatus === 'running' ? 'fc-cs2-status--online' : ($serverStatus === 'offline' ? 'fc-cs2-status--offline' : 'fc-cs2-status--pending'));
            @endphp
            <span class="fc-cs2-status {{ $statusClass }}"><span class="fc-cs2-status__dot"></span>{{ !empty($server['suspended']) ? 'متوقف شده' : $serverStatusLabel }}</span>
        </div>
        @if ($connectAddress !== '')
            <div class="fc-cs2-connect-card"><div><span class="fc-cs2-eyebrow">SERVER CONNECTION</span><strong>آدرس اتصال به سرور</strong><p>این آدرس را در کنسول بازی یا بخش اتصال مستقیم وارد کن.</p></div><code dir="ltr">{{ $connectAddress }}</code></div>
        @endif

        <div class="fc-cs2-resource-grid">
            <article class="fc-cs2-resource-card"><span class="fc-cs2-resource-card__icon"><i class="fa-solid fa-memory" aria-hidden="true"></i></span><div><small>حافظه RAM</small><strong>{{ $formatMegabytes($memory) }}</strong><span>محدودیت حافظه</span></div></article>
            <article class="fc-cs2-resource-card"><span class="fc-cs2-resource-card__icon"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span><div><small>پردازنده CPU</small><strong>{{ $cpu > 0 ? $faDigits($cpu) . '٪' : 'نامحدود' }}</strong><span>سهم پردازنده</span></div></article>
            <article class="fc-cs2-resource-card"><span class="fc-cs2-resource-card__icon"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i></span><div><small>فضای ذخیره‌سازی</small><strong>{{ $formatMegabytes($disk) }}</strong><span>فضای دیسک اختصاص‌یافته</span></div></article>
        </div>

        <div class="fc-cs2-details-grid">
            <section class="fc-cs2-panel">
                <div class="fc-cs2-panel__heading"><span><i class="fa-solid fa-sliders" aria-hidden="true"></i></span><div><h3>مشخصات سرور</h3><p>اطلاعات فنی و محل میزبانی</p></div></div>
                <dl class="fc-cs2-detail-list">
                    <div><dt>نام سرور</dt><dd>{{ $server['name'] ?: 'سرور CS2' }}</dd></div>
                    <div><dt>نود میزبانی</dt><dd>{{ $server['node_name'] ?: 'ثبت نشده' }}</dd></div>
                    <div><dt>موقعیت</dt><dd>{{ $server['location_long'] ?: ($server['location_short'] ?: 'ثبت نشده') }}</dd></div>
                    <div><dt>بازی</dt><dd>Counter-Strike 2</dd></div>
                    <div><dt>وضعیت نصب بازی</dt><dd>{{ ($server['installed'] ?? null) === true ? 'نصب و آماده' : ((($server['installed'] ?? null) === false) ? 'در انتظار نصب' : 'نامشخص') }}</dd></div>
                    <div><dt>آخرین بروزرسانی اطلاعات</dt><dd>{{ $formatDate($server['updated_at'] ?? null) }}</dd></div>
                </dl>
            </section>
            <section class="fc-cs2-panel">
                <div class="fc-cs2-panel__heading"><span><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><div><h3>امکانات اختصاص‌یافته</h3><p>محدودیت‌ها و قابلیت‌های این سرور</p></div></div>
                <div class="fc-cs2-feature-grid">
                    <div><strong>{{ $faDigits($features['databases'] ?? 0) }}</strong><span>پایگاه داده</span></div>
                    <div><strong>{{ $faDigits($features['backups'] ?? 0) }}</strong><span>نسخه پشتیبان</span></div>
                    <div><strong>{{ $faDigits($features['allocations'] ?? 0) }}</strong><span>پورت و اتصال</span></div>
                </div>
                @if (!empty($server['node_maintenance_mode']))<div class="fc-cs2-inline-notice"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> نود میزبان در حالت تعمیر و نگهداری قرار دارد.</div>@endif
            </section>
        </div>

        <section class="fc-cs2-panel fc-cs2-access-panel">
            <div class="fc-cs2-panel__heading"><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><div><h3>دسترسی به پنل مدیریت</h3><p>اطلاعات ورود به پنل Pterodactyl</p></div></div>
            <div class="fc-cs2-access-content">
                <div class="fc-cs2-account-info">
                    <div><small>نام کاربری پنل</small><strong dir="ltr">{{ $panel['username'] ?? '—' }}</strong></div>
                    <div><small>ایمیل پنل</small><strong dir="ltr">{{ $panel['email'] ?? '—' }}</strong></div>
                    <div><small>وضعیت رمز پنل</small><strong>{{ !empty($panel['password_set']) ? 'رمز اختصاصی تنظیم شده' : 'رمز پیش‌فرض پنل' }}</strong></div>
                </div>
                @if (!empty($panel['server_url']))<a class="fc-cs2-panel-link" href="{{ $panel['server_url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> ورود به پنل مدیریت</a>@endif
            </div>
        </section>

        <details class="fc-cs2-technical">
            <summary><i class="fa-solid fa-code" aria-hidden="true"></i> اطلاعات فنی پیشرفته</summary>
            <dl class="fc-cs2-detail-list">
                <div><dt>شناسه سرور</dt><dd dir="ltr">{{ $server['id'] ?? '—' }}</dd></div>
                <div><dt>شناسه یکتا</dt><dd dir="ltr">{{ $server['identifier'] ?? '—' }}</dd></div>
                <div><dt>شناسه سرویس Paymenter</dt><dd dir="ltr">{{ $service->id }}</dd></div>
                <div><dt>شناسه Egg</dt><dd dir="ltr">{{ $server['egg_id'] ?? '—' }}</dd></div>
                <div><dt>Docker Image</dt><dd dir="ltr">{{ $server['docker_image'] ?? '—' }}</dd></div>
            </dl>
        </details>
    @else
        <div class="fc-cs2-alert"><span class="fc-cs2-alert__icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span><div><strong>هنوز اطلاعاتی برای نمایش وجود ندارد</strong><p>اطلاعات سرور پس از تکمیل فرآیند ساخت نمایش داده می‌شود.</p></div></div>
    @endif
    <div class="fc-cs2-footer-note"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> اطلاعات منابع و وضعیت سرور مستقیماً از پنل مدیریت خوانده می‌شود.</div>
</section>
