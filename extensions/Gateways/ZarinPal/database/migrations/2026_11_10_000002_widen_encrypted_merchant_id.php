<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel-encrypted merchant IDs are longer than the original
        // 64-character plaintext UUID column.
        Schema::table('zarinpal_gateway_payments', function (Blueprint $table): void {
            $table->text('merchant_id')->change();
        });
    }

    public function down(): void
    {
        // Deliberately keep the wider column. Encrypted merchant IDs exceed
        // 64 characters, and narrowing it would destroy the stored ciphertext.
    }
};
