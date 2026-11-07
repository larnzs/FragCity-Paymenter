<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\FragCitySupportAPI\FragCitySupportAPI;

Route::post('/api/v1/fragcity/support/ping', [FragCitySupportAPI::class, 'ping'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.ping');

// FragCity-only identity lookup. The site sends the authenticated customer's
// SteamID64; the extension resolves it through the existing fragcity_user_links.
Route::post('/api/v1/fragcity/support/identity/resolve', [FragCitySupportAPI::class, 'resolveIdentity'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.identity.resolve');

// Paymenter API v1 owns ticket CRUD. This route only returns configured
// departments and compatibility data not exposed by Paymenter's Admin API.
Route::post('/api/v1/fragcity/support/tickets/options', [FragCitySupportAPI::class, 'ticketOptions'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.options');

// Paymenter's Admin API has no ticket-message attachment upload endpoint.
Route::post('/api/v1/fragcity/support/attachments/upload', [FragCitySupportAPI::class, 'attachmentUpload'])
    ->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.attachments.upload');

Route::post('/api/v1/fragcity/support/attachments/list', [FragCitySupportAPI::class, 'attachmentList'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.attachments.list');

Route::post('/api/v1/fragcity/support/attachments/download', [FragCitySupportAPI::class, 'attachmentDownload'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.attachments.download');

// Paymenter v1.5.9 has no invoice-transactions resource in its Admin API.
// Keep only this read-only compatibility endpoint here.
Route::post('/api/v1/fragcity/support/accounting/transactions/list', [FragCitySupportAPI::class, 'transactionsList'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.accounting.transactions.list');
