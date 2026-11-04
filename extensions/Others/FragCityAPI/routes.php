<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\FragCityAPI\FragCityAPI;

Route::post('/api/v1/fragcity/bridge/ping', [FragCityAPI::class, 'ping'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.ping');

Route::post('/api/v1/fragcity/bridge/users/sync', [FragCityAPI::class, 'syncUser'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.users.sync');

Route::post('/api/v1/fragcity/bridge/servers/list', [FragCityAPI::class, 'myServers'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.list');

Route::post('/api/v1/fragcity/bridge/servers/password', [FragCityAPI::class, 'setPanelPassword'])
    ->middleware('throttle:6,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.password');
