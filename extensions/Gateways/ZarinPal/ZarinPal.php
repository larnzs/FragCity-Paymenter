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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

#[ExtensionMeta(
    name: 'ZarinPal',
    description: 'Pay Paymenter invoices and wallet top-ups securely through ZarinPal v4.',
    version: '1.0.0',
    author: 'FragCity',
    url: 'https://fragcity.ir'
)]
class ZarinPal extends Gateway
{
    private const TABLE = 'zarinpal_gateway_payments';
    private const ATTEMPT_TTL_MINUTES = 60;
    private const VERIFY_LEASE_SECONDS = 120;

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Gateways/ZarinPal/database/migrations');
    }

    public function upgraded($oldVersion = null): void
    {
        ExtensionHelper::runMigrations('extensions/Gateways/ZarinPal/database/migrations');
    }

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
                'default' => 'IRR',
                'validation' => 'required|string|max:3|regex:/^[A-Za-z0-9_-]+$/',
                'description' => 'Exact Paymenter currency code allowed by this gateway, for example IRR.',
            ],
            [
                'name' => 'currency_unit',
                'label' => 'Paymenter currency unit',
                'type' => 'select',
                'required' => true,
                'default' => 'rial',
                'options' => [
                    ['value' => 'rial', 'label' => 'Rial (send the stored amount unchanged)'],
                    ['value' => 'toman', 'label' => 'Toman (convert to Rial × 10)'],
                ],
                'description' => 'Choose Rial when Paymenter stores invoice amounts in Rial. The unit must match the configured currency.',
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
                'default' => 'FragCity',
                'validation' => 'nullable|string|max:100',
            ],
        ];
    }

    public function canUseGateway($total, $currency, $type, $items = []): bool
    {
        $configuredCurrency = strtoupper(trim((string) $this->config('currency_code')));

        return $configuredCurrency !== ''
            && strtoupper(trim((string) $currency)) === $configuredCurrency;
    }

    /**
     * Create or reuse one durable payment attempt for this invoice.
     *
     * The invoice row is locked while selecting the amount and creating the
     * attempt, so two simultaneous checkout requests do not create two live
     * authorities for the same invoice through this gateway.
     */
    public function pay(Invoice $invoice, $total)
    {
        $merchantId = trim((string) $this->config('merchant_id'));
        $configuredCurrency = strtoupper(trim((string) $this->config('currency_code')));
        $currencyUnit = strtolower(trim((string) $this->config('currency_unit')));
        $sandbox = (bool) $this->config('sandbox');

        if (
            $merchantId === ''
            || $configuredCurrency === ''
            || !in_array($currencyUnit, ['rial', 'toman'], true)
        ) {
            throw new DisplayException('ZarinPal gateway is not configured correctly.');
        }

        $attempt = DB::transaction(function () use (
            $invoice,
            $merchantId,
            $configuredCurrency,
            $currencyUnit,
            $sandbox
        ): array {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedInvoice->status !== Invoice::STATUS_PENDING
                || strtoupper((string) $lockedInvoice->currency_code) !== $configuredCurrency
            ) {
                throw new DisplayException('This invoice is not payable with the configured ZarinPal gateway.');
            }

            $amountPaymenter = number_format((float) $lockedInvoice->remaining, 2, '.', '');
            if (!is_numeric($amountPaymenter) || (float) $amountPaymenter <= 0) {
                throw new DisplayException('There is no outstanding amount to pay on this invoice.');
            }

            $amountRial = $this->toZarinPalRial($amountPaymenter, $currencyUnit);

            $activeAttempt = DB::table(self::TABLE)
                ->where('invoice_id', $lockedInvoice->id)
                ->whereIn('status', ['requesting', 'pending', 'verifying', 'verification_error'])
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($activeAttempt) {
                if ($activeAttempt->status === 'pending' && $activeAttempt->authority) {
                    return [
                        'reuse_url' => $this->paymentUrl((bool) $activeAttempt->sandbox, (string) $activeAttempt->authority),
                    ];
                }

                throw new DisplayException('A ZarinPal payment attempt is already being prepared or verified. Please return to the invoice and check its status.');
            }

            // Expired attempts are retained for audit but are no longer payable.
            DB::table(self::TABLE)
                ->where('invoice_id', $lockedInvoice->id)
                ->whereIn('status', ['requesting', 'pending', 'verifying', 'verification_error'])
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired', 'updated_at' => now()]);

            $token = bin2hex(random_bytes(32));
            $id = DB::table(self::TABLE)->insertGetId([
                'invoice_id' => (int) $lockedInvoice->id,
                'token_hash' => hash('sha256', $token),
                'authority' => null,
                'ref_id' => null,
                'merchant_id' => $merchantId,
                'sandbox' => $sandbox,
                'currency_code' => (string) $lockedInvoice->currency_code,
                'currency_unit' => $currencyUnit,
                'amount_paymenter' => $amountPaymenter,
                'amount_rial' => $amountRial,
                'status' => 'requesting',
                'verify_started_at' => null,
                'verified_at' => null,
                'expires_at' => now()->addMinutes(self::ATTEMPT_TTL_MINUTES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $id,
                'token' => $token,
                'invoice_id' => (int) $lockedInvoice->id,
                'currency_code' => (string) $lockedInvoice->currency_code,
                'amount_paymenter' => $amountPaymenter,
                'amount_rial' => $amountRial,
                'sandbox' => $sandbox,
                'merchant_id' => $merchantId,
            ];
        });

        if (isset($attempt['reuse_url'])) {
            return $attempt['reuse_url'];
        }

        $apiBase = $this->apiBase((bool) $attempt['sandbox']);
        $descriptionPrefix = trim((string) $this->config('description_prefix'));
        $description = trim(($descriptionPrefix !== '' ? $descriptionPrefix : 'FragCity')
            . ' invoice #' . $attempt['invoice_id']);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($apiBase . '/request.json', [
                    'merchant_id' => $attempt['merchant_id'],
                    'amount' => $attempt['amount_rial'],
                    'callback_url' => route('extensions.gateways.zarinpal.callback', [
                        'token' => $attempt['token'],
                    ]),
                    'description' => mb_substr($description, 0, 255),
                    'metadata' => [
                        'invoice_id' => (string) $attempt['invoice_id'],
                    ],
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('ZarinPal request returned HTTP ' . $response->status() . '.');
            }

            $body = $response->json();
            $code = (int) data_get($body, 'data.code', 0);
            $authority = trim((string) data_get($body, 'data.authority', ''));

            if ($code !== 100 || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)) {
                $message = trim((string) data_get($body, 'errors.message', ''));
                throw new RuntimeException($message !== '' ? $message : 'ZarinPal did not create a payment authority.');
            }

            DB::table(self::TABLE)
                ->where('id', $attempt['id'])
                ->where('status', 'requesting')
                ->update([
                    'authority' => $authority,
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);

            return $this->paymentUrl((bool) $attempt['sandbox'], $authority);
        } catch (Throwable $e) {
            DB::table(self::TABLE)
                ->where('id', $attempt['id'])
                ->whereIn('status', ['requesting', 'pending'])
                ->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);

            Log::warning('FragCity ZarinPal payment request failed.', [
                'invoice_id' => $attempt['invoice_id'],
                'exception' => get_class($e),
            ]);

            throw new DisplayException('Could not start the ZarinPal payment. Please try again.');
        }
    }

    /**
     * ZarinPal v4 callback. Browser query values are never trusted as payment
     * proof; the authority is verified server-to-server before addPayment().
     */
    public function callback(Request $request)
    {
        $token = strtolower(trim((string) $request->query('token', '')));
        $authority = trim((string) $request->query('Authority', ''));
        $callbackStatus = strtoupper(trim((string) $request->query('Status', '')));

        if (
            !preg_match('/^[a-f0-9]{64}$/D', $token)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)
        ) {
            abort(400, 'Invalid ZarinPal callback.');
        }

        $payment = DB::table(self::TABLE)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (
            !$payment
            || !$payment->authority
            || !hash_equals((string) $payment->authority, $authority)
            || (
                $payment->status !== 'succeeded'
                && ($payment->expires_at === null || now()->greaterThan($payment->expires_at))
            )
        ) {
            abort(404, 'Payment request not found or expired.');
        }

        $invoice = Invoice::query()->findOrFail((int) $payment->invoice_id);
        $transactionId = 'zarinpal:' . $authority;

        $claim = DB::transaction(function () use ($payment): string {
            $locked = DB::table(self::TABLE)->where('id', $payment->id)->lockForUpdate()->first();

            if (!$locked) {
                return 'missing';
            }

            if ($locked->status === 'succeeded') {
                return 'succeeded';
            }

            if (in_array($locked->status, ['cancelled', 'failed', 'expired', 'manual_review'], true)) {
                return $locked->status;
            }

            if (
                $locked->status === 'verifying'
                && $locked->verify_started_at
                && strtotime((string) $locked->verify_started_at) > (time() - self::VERIFY_LEASE_SECONDS)
            ) {
                return 'in_progress';
            }

            if (!in_array($locked->status, ['pending', 'verifying', 'verification_error'], true)) {
                return 'invalid';
            }

            DB::table(self::TABLE)->where('id', $locked->id)->update([
                'status' => 'verifying',
                'verify_started_at' => now(),
                'updated_at' => now(),
            ]);

            return 'claimed';
        });

        if ($claim === 'succeeded') {
            return redirect()->route('invoices.show', $invoice);
        }

        if ($claim === 'in_progress') {
            return redirect()->route('invoices.show', $invoice) . '?checkPayment=1';
        }

        if ($claim !== 'claimed') {
            abort(409, 'This ZarinPal payment attempt cannot be processed.');
        }

        if ($callbackStatus !== 'OK') {
            DB::table(self::TABLE)->where('id', $payment->id)->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            return redirect()->route('invoices.show', $invoice);
        }

        // If the prior callback verified the authority and recorded Paymenter
        // payment but crashed before updating our ledger, recover idempotently.
        $existingTransaction = InvoiceTransaction::query()
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existingTransaction) {
            if (
                (int) $existingTransaction->invoice_id === (int) $invoice->id
                && $existingTransaction->status === InvoiceTransactionStatus::Succeeded
                && number_format((float) $existingTransaction->amount, 2, '.', '') === number_format((float) $payment->amount_paymenter, 2, '.', '')
            ) {
                $this->markSucceeded($payment->id, (string) ($payment->ref_id ?? ''));
                return redirect()->route('invoices.show', $invoice);
            }

            $this->markManualReview($payment->id, $invoice->id, 'transaction_id_conflict');
            abort(409, 'This payment requires administrator review.');
        }

        try {
            $verifyResponse = Http::acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($this->apiBase((bool) $payment->sandbox) . '/verify.json', [
                    'merchant_id' => (string) $payment->merchant_id,
                    'amount' => (int) $payment->amount_rial,
                    'authority' => $authority,
                ]);

            if (!$verifyResponse->successful()) {
                throw new RuntimeException('ZarinPal verify returned HTTP ' . $verifyResponse->status() . '.');
            }

            $body = $verifyResponse->json();
            $code = (int) data_get($body, 'data.code', 0);
            $referenceId = trim((string) data_get($body, 'data.ref_id', ''));

            // The v4 verify request itself is bound to the stored Rial amount.
            // A successful first verification is code 100 with a receipt ID.
            // Code 101 means the authority was verified earlier; recover it
            // only when the matching successful Paymenter transaction exists
            // (handled above), otherwise leave it for manual reconciliation.
            if ($code === 101) {
                $this->markManualReview($payment->id, $invoice->id, 'already_verified_without_paymenter_transaction');
                return redirect()->route('invoices.show', $invoice);
            }

            if ($code !== 100 || $referenceId === '') {
                DB::table(self::TABLE)->where('id', $payment->id)->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);

                Log::warning('FragCity ZarinPal verification was not successful.', [
                    'invoice_id' => (int) $invoice->id,
                    'code' => $code,
                ]);

                return redirect()->route('invoices.show', $invoice);
            }

            $invoice->refresh();
            $expectedAmount = number_format((float) $invoice->remaining, 2, '.', '');
            $storedAmount = number_format((float) $payment->amount_paymenter, 2, '.', '');

            if (
                $invoice->status !== Invoice::STATUS_PENDING
                || strtoupper((string) $invoice->currency_code) !== strtoupper((string) $payment->currency_code)
                || $expectedAmount !== $storedAmount
            ) {
                $this->markManualReview($payment->id, $invoice->id, 'invoice_amount_or_status_changed');
                return redirect()->route('invoices.show', $invoice);
            }

            // One authority maps to one exact Paymenter transaction ID.
            // addPayment locks the invoice and is the only operation that
            // settles it and triggers native invoice/credit processing.
            ExtensionHelper::addPayment(
                $invoice,
                'ZarinPal',
                (float) $storedAmount,
                null,
                $transactionId,
                InvoiceTransactionStatus::Succeeded
            );

            $this->markSucceeded($payment->id, $referenceId);

            return redirect()->route('invoices.show', $invoice);
        } catch (Throwable $e) {
            // Keep the attempt retryable. A network timeout can happen after
            // ZarinPal has processed the verify request; the next callback
            // can recover via code 101 plus the returned receipt data.
            DB::table(self::TABLE)->where('id', $payment->id)->update([
                'status' => 'verification_error',
                'updated_at' => now(),
            ]);

            Log::error('FragCity ZarinPal payment verification failed.', [
                'invoice_id' => (int) $invoice->id,
                'exception' => get_class($e),
            ]);

            return redirect()->route('invoices.show', $invoice);
        }
    }

    private function markSucceeded(int $paymentId, string $referenceId): void
    {
        DB::table(self::TABLE)->where('id', $paymentId)->update([
            'status' => 'succeeded',
            'ref_id' => $referenceId !== '' ? $referenceId : null,
            'verified_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function markManualReview(int $paymentId, int $invoiceId, string $reason): void
    {
        DB::table(self::TABLE)->where('id', $paymentId)->update([
            'status' => 'manual_review',
            'updated_at' => now(),
        ]);

        Log::critical('FragCity ZarinPal payment requires manual reconciliation.', [
            'payment_attempt_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'reason' => $reason,
        ]);
    }

    private function toZarinPalRial(string $amount, string $unit): int
    {
        $factor = $unit === 'toman' ? 10 : 1;
        $rial = (float) $amount * $factor;
        $roundedRial = round($rial);

        if (
            !is_finite($rial)
            || $rial <= 0
            || $rial > PHP_INT_MAX
            || abs($rial - $roundedRial) > 0.000001
        ) {
            throw new DisplayException('The invoice amount must convert to a whole Rial amount before paying with ZarinPal.');
        }

        return (int) $roundedRial;
    }

    private function apiBase(bool $sandbox): string
    {
        return $sandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://payment.zarinpal.com/pg/v4/payment';
    }

    private function paymentUrl(bool $sandbox, string $authority): string
    {
        $host = $sandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://www.zarinpal.com/pg/StartPay/';

        return $host . rawurlencode($authority);
    }
}
