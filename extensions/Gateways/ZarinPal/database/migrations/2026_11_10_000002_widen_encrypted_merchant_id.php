<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel-encrypted merchant IDs are longer than the original
        // 64-character plaintext UUID column.
        Schema::table('zarinpal_gateway_payments', function (Blueprint $table): void {
            $table->text('merchant_id')->change();
        });

        // Migrate pending attempts created by v1.0.0. New attempts are
        // already encrypted by the v1.0.1 gateway code. The decrypt probe
        // makes this safe to retry if a deployment is interrupted.
        DB::table('zarinpal_gateway_payments')
            ->orderBy('id')
            ->get(['id', 'merchant_id'])
            ->each(function ($payment): void {
                $stored = (string) $payment->merchant_id;
                if ($stored === '') {
                    return;
                }

                try {
                    Crypt::decryptString($stored);

                    return; // Already encrypted.
                } catch (Throwable) {
                    DB::table('zarinpal_gateway_payments')
                        ->where('id', $payment->id)
                        ->update(['merchant_id' => Crypt::encryptString($stored)]);
                }
            });
    }

    public function down(): void
    {
        // Restore the plaintext UUID values before narrowing the column so
        // a deliberate downgrade to v1.0.0 remains possible.
        DB::table('zarinpal_gateway_payments')
            ->orderBy('id')
            ->get(['id', 'merchant_id'])
            ->each(function ($payment): void {
                $stored = (string) $payment->merchant_id;
                if ($stored === '') {
                    return;
                }

                try {
                    $plain = Crypt::decryptString($stored);
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        'Cannot safely downgrade the ZarinPal merchant ID column because an encrypted value could not be decrypted.',
                        0,
                        $e
                    );
                }

                DB::table('zarinpal_gateway_payments')
                    ->where('id', $payment->id)
                    ->update(['merchant_id' => $plain]);
            });

        Schema::table('zarinpal_gateway_payments', function (Blueprint $table): void {
            $table->string('merchant_id', 64)->change();
        });
    }
};
