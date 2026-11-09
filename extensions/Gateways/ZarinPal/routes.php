<?php

use Illuminate\\Support\\Facades\\Route;
use Paymenter\\Extensions\\Gateways\\ZarinPal\\ZarinPal;

Route::get('/extensions/zarinpal/callback', [ZarinPal::class, 'callback'])
    ->name('extensions.gateways.zarinpal.callback');
