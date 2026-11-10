<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Server orders previously enforced ZarinPal uniqueness only within
        // their own table. The shared receipt ledger below extends that
        // protection across server purchases and wallet deposits.
        Schema::table('fragcity_server_orders', function (Blueprint $table): void {
            $table->string('payment_method', 16)->default('zarinpal')->after('zarinpal_ref_id');
            $table->string('zarinpal_authority', 128)->charset('ascii')->collation('ascii_bin')->nullable()->change();
            $table->string('zarinpal_ref_id', 64)->charset('ascii')->collation('ascii_bin')->nullable()->change();
        });

        Schema::create('fragcity_payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 16)->default('zarinpal')->charset('ascii')->collation('ascii_bin');
            $table->string('authority', 128)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('ref_id', 64)->charset('ascii')->collation('ascii_bin')->unique();
            $table->char('reference', 32)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('purpose', 24)->charset('ascii')->collation('ascii_bin');
            $table->char('steamid64', 17)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('amount_rial');
            $table->string('currency_code', 12)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('paymenter_invoice_id')->nullable();
            $table->timestamps();

            $table->index(['steamid64', 'created_at'], 'fragcity_receipts_steam_created_idx');
            $table->index(['purpose', 'created_at'], 'fragcity_receipts_purpose_created_idx');
        });

        Schema::create('fragcity_wallet_topups', function (Blueprint $table): void {
            $table->id();
            $table->char('topup_ref', 32)->charset('ascii')->collation('ascii_bin')->unique();
            $table->char('steamid64', 17)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('amount_rial');
            $table->string('currency_code', 12)->charset('ascii')->collation('ascii_bin');
            $table->char('payload_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('paymenter_user_id');
            $table->unsignedBigInteger('paymenter_invoice_id')->nullable();
            $table->string('status', 24)->default('pending_payment')->charset('ascii')->collation('ascii_bin');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['steamid64', 'created_at'], 'fragcity_wallet_topups_steam_created_idx');
            $table->index(['paymenter_invoice_id'], 'fragcity_wallet_topups_invoice_idx');
            $table->index(['status', 'expires_at'], 'fragcity_wallet_topups_status_expiry_idx');
        });

        // Backfill every already-accepted server receipt so that a receipt
        // used before this migration cannot be spent again on a wallet top-up.
        DB::table('fragcity_server_orders')
            ->whereNotNull('zarinpal_authority')
            ->whereNotNull('zarinpal_ref_id')
            ->orderBy('id')
            ->get()
            ->each(function ($order): void {
                DB::table('fragcity_payment_receipts')->insert([
                    'provider' => 'zarinpal',
                    'authority' => (string) $order->zarinpal_authority,
                    'ref_id' => (string) $order->zarinpal_ref_id,
                    'reference' => (string) $order->order_ref,
                    'purpose' => 'cs2_server',
                    'steamid64' => (string) $order->steamid64,
                    'amount_toman' => (int) $order->amount_toman,
                    'amount_rial' => (int) $order->amount_rial,
                    'currency_code' => (string) $order->currency_code,
                    'paymenter_invoice_id' => (int) $order->paymenter_invoice_id,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        $hasWalletOrders = Schema::hasTable('fragcity_server_orders')
            && DB::table('fragcity_server_orders')
                ->whereNull('zarinpal_authority')
                ->orWhereNull('zarinpal_ref_id')
                ->exists();

        $hasWalletTopups = Schema::hasTable('fragcity_wallet_topups')
            && DB::table('fragcity_wallet_topups')->exists();

        if ($hasWalletOrders || $hasWalletTopups) {
            throw new RuntimeException(
                'Cannot roll back FragCityAPI wallet support while wallet orders or wallet top-ups exist.'
            );
        }

        Schema::dropIfExists('fragcity_wallet_topups');
        Schema::dropIfExists('fragcity_payment_receipts');

        Schema::table('fragcity_server_orders', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
            $table->string('zarinpal_authority', 128)->charset('ascii')->collation('ascii_bin')->nullable(false)->change();
            $table->string('zarinpal_ref_id', 64)->charset('ascii')->collation('ascii_bin')->nullable(false)->change();
        });
    }
};
