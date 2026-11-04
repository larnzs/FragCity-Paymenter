<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fragcity_service_servers', function (Blueprint $table): void {
            $table->timestamp('recreation_authorization_claimed_at')->nullable()->after('recreation_authorization_reason');
            $table->timestamp('recreation_authorization_claim_expires_at')->nullable()->after('recreation_authorization_claimed_at')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fragcity_service_servers')) {
            return;
        }

        Schema::table('fragcity_service_servers', function (Blueprint $table): void {
            $table->dropIndex(['recreation_authorization_claim_expires_at']);
            $table->dropColumn([
                'recreation_authorization_claimed_at',
                'recreation_authorization_claim_expires_at',
            ]);
        });
    }
};
