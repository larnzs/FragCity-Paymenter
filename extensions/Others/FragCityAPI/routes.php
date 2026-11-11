<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\FragCityAPI\FragCityAPI;

Route::post('/api/v1/fragcity/bridge/ping', [FragCityAPI::class, 'ping'])
    ->middleware('throttle:180,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.ping');

Route::post('/api/v1/fragcity/bridge/users/sync', [FragCityAPI::class, 'syncUser'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.users.sync');


Route::post('/api/v1/fragcity/bridge/catalog/cs2', [FragCityAPI::class, 'cs2Catalog'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.catalog.cs2');

Route::post('/api/v1/fragcity/bridge/wallet/balance', [FragCityAPI::class, 'walletBalance'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.wallet.balance');

Route::post('/api/v1/fragcity/bridge/wallet/topups/zarinpal', [FragCityAPI::class, 'createWalletTopup'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.wallet.topups.zarinpal');

Route::post('/api/v1/fragcity/bridge/wallet/topups/prepare', [FragCityAPI::class, 'prepareWalletTopup'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.wallet.topups.prepare');

Route::post('/api/v1/fragcity/bridge/quotes/cs2', [FragCityAPI::class, 'quoteCs2Order'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.quotes.cs2');

Route::post('/api/v1/fragcity/bridge/orders/cs2', [FragCityAPI::class, 'createCs2Order'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.orders.cs2');

Route::post('/api/v1/fragcity/bridge/servers/list', [FragCityAPI::class, 'myServers'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.list');

Route::post('/api/v1/fragcity/bridge/servers/password', [FragCityAPI::class, 'setPanelPassword'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.password');

Route::post('/api/v1/fragcity/bridge/servers/username', [FragCityAPI::class, 'setPanelUsername'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.username');

Route::post('/api/v1/fragcity/bridge/catalog/vip', [FragCityAPI::class, 'vipCatalog'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.catalog.vip');

Route::post('/api/v1/fragcity/bridge/orders/vip', [FragCityAPI::class, 'createVipOrder'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.orders.vip');

Route::post('/api/v1/fragcity/bridge/orders/vip/list', [FragCityAPI::class, 'listVipOrders'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.orders.vip.list');

Route::post('/api/v1/fragcity/bridge/orders/vip/revoke', [FragCityAPI::class, 'revokeVipMembership'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.orders.vip.revoke');

Route::get('/auth/fragcity/sso/start', [FragCityAPI::class, 'startSso'])
    ->middleware('web')
    ->name('extensions.fragcity-api.sso.start');

Route::post('/auth/fragcity/sso/callback', [FragCityAPI::class, 'completeSso'])
    ->middleware(['web', 'throttle:20,1'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.sso.callback');
