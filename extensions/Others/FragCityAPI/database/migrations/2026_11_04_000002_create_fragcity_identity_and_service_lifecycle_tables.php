<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragcity_customer_identities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('customer_uuid')->unique();
            $table->string('steamid64', 17)->charset('ascii')->collation('ascii_bin')->unique();

            // Deliberately no foreign keys: this durable identity must survive
            // Paymenter account deletion and auto-increment ID reuse.
            $table->unsignedBigInteger('paymenter_user_id')->nullable()->unique();
            $table->unsignedBigInteger('pterodactyl_user_id')->nullable()->unique();

            $table->string('status', 32)->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('fragcity_service_servers', function (Blueprint $table): void {
            $table->id();

            // One mapping per Paymenter service; intentionally no FK to
            // Paymenter services/users so deletion leaves an auditable tombstone.
            $table->unsignedBigInteger('paymenter_service_id')->unique();
            $table->unsignedBigInteger('paymenter_user_id')->nullable()->index();
            $table->uuid('customer_uuid')->nullable()->index();

            $table->unsignedBigInteger('pterodactyl_user_id')->nullable()->index();
            $table->unsignedBigInteger('pterodactyl_server_id')->nullable()->unique();
            $table->string('server_identifier', 64)->nullable();

            $table->string('desired_state', 32)->default('active');
            $table->string('observed_state', 32)->default('unknown');
            $table->string('lifecycle_state', 32)->default('pending');

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['customer_uuid', 'lifecycle_state']);
            $table->index(['paymenter_user_id', 'lifecycle_state']);
        });

        Schema::create('fragcity_service_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('paymenter_service_id')->index();
            $table->uuid('customer_uuid')->nullable()->index();
            $table->string('event_key', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['paymenter_service_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fragcity_service_lifecycle_events');
        Schema::dropIfExists('fragcity_service_servers');
        Schema::dropIfExists('fragcity_customer_identities');
    }
};
