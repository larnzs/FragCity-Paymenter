<?php

namespace Paymenter\Extensions\Others\FragCity;

use App\Classes\Extension\Extension;

class FragCity extends Extension
{
    public function getConfig($values = []): array
    {
        return [];
    }

    /**
     * Register Persian locale metadata at runtime. No Paymenter core files are modified.
     */
    public function boot(): void
    {
        $locales = config('app.available_locales', []);
        $locales['fa'] = 'فارسی';

        $rtl = config('app.rtl_locales', []);
        $rtl[] = 'fa';

        config([
            'app.available_locales' => $locales,
            'app.rtl_locales' => array_values(array_unique($rtl)),
        ]);
    }
}
