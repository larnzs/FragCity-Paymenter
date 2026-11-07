<?php

namespace Paymenter\Extensions\Others\FragCitySupportAPI\Services;

use App\Enums\InvoiceTransactionStatus;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupportAccountingService
{
    public function summary(array $payload, User $user): JsonResponse
    {
        $invoices = $user->invoices()
            ->with(['items', 'transactions'])
            ->orderByDesc('id')
            ->get();

        $byCurrency = [];
        $counts = [
            'total' => $invoices->count(),
            'pending' => 0,
            'paid' => 0,
            'cancelled' => 0,
        ];

        foreach ($invoices as $invoice) {
            $currency = (string) $invoice->currency_code;
            if (!isset($byCurrency[$currency])) {
                $byCurrency[$currency] = [
                    'currency_code' => $currency,
                    'outstanding_amount' => '0.00',
                    'invoiced_amount' => '0.00',
                ];
            }

            $byCurrency[$currency]['invoiced_amount'] = $this->decimal(
                (float) $byCurrency[$currency]['invoiced_amount'] + (float) $invoice->total
            );

            $status = (string) $invoice->status;
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }

            if ($status === Invoice::STATUS_PENDING) {
                $byCurrency[$currency]['outstanding_amount'] = $this->decimal(
                    (float) $byCurrency[$currency]['outstanding_amount'] + max(0, (float) $invoice->remaining)
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_counts' => $counts,
                'balances_by_currency' => array_values($byCurrency),
                'source' => 'paymenter_invoices_and_transactions',
                'wallet_included' => false,
            ],
        ]);
    }

    public function invoices(array $payload, User $user): JsonResponse
    {
        $validator = Validator::make($payload, [
            'status' => ['nullable', 'string', Rule::in([
                Invoice::STATUS_PENDING,
                Invoice::STATUS_PAID,
                Invoice::STATUS_CANCELLED,
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = $user->invoices()
            ->with(['items', 'transactions'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $invoices = $query->paginate($perPage, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'success' => true,
            'data' => $invoices->getCollection()
                ->map(fn (Invoice $invoice) => $this->invoiceSummary($invoice))
                ->values(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function invoice(array $payload, User $user): JsonResponse
    {
        $validator = Validator::make($payload, [
            'invoice_reference' => ['required', 'string', 'min:1', 'max:64'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $reference = trim($validator->validated()['invoice_reference']);
        $query = $user->invoices()->where(function ($builder) use ($reference): void {
            $builder->where('number', $reference);

            if (ctype_digit($reference)) {
                $builder->orWhere('id', (int) $reference);
            }
        });

        $invoice = $query->with(['items', 'transactions'])->first();

        if (!$invoice) {
            return $this->error('invoice_not_found', 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->invoiceSummary($invoice),
                'items' => $invoice->items->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'description' => (string) $item->description,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $this->decimal((float) $item->price),
                    'total' => $this->decimal((float) $item->total()),
                ])->values(),
                'transactions' => $invoice->transactions
                    ->sortBy('id')
                    ->map(fn (InvoiceTransaction $transaction) => $this->transactionResource($transaction))
                    ->values(),
            ],
        ]);
    }

    public function transactions(array $payload, User $user): JsonResponse
    {
        $validator = Validator::make($payload, [
            'status' => ['nullable', 'string', Rule::in(['processing', 'succeeded', 'failed'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = $user->transactions()
            ->with('invoice')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $transactions = $query->paginate($perPage, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'success' => true,
            'data' => $transactions->getCollection()
                ->map(fn (InvoiceTransaction $transaction) => $this->transactionResource($transaction))
                ->values(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    private function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => (int) $invoice->id,
            'number' => $invoice->number !== null ? (string) $invoice->number : (string) $invoice->id,
            'status' => (string) $invoice->status,
            'currency_code' => (string) $invoice->currency_code,
            'total' => $this->decimal((float) $invoice->total),
            'remaining' => $this->decimal((float) $invoice->remaining),
            'due_at' => $invoice->due_at?->toDateString(),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
            'items_count' => $invoice->relationLoaded('items') ? $invoice->items->count() : null,
            'transactions_count' => $invoice->relationLoaded('transactions') ? $invoice->transactions->count() : null,
        ];
    }

    private function transactionResource(InvoiceTransaction $transaction): array
    {
        $status = $transaction->status instanceof InvoiceTransactionStatus
            ? $transaction->status->value
            : (string) $transaction->status;

        return [
            'id' => (int) $transaction->id,
            'invoice_id' => (int) $transaction->invoice_id,
            'invoice_number' => $transaction->invoice?->number !== null
                ? (string) $transaction->invoice->number
                : (string) ($transaction->invoice?->id ?? $transaction->invoice_id),
            'amount' => $this->decimal((float) $transaction->amount),
            'fee' => $this->decimal((float) $transaction->fee),
            'currency_code' => (string) ($transaction->invoice?->currency_code ?? ''),
            'status' => $status,
            'transaction_reference' => $transaction->transaction_id !== null
                ? (string) $transaction->transaction_id
                : null,
            'is_credit_transaction' => (bool) $transaction->is_credit_transaction,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'validation_failed',
            'fields' => $validator->errors()->keys(),
        ], 422);
    }

    private function error(string $error, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }
}
