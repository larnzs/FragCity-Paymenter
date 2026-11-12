<div class="container fc-portal-page fc-account-page fc-account-notifications-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading">
        <div><span class="fc-portal-kicker">FRAGCITY / NOTIFICATIONS</span><h1>اعلان‌ها</h1><p>مشخص کن پیام‌های مهم حساب از چه طریقی به تو اطلاع داده شوند.</p></div>
    </div>

    <div class="fc-account-content fc-notifications-grid">
        <section class="fc-notifications-card" x-data="pushNotifications">
            <div class="fc-account-card__heading">
                <span><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
                <div><h2>اعلان‌های فوری مرورگر</h2><p>برای دریافت هشدارهای مهم، اعلان مرورگر را فعال کن.</p></div>
            </div>

            @if($this->supportsPush())
                <x-button.primary type="button" class="w-full" @click="subscribe"
                    x-bind:disabled="subscriptionStatus !== 'not_subscribed'">
                    <i class="fa-solid fa-bell ml-2" aria-hidden="true"></i>
                    فعال‌سازی اعلان مرورگر
                </x-button.primary>
                <div class="mt-3 text-xs leading-6" x-show="subscriptionStatus !== 'unknown'">
                    <template x-if="subscriptionStatus === 'not_supported'">
                        <p class="text-red-300">مرورگر شما از اعلان فوری پشتیبانی نمی‌کند.</p>
                    </template>
                    <template x-if="subscriptionStatus === 'denied'">
                        <p class="text-red-300">دسترسی اعلان‌ها در تنظیمات مرورگر مسدود شده است.</p>
                    </template>
                    <template x-if="subscriptionStatus === 'subscribed'">
                        <p class="text-green-300"><i class="fa-solid fa-circle-check ml-1"></i> اعلان مرورگر فعال است.</p>
                    </template>
                    <template x-if="subscriptionStatus === 'not_subscribed'">
                        <p class="text-gray-400">هنوز اعلان مرورگر را فعال نکرده‌ای.</p>
                    </template>
                </div>
            @else
                <p class="text-sm leading-7 text-gray-400">اعلان مرورگر برای این حساب در دسترس نیست.</p>
            @endif
        </section>

        @script
        <script>
            Alpine.data('pushNotifications', () => ({
                subscriptionStatus: 'unknown',

                init() {
                    if ('serviceWorker' in navigator && 'PushManager' in window) {
                        navigator.serviceWorker.ready.then((registration) => {
                            registration.pushManager.getSubscription().then((subscription) => {
                                if (subscription) {
                                    this.subscriptionStatus = 'subscribed';
                                } else {
                                    this.subscriptionStatus = Notification.permission === 'denied' ? 'denied' : 'not_subscribed';
                                }
                            });
                        }).catch(() => {
                            this.subscriptionStatus = 'not_supported';
                        });
                    } else {
                        this.subscriptionStatus = 'not_supported';
                    }
                },

                subscribe() {
                    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                        this.subscriptionStatus = 'not_supported';
                        return;
                    }

                    navigator.serviceWorker.ready.then((registration) => {
                        registration.pushManager.getSubscription().then((subscription) => {
                            if (subscription) {
                                @this.call('storePushSubscription', JSON.stringify(subscription));
                                this.subscriptionStatus = 'subscribed';
                                return;
                            }

                            registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array('{{ config('settings.vapid_public_key') }}')
                            }).then((newSubscription) => {
                                @this.call('storePushSubscription', JSON.stringify(newSubscription));
                                this.subscriptionStatus = 'subscribed';
                            }).catch(() => {
                                this.subscriptionStatus = Notification.permission === 'denied' ? 'denied' : 'not_subscribed';
                            });
                        });
                    }).catch(() => {
                        this.subscriptionStatus = 'not_supported';
                    });
                }
            }));

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
                return outputArray;
            }
        </script>
        @endscript

        <section class="fc-notifications-card">
            <div class="fc-account-card__heading">
                <span><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
                <div><h2>تنظیمات دریافت اعلان</h2><p>کانال دریافت هر نوع پیام را جداگانه روشن یا خاموش کن.</p></div>
            </div>

            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th class="text-right">نوع اعلان</th>
                            <th class="text-center"><i class="fa-solid fa-envelope ml-1" aria-hidden="true"></i> ایمیل</th>
                            <th class="text-center"><i class="fa-solid fa-bell ml-1" aria-hidden="true"></i> داخل پنل</th>
                        </tr>
                    </thead>
                    <tbody x-data="{ preferences: $wire.entangle('preferences') }">
                        @foreach($this->notifications as $notification)
                            @php
                                $notificationLabels = [
                                    'invoice_paid' => 'پرداخت فاکتور',
                                    'invoice_created' => 'صدور فاکتور جدید',
                                    'invoice_reminder' => 'یادآوری پرداخت',
                                    'service_created' => 'ایجاد سرویس جدید',
                                    'service_updated' => 'تغییر وضعیت سرویس',
                                    'service_suspended' => 'تعلیق سرویس',
                                    'service_expiring' => 'نزدیک‌شدن زمان تمدید سرویس',
                                    'ticket_created' => 'ثبت تیکت جدید',
                                    'ticket_reply' => 'پاسخ جدید به تیکت',
                                    'ticket_updated' => 'به‌روزرسانی تیکت',
                                    'credit_added' => 'افزایش موجودی کیف پول',
                                ];
                                $notificationLabel = $notificationLabels[$notification->key] ?? $notification->name;
                                if (preg_match('/[A-Za-z]/', (string) $notificationLabel)) {
                                    $notificationLabel = 'اعلان مربوط به حساب کاربری';
                                }
                            @endphp
                            <tr>
                                <td>{{ $notificationLabel }}</td>
                                <td><div class="flex justify-center"><x-form.toggle :disabled="!$notification->mail_controllable" wire:model.defer="preferences.{{ $notification->key }}.mail_enabled" /></div></td>
                                <td><div class="flex justify-center"><x-form.toggle :disabled="!$notification->in_app_controllable" wire:model.defer="preferences.{{ $notification->key }}.in_app_enabled" /></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-button.primary wire:click="savePreferences" class="w-full mt-4" wire:loading.attr="disabled">
                <x-loading wire:loading wire:target="savePreferences" />
                <span wire:loading.remove wire:target="savePreferences">
                    <i class="fa-solid fa-floppy-disk ml-2" aria-hidden="true"></i> ذخیره تنظیمات
                </span>
            </x-button.primary>
        </section>
    </div>
</div>