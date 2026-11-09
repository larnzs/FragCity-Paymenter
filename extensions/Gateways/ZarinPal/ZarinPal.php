<?php

namespace Paymenter\Extensions\Gateways\ZarinPal;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Enums\InvoiceTransactionStatus;
use App\Exceptions\DisplayException;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Throwable;

#[ExtensionMeta(
    name: 'ZarinPal Gateway',
    description: 'Accept invoice payments through the ZarinPal v4 payment gateway.',
    version: '1.0.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class ZarinPal extends Gateway
{
    private const CACHE_PREFIX = 'fragcity:zarinpal:';
    private const CACHE_TTL_MINUTES = 60 * 24 * 7;

    public function boot(): void
    {
        require __DIR__ . '/routes.php';
    }

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'merchant_id',
                'label' => 'ZarinPal Merchant ID',
                'type' => 'text',
                'required' => true,
                'encrypted' => true,
                'validation' => 'required|string|max:64',
            ],
            [
                'name' => 'currency_code',
                'label' => 'Paymenter currency code',
                'type' => 'text',
                'required' => true,
                'validation' => 'required|string|max:12',
                'description' => 'Only invoices using this exact Paymenter currency code will show the gateway.',
            ],
            [
                'name' => 'currency_unit',
                'label' => 'Paymenter currency unit',
                'type' => 'select',
                'required' => true,
                'default' => 'toman',
                'options' => [
                    ['value' => 'toman', 'label' => 'Toman (convert to Rial × 10)'],
                    ['value' => 'rial', 'label' => 'Rial (no conversion)'],
                ],
                'description' => 'Must match the unit used by the Paymenter currency attached to invoices paid through this gateway.',
            ],
            [
                'name' => 'sandbox',
                'label' => 'Sandbox mode',
                'type' => 'checkbox',
                'required' => false,
                'default' => false,
            ],
            [
                'name' => 'description_prefix',
                'label' => 'Payment description prefix',
                'type' => 'text',
                'required' => false,
                'default' => 'FragCity invoice',
            ],
        ];
    }

    public function canUseGateway($total, $currency, $type, $items = []): bool
    {
        return strtoupper(trim((string) $currency))
            === strtoupper(trim((string) $this->config('currency_code')));
    }

    public function pay(Invoice $invoice, $total)
    {
        $merchantId = trim((string) $this->config('merchant_id'));
        $configuredCurrency = strtoupper(trim((string) $this->config('currency_code')));
        if ($configuredCurrency === '' || strtoupper((string) $invoice->currency_code) !== $configuredCurrency) {
            throw new DisplayException('This Paymenter invoice uses a currency not configured for ZarinPal.');
        }
        $currencyUnit = strtolower(trim((string) $this->config('currency_unit')));
        $amountPaymenter = round((float) $total, 2);

        if (
            $merchantId === ''
            || !in_array($currencyUnit, ['toman', 'rial'], true)
            || !is_finite($amountPaymenter)
            || $amountPaymenter <= 0
        ) {
            throw new DisplayException('ZarinPal gateway is not configured correctly.');
        }

        $amountRial = (int) round(
            $amountPaymenter * ($currencyUnit === 'toman' ? 10 : 1)
        );
        if ($amountRial < 10) {
            throw new DisplayException('The ZarinPal payment amount is below the supported minimum.');
        }

        $sandbox = (bool) $this->config('sandbox');
        $apiBase = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://payment.zarinpal.com/pg/v4/payment';
        $paymentBase = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://www.zarinpal.com/pg/StartPay/';

        $token = bin2hex(random_bytes(32));
        $cacheKey = self::CACHE_PREFIX . $token;

        $transactionContext = [
            'invoice_id' => (int) $invoice->id,
            'currency_code' => (string) $invoice->currency_code,
            'currency_unit' => $currencyUnit,
            'amount_paymenter' => number_format($amountPaymenter, 2, '.', ''),
            'amount_rial' => $amountRial,
            'status' => 'requesting',
            'authority' => null,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $transactionContext, now()->addMinutes(self::CACHE_TTL_MINUTES));

        $descriptionPrefix = trim((string) $this->config('description_prefix'));
        $description = trim($descriptionPrefix . ' #' . ($invoice->number ?: $invoice->id));
        $payload = [
            'merchant_id' => $merchantId,
            'amount' => $amountRial,
            'callback_url' => route('extensions.gateways.zarinpal.callback', ['token' => $token]),
            'description' => mb_substr($description, 0, 255),
            'metadata' => [
                'email' => (string) ($invoice->user?->email ?? ''),
                'invoice_id' => (string) $invoice->id,
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->post($apiBase . '/request.json', $payload);

            if (!$response->successful()) {
                throw new RuntimeException('ZarinPal request returned HTTP ' . $response->status() . '.');
            }

            $body = $response->json();
            $code = (int) data_get($body, 'data.code', 0);
            $authority = trim((string) data_get($body, 'data.authority', ''));

            if ($code !== 100 || $authority === '') {
                $message = trim((string) data_get($body, 'errors.message', ''));
                throw new RuntimeException(
                    $message !== '' ? $message : 'ZarinPal did not create a payment authority.'
                );
            }

            $transactionContext['status'] = 'pending';
            $transactionContext['authority'] = $authority;
            Cache::put($cacheKey, $transactionContext, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return redirect()->away($paymentBase . rawurlencode($authority));
        } catch (Throwable $e) {
            Cache::forget($cacheKey);
            Log::warning('FragCity ZarinPal payment request failed.', [
                'invoice_id' => (int) $invoice->id,
                'exception' => get_class($e),
            ]);

            throw new DisplayException('Could not start the ZarinPal payment. Please try again.');
        }
    }

    public function callback(Request $request)
    {
        $token = strtolower(trim((string) $request->query('token', '')));
        $authority = trim((string) $request->query('Authority', ''));
        $status = strtoupper(trim((string) $request->query('Status', '')));

        if (
            !preg_match('/^[a-f0-9]{64}$/D', $token)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
        ) {
            abort(400, 'Invalid ZarinPal callback.');
        }

        $cacheKey = self::CACHE_PREFIX . $token;
        $context = Cache::get($cacheKey);

        if (
            !is_array($context)
            || (int) ($context['invoice_id'] ?? 0) < 1
            || !hash_equals((string) ($context['authority'] ?? ''), $authority)
        ) {
            abort(404, 'Payment request not found or expired.');
        }

        $invoice = Invoice::query()->findOrFail((int) $context['invoice_id']);
        $transactionId = 'zarinpal:' . $authority;

        $existing = InvoiceTransaction::query()
            ->where('transaction_id', $transactionId)
            ->where('invoice_id', $invoice->id)
            ->first();

        if ($existing && $existing->status === InvoiceTransactionStatus::Succeeded) {
            return redirect()->route('invoices.show', $invoice);
        }

        if ($status !== 'OK') {
            $context['status'] = 'cancelled';
            Cache::put($cacheKey, $context, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return redirect()->route('invoices.show', $invoice);
        }

        $currencyUnit = (string) ($context['currency_unit'] ?? '');
        $amountPaymenter = (float) ($context['amount_paymenter'] ?? 0);
        $amountRial = (int) ($context['amount_rial'] ?? 0);
        $expectedRial = (int) round(
            (float) $invoice->remaining * ($currencyUnit === 'toman' ? 10 : 1)
        );

        if (
            !in_array($currencyUnit, ['toman', 'rial'], true)
            || strtoupper((string) $invoice->currency_code) !== strtoupper(trim((string) $this->config('currency_code')))
            || $amountRial < 10
            || !hash_equals((string) $context['currency_code'], (string) $invoice->currency_code)
            || $expectedRial !== $amountRial
        ) {
            Log::warning('FragCity ZarinPal callback amount mismatch.', [
                'invoice_id' => (int) $invoice->id,
            ]);
            abort(422, 'Payment amount does not match the invoice.');
        }

        $sandbox = (bool) $this->config('sandbox');
        $apiBase = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://payment.zarinpal.com/pg/v4/payment';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->post($apiBase . '/verify.json', [
                    'merchant_id' => trim((string) $this->config('merchant_id')),
                    'amount' => $amountRial,
                    'authority' => $authority,
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('ZarinPal verify returned HTTP ' . $response->status() . '.');
            }

            $body = $response->json();
            $code = (int) data_get($body, 'data.code', 0);
            $verifiedAmount = (int) data_get($body, 'data.amount', $amountRial);

            // Code 101 means this authority was already verified. It is safe
            // only because the unguessable callback token is bound in cache to
            // this exact invoice and authority.
            if (!in_array($code, [100, 101], true) || $verifiedAmount !== $amountRial) {
                throw new RuntimeException('ZarinPal did not verify the expected payment.');
            }

            $existing = InvoiceTransaction::query()
                ->where('transaction_id', $transactionId)
                ->where('invoice_id', $invoice->id)
                ->first();

            if (!$existing) {
                ExtensionHelper::addPayment(
                    $invoice,
                    'ZarinPal',
                    $amountPaymenter,
                    null,
                    $transactionId,
                    InvoiceTransactionStatus::Succeeded
                );
            } elseif ($existing->status !== InvoiceTransactionStatus::Succeeded) {
                throw new RuntimeException('A non-successful transaction already uses this ZarinPal authority.');
            }

            $context['status'] = 'succeeded';
            $context['reference_id'] = (string) data_get($body, 'data.ref_id', '');
            Cache::put($cacheKey, $context, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return redirect()->route('invoices.show', $invoice);
        } catch (Throwable $e) {
            Log::error('FragCity ZarinPal payment verification failed.', [
                'invoice_id' => (int) $invoice->id,
                'exception' => get_class($e),
            ]);

            return redirect()->route('invoices.show', $invoice);
        }
    }
}
