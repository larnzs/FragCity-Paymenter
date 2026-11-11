<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragcity_sso_used_tickets', function (Blueprint $table) {
            $table->char('jti_hash', 64)->charset('ascii')->collation('ascii_bin')->primary();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fragcity_sso_used_tickets');
    }
};
