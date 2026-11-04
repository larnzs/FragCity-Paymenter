<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'fragcity_service_servers';
        $claimedAtColumn = 'recreation_authorization_claimed_at';
        $expiresAtColumn = 'recreation_authorization_claim_expires_at';
        $indexName = 'fragcity_srv_claim_exp_idx';

        if (!Schema::hasTable($tableName)) {
            throw new RuntimeException('FragCity lifecycle table is missing.');
        }

        // MariaDB keeps successful DDL statements even if a later statement in
        // the same migration fails. Guard each column so this migration can
        // safely resume after a partially applied attempt.
        if (!Schema::hasColumn($tableName, $claimedAtColumn)) {
            Schema::table($tableName, function (Blueprint $table) use ($claimedAtColumn): void {
                $table->timestamp($claimedAtColumn)
                    ->nullable()
                    ->after('recreation_authorization_reason');
            });
        }

        if (!Schema::hasColumn($tableName, $expiresAtColumn)) {
            Schema::table($tableName, function (Blueprint $table) use (
                $claimedAtColumn,
                $expiresAtColumn
            ): void {
                $table->timestamp($expiresAtColumn)
                    ->nullable()
                    ->after($claimedAtColumn);
            });
        }

        // The default Laravel-generated name exceeds MariaDB's 64-character
        // identifier limit. Always use a short, explicit index name.
        $indexExists = DB::selectOne(
            'SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$tableName, $indexName]
        );

        if ($indexExists === null) {
            Schema::table($tableName, function (Blueprint $table) use (
                $expiresAtColumn,
                $indexName
            ): void {
                $table->index($expiresAtColumn, $indexName);
            });
        }
    }

    public function down(): void
    {
        $tableName = 'fragcity_service_servers';
        $indexName = 'fragcity_srv_claim_exp_idx';

        if (!Schema::hasTable($tableName)) {
            return;
        }

        $indexExists = DB::selectOne(
            'SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$tableName, $indexName]
        );

        if ($indexExists !== null) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }

        $columnsToDrop = array_values(array_filter(
            [
                'recreation_authorization_claimed_at',
                'recreation_authorization_claim_expires_at',
            ],
            fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($columnsToDrop !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
