<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragcity_client_api_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->unique()->constrained()->cascadeOnDelete();
            $table->text('encrypted_token');
            $table->char('token_fingerprint', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fragcity_client_api_credentials');
    }
};
