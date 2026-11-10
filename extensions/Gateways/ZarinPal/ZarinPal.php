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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

#[ExtensionMeta(
    name: 'ZarinPal',
    description: 'Pay Paymenter invoices and wallet top-ups securely through ZarinPal v4.',
    version: '1.0.4',
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
                'type' => 'password',
                'required' => true,
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
                'name' => 'callback_base_url',
                'label' => 'Public Paymenter URL',
                'type' => 'text',
                'required' => false,
                'default' => 'https://checkout.fragcity.ir',
                'validation' => 'nullable|url',
                'description' => 'Public HTTPS base URL used for ZarinPal callbacks. FragCity uses https://checkout.fragcity.ir.',
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

        $this->ensurePaymentTable();

        try {
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
                    $activeAmount = number_format((float) $activeAttempt->amount_paymenter, 2, '.', '');
                    if ($activeAmount !== $amountPaymenter) {
                        throw new DisplayException('The outstanding invoice amount changed while an earlier ZarinPal payment is still active. Check the invoice or wait for the existing payment attempt to expire.');
                    }

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
                'merchant_id' => Crypt::encryptString($merchantId),
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
        } catch (DisplayException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::critical('FragCity ZarinPal payment attempt could not be prepared.', [
                'invoice_id' => (int) $invoice->id,
                'phase' => 'payment_attempt_prepare',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new DisplayException('ZarinPal could not prepare this payment. The administrator can find the exact cause in the Paymenter application log.');
        }

        if (isset($attempt['reuse_url'])) {
            return $attempt['reuse_url'];
        }

        $apiBase = $this->apiBase((bool) $attempt['sandbox']);
        $descriptionPrefix = trim((string) $this->config('description_prefix'));
        $description = trim(($descriptionPrefix !== '' ? $descriptionPrefix : 'FragCity')
            . ' invoice #' . $attempt['invoice_id']);

        $callbackUrl = null;
        $responseStatus = null;
        $responseContentType = null;
        $providerErrorCode = null;
        $providerErrorMessage = null;

        try {
            $callbackUrl = $this->buildCallbackUrl((string) $attempt['token']);

            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($apiBase . '/request.json', [
                    'merchant_id' => $attempt['merchant_id'],
                    'amount' => $attempt['amount_rial'],
                    'currency' => 'IRR',
                    'callback_url' => $callbackUrl,
                    'description' => mb_substr($description, 0, 255),
                    'metadata' => [
                        'invoice_id' => (string) $attempt['invoice_id'],
                    ],
                ]);

            $responseStatus = $response->status();
            $responseContentType = $response->header('Content-Type');
            $body = $response->json();

            // Extract only the provider's error fields. Do not log the complete
            // request/response payload: it may contain merchant or callback data.
            $providerErrorCode = $this->safeDiagnosticText(
                data_get($body, 'errors.code') ?? data_get($body, 'data.code'),
                [],
                80
            );
            $providerErrorMessage = $this->safeDiagnosticText(
                data_get($body, 'errors.message')
                    ?? data_get($body, 'message')
                    ?? data_get($body, 'error'),
                [
                    (string) $attempt['merchant_id'],
                    (string) $attempt['token'],
                    (string) $callbackUrl,
                ],
                500
            );

            if (!$response->successful()) {
                $diagnostic = 'ZarinPal request returned HTTP ' . $responseStatus . '.';

                if ($providerErrorCode !== null) {
                    $diagnostic .= ' Provider error code: ' . $providerErrorCode . '.';
                }

                if ($providerErrorMessage !== null) {
                    $diagnostic .= ' Provider message: ' . $providerErrorMessage;
                }

                throw new RuntimeException($diagnostic);
            }

            $code = (int) data_get($body, 'data.code', 0);
            $authority = trim((string) data_get($body, 'data.authority', ''));

            if ($code !== 100 || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $authority)) {
                $diagnostic = 'ZarinPal did not create a payment authority (data.code: ' . $code . ').';

                if ($providerErrorCode !== null) {
                    $diagnostic .= ' Provider error code: ' . $providerErrorCode . '.';
                }

                if ($providerErrorMessage !== null) {
                    $diagnostic .= ' Provider message: ' . $providerErrorMessage;
                }

                throw new RuntimeException($diagnostic);
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
                'phase' => 'payment_request',
                'invoice_id' => (int) $attempt['invoice_id'],
                'payment_attempt_id' => (int) $attempt['id'],
                'sandbox' => (bool) $attempt['sandbox'],
                'currency_code' => (string) $attempt['currency_code'],
                'currency_unit' => (string) $currencyUnit,
                'amount_rial' => (int) $attempt['amount_rial'],
                'api_host' => parse_url($apiBase, PHP_URL_HOST),
                'callback_host' => is_string($callbackUrl)
                    ? parse_url($callbackUrl, PHP_URL_HOST)
                    : null,
                'callback_path' => is_string($callbackUrl)
                    ? parse_url($callbackUrl, PHP_URL_PATH)
                    : null,
                'http_status' => $responseStatus,
                'response_content_type' => $this->safeDiagnosticText($responseContentType, [], 120),
                'provider_error_code' => $providerErrorCode,
                'provider_error_message' => $providerErrorMessage,
                'exception' => get_class($e),
                'message' => $this->safeDiagnosticText(
                    $e->getMessage(),
                    [
                        (string) $attempt['merchant_id'],
                        (string) $attempt['token'],
                        is_string($callbackUrl) ? $callbackUrl : '',
                    ],
                    500
                ),
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

        try {
            $this->ensurePaymentTable();

            $payment = DB::table(self::TABLE)
                ->where('token_hash', hash('sha256', $token))
                ->first();
        } catch (Throwable $e) {
            Log::critical('FragCity ZarinPal callback could not load payment state.', [
                'phase' => 'callback_load_payment',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new DisplayException('ZarinPal callback storage is not ready. The administrator has been notified in the application log.');
        }

        if (
            !$payment
            || !$payment->authority
            || !hash_equals((string) $payment->authority, $authority)
        ) {
            abort(404, 'Payment request not found or expired.');
        }

        $invoice = Invoice::query()->findOrFail((int) $payment->invoice_id);
        $transactionId = 'zarinpal:' . $authority;

        try {
            $claim = DB::transaction(function () use ($payment): string {
                $locked = DB::table(self::TABLE)->where('id', $payment->id)->lockForUpdate()->first();

                if (!$locked) {
                    return 'missing';
                }

                if ($locked->status === 'succeeded') {
                    return 'succeeded';
                }

                if (in_array($locked->status, ['cancelled', 'failed', 'manual_review'], true)) {
                    return $locked->status;
                }

                if (
                    $locked->status === 'verifying'
                    && $locked->verify_started_at
                    && strtotime((string) $locked->verify_started_at) > (time() - self::VERIFY_LEASE_SECONDS)
                ) {
                    return 'in_progress';
                }

                if (!in_array($locked->status, ['pending', 'expired', 'verifying', 'verification_error'], true)) {
                    return 'invalid';
                }

                DB::table(self::TABLE)->where('id', $locked->id)->update([
                    'status' => 'verifying',
                    'verify_started_at' => now(),
                    'updated_at' => now(),
                ]);

                return 'claimed';
            });
        } catch (Throwable $e) {
            Log::critical('FragCity ZarinPal callback could not claim payment state.', [
                'invoice_id' => (int) $invoice->id,
                'phase' => 'callback_claim_payment',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new DisplayException('ZarinPal could not load the payment state. The administrator can find the exact cause in the Paymenter application log.');
        }

        if ($claim === 'succeeded') {
            return redirect()->route('invoices.show', $invoice);
        }

        if ($claim === 'in_progress') {
            return redirect()->to(route('invoices.show', $invoice) . '?checkPayment=1');
        }

        if ($claim !== 'claimed') {
            abort(409, 'This ZarinPal payment attempt cannot be processed.');
        }

        if ($callbackStatus !== 'OK') {
            // Status is a browser-return parameter, not a trusted payment
            // result. Do not permanently cancel the stored attempt based on
            // it: the customer can alter the query string after paying.
            // Leave the authority pending so a later genuine success callback
            // can still be verified server-to-server.
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
                    'merchant_id' => Crypt::decryptString((string) $payment->merchant_id),
                    'amount' => (int) $payment->amount_rial,
                    'authority' => $authority,
                ]);

            if (!$verifyResponse->successful()) {
                throw new RuntimeException('ZarinPal verify returned HTTP ' . $verifyResponse->status() . '.');
            }

            $body = $verifyResponse->json();
            $code = (int) data_get($body, 'data.code', 0);
            $referenceId = trim((string) data_get($body, 'data.ref_id', ''));

            // Match the stable legacy FragCity flow: ZarinPal returns 100
            // for the first successful verify and 101 when this same authority
            // has already been verified. Because the authority, invoice and
            // exact request amount are already bound in our durable attempt
            // row, both codes can safely settle this same Paymenter invoice.
            if (!in_array($code, [100, 101], true) || $referenceId === '') {
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

    private function ensurePaymentTable(): void
    {
        try {
            $tableExists = Schema::hasTable(self::TABLE);
            $merchantColumnType = $tableExists
                ? strtolower((string) Schema::getColumnType(self::TABLE, 'merchant_id'))
                : '';

            // Paymenter catches migration exceptions inside runMigrations().
            // Verify the resulting schema rather than assuming installation
            // succeeded, and repair an older v1.0.0 varchar column if needed.
            if (!$tableExists || $merchantColumnType !== 'text') {
                ExtensionHelper::runMigrations('extensions/Gateways/ZarinPal/database/migrations');
            }

            if (
                !Schema::hasTable(self::TABLE)
                || strtolower((string) Schema::getColumnType(self::TABLE, 'merchant_id')) !== 'text'
            ) {
                throw new RuntimeException('zarinpal_gateway_payments_schema_missing_or_outdated');
            }
        } catch (Throwable $e) {
            Log::critical('FragCity ZarinPal payment table is missing or could not be migrated.', [
                'table' => self::TABLE,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new DisplayException('ZarinPal payment storage is not ready. Check the Paymenter application log and extension migrations.');
        }
    }

    private function buildCallbackUrl(string $token): string
    {
        $baseUrl = trim((string) ($this->config('callback_base_url') ?: 'https://checkout.fragcity.ir'));
        $parts = parse_url($baseUrl);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array((string) ($parts['path'] ?? ''), ['', '/'], true)
        ) {
            throw new RuntimeException('zarinpal_callback_base_url_must_be_a_public_https_origin');
        }

        $relativeCallback = route('extensions.gateways.zarinpal.callback', ['token' => $token], false);
        if (!is_string($relativeCallback) || !str_starts_with($relativeCallback, '/')) {
            throw new RuntimeException('zarinpal_callback_route_is_not_registered');
        }

        return rtrim($baseUrl, '/') . $relativeCallback;
    }

    /**
     * Convert provider diagnostics to bounded, single-line log-safe text.
     * Known request secrets are removed before any provider message is logged.
     */
    private function safeDiagnosticText(mixed $value, array $secrets = [], int $limit = 500): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $text = str_replace($secret, '[redacted]', $text);
            }
        }

        $text = preg_replace('/[\\x00-\\x1F\\x7F]/', ' ', $text) ?? $text;
        $text = preg_replace('/\\s+/', ' ', trim($text)) ?? trim($text);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, max(1, $limit));
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
