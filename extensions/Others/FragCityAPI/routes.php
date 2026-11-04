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

Route::post('/api/v1/fragcity/bridge/servers/client-api/connect', [FragCityAPI::class, 'connectClientApi'])
    ->middleware('throttle:10,15')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.client-api.connect');

Route::post('/api/v1/fragcity/bridge/servers/client-api/disconnect', [FragCityAPI::class, 'disconnectClientApi'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.client-api.disconnect');

Route::post('/api/v1/fragcity/bridge/servers/console', [FragCityAPI::class, 'customerConsole'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.console');

Route::post('/api/v1/fragcity/bridge/servers/files', [FragCityAPI::class, 'customerFiles'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.servers.files');

Route::post('/api/v1/fragcity/bridge/admin/lifecycle/list', [FragCityAPI::class, 'adminLifecycleList'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.admin.lifecycle.list');

Route::post('/api/v1/fragcity/bridge/admin/lifecycle/detail', [FragCityAPI::class, 'adminLifecycleDetail'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.admin.lifecycle.detail');

Route::post('/api/v1/fragcity/bridge/admin/lifecycle/reconcile', [FragCityAPI::class, 'adminLifecycleReconcile'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.admin.lifecycle.reconcile');

Route::post('/api/v1/fragcity/bridge/admin/lifecycle/authorize-recreation', [FragCityAPI::class, 'adminLifecycleAuthorizeRecreation'])
    ->middleware('throttle:5,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-api.admin.lifecycle.authorize-recreation');
