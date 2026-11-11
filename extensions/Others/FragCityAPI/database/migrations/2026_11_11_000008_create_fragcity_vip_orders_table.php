<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragcity_vip_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('order_ref', 32)->charset('ascii')->collation('ascii_bin')->unique();
            $table->char('steamid64', 17)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('paymenter_user_id');
            $table->string('player_name', 64);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('config_option_id');
            $table->unsignedBigInteger('config_value_id');
            $table->unsignedTinyInteger('vip_level');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('duration_seconds');
            $table->decimal('amount_rial', 24, 2);
            $table->unsignedBigInteger('tier_price_rial');
            $table->string('currency_code', 12)->charset('ascii')->collation('ascii_bin');
            $table->string('purchase_type', 16)->default('new')->charset('ascii')->collation('ascii_bin');
            $table->char('upgrade_from_order_ref', 32)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unsignedTinyInteger('upgrade_from_level')->nullable();
            $table->unsignedBigInteger('upgrade_from_expires')->nullable();
            $table->unsignedInteger('upgrade_remaining_seconds')->nullable();
            $table->char('payload_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('paymenter_invoice_id')->nullable()->unique();
            $table->string('status', 24)->default('paid')->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('vip_granted_at')->nullable();
            $table->unsignedBigInteger('vip_expires_at')->nullable();
            $table->unsignedInteger('provision_attempts')->default(0);
            $table->string('last_error', 96)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['steamid64', 'created_at'], 'fragcity_vip_orders_steam_created_idx');
            $table->index(['paymenter_user_id', 'created_at'], 'fragcity_vip_orders_user_created_idx');
            $table->index(['steamid64', 'status'], 'fragcity_vip_orders_steam_status_idx');
            $table->index(['upgrade_from_order_ref'], 'fragcity_vip_orders_upgrade_source_idx');
        });

        Schema::create('fragcity_vip_refunds', function (Blueprint $table): void {
            $table->id();
            $table->char('refund_ref', 32)->charset('ascii')->collation('ascii_bin')->unique();
            $table->char('steamid64', 17)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('paymenter_user_id');
            $table->unsignedBigInteger('paymenter_invoice_id')->nullable();
            $table->char('revoked_order_ref', 32)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->decimal('amount_rial', 24, 2);
            $table->string('currency_code', 12)->charset('ascii')->collation('ascii_bin');
            $table->string('status', 24)->default('credited')->charset('ascii')->collation('ascii_bin');
            $table->timestamps();

            $table->index(['steamid64', 'created_at'], 'fragcity_vip_refunds_steam_created_idx');
        });
    }

    public function down(): void
    {
        $hasOrders = Schema::hasTable('fragcity_vip_orders')
            && DB::table('fragcity_vip_orders')->exists();
        $hasRefunds = Schema::hasTable('fragcity_vip_refunds')
            && DB::table('fragcity_vip_refunds')->exists();

        if ($hasOrders || $hasRefunds) {
            throw new RuntimeException(
                'Cannot roll back FragCity VIP store tables after paid orders or refunds have been recorded.'
            );
        }

        Schema::dropIfExists('fragcity_vip_refunds');
        Schema::dropIfExists('fragcity_vip_orders');
    }
};
