<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\FragCitySupportAPI\FragCitySupportAPI;

Route::post('/api/v1/fragcity/support/ping', [FragCitySupportAPI::class, 'ping'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.ping');

Route::post('/api/v1/fragcity/support/identity/resolve', [FragCitySupportAPI::class, 'resolveIdentity'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.identity.resolve');
