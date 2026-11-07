<?php

namespace Paymenter\Extensions\Others\FragCitySupportAPI\Services;

use App\Enums\InvoiceTransactionStatus;
use App\Models\InvoiceTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupportAccountingService
{
    /**
     * Paymenter v1.5.9 does not expose invoice transactions in its Admin API.
     * Keep this endpoint read-only; invoices and invoice items use Paymenter's
     * official Admin API directly.
     */
    public function transactions(array $payload, User $user): JsonResponse
    {
        $validator = Validator::make($payload, [
            'status' => ['nullable', 'string', Rule::in(['processing', 'succeeded', 'failed'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'fields' => $validator->errors()->keys(),
            ], 422);
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

        $transactions = $query->paginate(
            $perPage,
            ['*'],
            'page',
            (int) ($data['page'] ?? 1)
        );

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
            'source' => 'paymenter_invoice_transactions',
        ]);
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
            'amount' => (string) $transaction->amount,
            'fee' => (string) $transaction->fee,
            'currency_code' => (string) ($transaction->invoice?->currency_code ?? ''),
            'status' => $status,
            'transaction_reference' => $transaction->transaction_id !== null
                ? (string) $transaction->transaction_id
                : null,
            'is_credit_transaction' => (bool) $transaction->is_credit_transaction,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
