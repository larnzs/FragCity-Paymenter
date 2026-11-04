<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fragcity_service_servers', function (Blueprint $table): void {
            $table->timestamp('last_reconciled_at')->nullable()->after('last_seen_at');
            $table->uuid('recreation_authorization_id')->nullable()->unique()->after('last_reconciled_at');
            $table->timestamp('recreation_authorized_at')->nullable()->after('recreation_authorization_id');
            $table->string('recreation_authorized_by', 17)->nullable()->after('recreation_authorized_at');
            $table->string('recreation_authorized_username', 64)->nullable()->after('recreation_authorized_by');
            $table->string('recreation_authorization_reason', 500)->nullable()->after('recreation_authorized_username');
            $table->timestamp('recreation_authorization_consumed_at')->nullable()->after('recreation_authorization_reason');
            $table->timestamp('recreation_authorization_revoked_at')->nullable()->after('recreation_authorization_consumed_at');
            $table->string('recreation_authorization_revoked_by', 17)->nullable()->after('recreation_authorization_revoked_at');
            $table->string('recreation_authorization_revoke_reason', 500)->nullable()->after('recreation_authorization_revoked_by');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fragcity_service_servers')) {
            return;
        }

        Schema::table('fragcity_service_servers', function (Blueprint $table): void {
            $table->dropUnique(['recreation_authorization_id']);
            $table->dropColumn([
                'last_reconciled_at',
                'recreation_authorization_id',
                'recreation_authorized_at',
                'recreation_authorized_by',
                'recreation_authorized_username',
                'recreation_authorization_reason',
                'recreation_authorization_consumed_at',
                'recreation_authorization_revoked_at',
                'recreation_authorization_revoked_by',
                'recreation_authorization_revoke_reason',
            ]);
        });
    }
};
