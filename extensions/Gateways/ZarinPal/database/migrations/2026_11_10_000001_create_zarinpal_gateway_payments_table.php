<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zarinpal_gateway_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->string('authority', 128)->nullable()->unique();
            $table->string('ref_id', 64)->nullable()->unique();
            $table->string('merchant_id', 64);
            $table->boolean('sandbox')->default(false);
            $table->string('currency_code', 3);
            $table->string('currency_unit', 8);
            $table->decimal('amount_paymenter', 17, 2);
            $table->unsignedBigInteger('amount_rial');
            $table->string('status', 24)->index();
            $table->timestamp('verify_started_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['invoice_id', 'status', 'expires_at'], 'zarinpal_invoice_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zarinpal_gateway_payments');
    }
};
