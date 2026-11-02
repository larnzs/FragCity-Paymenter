<?php

namespace Paymenter\Extensions\Others\FragCity;

use App\Classes\Extension\Extension;

class FragCity extends Extension
{
    /**
     * Return FragCity extension configuration fields.
     */
    public function getConfig($values = []): array
    {
        return [];
    }

    /**
     * Register FragCity functionality.
     */
    public function boot(): void
    {
        // FragCity services, routes and integrations
        // will be registered in subsequent development phases.
    }
}
