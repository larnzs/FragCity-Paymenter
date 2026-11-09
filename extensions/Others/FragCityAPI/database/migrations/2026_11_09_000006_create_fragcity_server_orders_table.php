<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragcity_server_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('order_ref', 32)->charset('ascii')->collation('ascii_bin')->unique();
            $table->char('steamid64', 17)->charset('ascii')->collation('ascii_bin');
            $table->string('zarinpal_authority', 128)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('zarinpal_ref_id', 64)->charset('ascii')->collation('ascii_bin')->unique();
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('amount_rial');
            $table->string('currency_code', 12)->charset('ascii')->collation('ascii_bin');
            $table->string('currency_unit', 8)->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('duration_days');
            $table->char('payload_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('paymenter_user_id');
            $table->unsignedBigInteger('paymenter_order_id');
            $table->unsignedBigInteger('paymenter_invoice_id');
            $table->unsignedBigInteger('paymenter_service_id');
            $table->string('status', 24)->default('accepted');
            $table->timestamps();

            $table->index(['steamid64', 'created_at'], 'fragcity_server_orders_steam_created_idx');
            $table->index(['paymenter_service_id'], 'fragcity_server_orders_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fragcity_server_orders');
    }
};
