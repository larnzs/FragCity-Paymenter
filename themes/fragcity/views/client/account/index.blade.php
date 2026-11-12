<div class="container fc-portal-page fc-account-page fc-account-details-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading">
        <div>
            <span class="fc-portal-kicker">FRAGCITY / اطلاعات حساب</span>
            <h1>اطلاعات حساب کاربری</h1>
            <p>مشخصات و راه‌های ارتباطی حساب خود را مدیریت کن.</p>
        </div>
    </div>

    <div class="fc-account-content">
        <section class="fc-account-card p-5">
            <div class="fc-account-card__heading">
                <span><i class="fa-solid fa-user-gear" aria-hidden="true"></i></span>
                <div><h2>مشخصات شخصی</h2><p>این اطلاعات برای فاکتورها و ارتباط با پشتیبانی استفاده می‌شود.</p></div>
            </div>

            <div class="fc-account-layout-fields">
                <x-form.input name="first_name" type="text" label="نام"
                    placeholder="نام خود را وارد کن" wire:model="first_name" required dirty />
                <x-form.input name="last_name" type="text" label="نام خانوادگی"
                    placeholder="نام خانوادگی خود را وارد کن" wire:model="last_name" required dirty />
                <x-form.input name="email" type="email" label="آدرس ایمیل"
                    placeholder="name@example.com" required wire:model="email" dirty />
                <x-form.properties :custom_properties="$custom_properties" :properties="$properties" dirty />
            </div>

            <x-button.primary wire:click="submit" class="w-full mt-5">
                <i class="fa-solid fa-floppy-disk ml-2" aria-hidden="true"></i>
                ذخیره تغییرات
            </x-button.primary>
        </section>

        <aside class="fc-account-help-card">
            <h3><i class="fa-solid fa-shield-halved ml-2" aria-hidden="true"></i> نکته امنیتی</h3>
            <p>برای حفظ دسترسی به سرویس‌ها، ایمیلی وارد کن که همیشه به آن دسترسی داری. تغییر ایمیل ممکن است نیازمند تأیید مجدد باشد.</p>
            <a href="{{ route('account.security') }}" wire:navigate class="inline-flex items-center gap-2 mt-4 text-sm font-bold text-orange-300 hover:text-orange-200">
                مدیریت امنیت حساب <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
        </aside>
    </div>
</div>