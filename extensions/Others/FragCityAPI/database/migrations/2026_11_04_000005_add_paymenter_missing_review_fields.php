<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fragcity_service_servers', function (Blueprint $table): void {
            $table->timestamp('paymenter_missing_reviewed_at')->nullable();
            $table->string('paymenter_missing_reviewed_by', 17)->nullable();
            $table->string('paymenter_missing_reviewed_username', 64)->nullable();
            $table->string('paymenter_missing_review_reason', 500)->nullable();
            $table->string('paymenter_missing_review_decision', 32)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fragcity_service_servers')) {
            return;
        }

        Schema::table('fragcity_service_servers', function (Blueprint $table): void {
            $table->dropColumn([
                'paymenter_missing_reviewed_at',
                'paymenter_missing_reviewed_by',
                'paymenter_missing_reviewed_username',
                'paymenter_missing_review_reason',
                'paymenter_missing_review_decision',
            ]);
        });
    }
};
