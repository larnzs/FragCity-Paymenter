<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\FragCitySupportAPI\FragCitySupportAPI;

Route::post('/api/v1/fragcity/support/ping', [FragCitySupportAPI::class, 'ping'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.ping');

// Customer ticket operations. The signed SteamID64 is resolved server-side
// to a Paymenter user; callers never submit a Paymenter user_id.
Route::post('/api/v1/fragcity/support/tickets/options', [FragCitySupportAPI::class, 'ticketOptions'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.options');

Route::post('/api/v1/fragcity/support/tickets/list', [FragCitySupportAPI::class, 'ticketsList'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.list');

Route::post('/api/v1/fragcity/support/tickets/show', [FragCitySupportAPI::class, 'ticketShow'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.show');

Route::post('/api/v1/fragcity/support/tickets/create', [FragCitySupportAPI::class, 'ticketCreate'])
    ->middleware('throttle:12,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.create');

Route::post('/api/v1/fragcity/support/tickets/reply', [FragCitySupportAPI::class, 'ticketReply'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.reply');

Route::post('/api/v1/fragcity/support/tickets/close', [FragCitySupportAPI::class, 'ticketClose'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.close');

Route::post('/api/v1/fragcity/support/tickets/reopen', [FragCitySupportAPI::class, 'ticketReopen'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.tickets.reopen');

Route::post('/api/v1/fragcity/support/attachments/download', [FragCitySupportAPI::class, 'attachmentDownload'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.attachments.download');

// Read-only Paymenter invoice and transaction views. FragCity remains the
// owner of its own wallet and customer payment settlement.
Route::post('/api/v1/fragcity/support/accounting/summary', [FragCitySupportAPI::class, 'accountingSummary'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.accounting.summary');

Route::post('/api/v1/fragcity/support/accounting/invoices/list', [FragCitySupportAPI::class, 'invoicesList'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.accounting.invoices.list');

Route::post('/api/v1/fragcity/support/accounting/invoices/show', [FragCitySupportAPI::class, 'invoiceShow'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.accounting.invoices.show');

Route::post('/api/v1/fragcity/support/accounting/transactions/list', [FragCitySupportAPI::class, 'transactionsList'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.fragcity-support-api.accounting.transactions.list');
