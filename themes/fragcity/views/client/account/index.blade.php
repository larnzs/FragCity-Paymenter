<div class="container mt-14 fc-portal-page fc-account-page fc-account-details-page" dir="rtl">
    <x-navigation.breadcrumb />

    <div class="fc-portal-heading"><div><span class="fc-portal-kicker">FRAGCITY / ACCOUNT</span><h1>اطلاعات شخصی</h1><p>اطلاعات تماس و مشخصات حساب کاربری خود را به‌روز نگه دار.</p></div></div>
    <div class="px-2">

        <div class="grid md:grid-cols-2 gap-3">

            <x-form.input name="first_name" type="text" :label="__('general.input.first_name')"
                :placeholder="__('general.input.first_name_placeholder')" wire:model="first_name" required dirty />
            <x-form.input name="last_name" type="text" :label="__('general.input.last_name')"
                :placeholder="__('general.input.last_name_placeholder')" wire:model="last_name" required dirty />

            <x-form.input name="email" type="email" :label="__('general.input.email')"
                :placeholder="__('general.input.email_placeholder')" required wire:model="email" dirty />

            <x-form.properties :custom_properties="$custom_properties" :properties="$properties" dirty />
        </div>


        <x-button.primary wire:click="submit" class="w-full mt-4">
            {{ __('general.update') }}
        </x-button.primary>
    </div>
</div>