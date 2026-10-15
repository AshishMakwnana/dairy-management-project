<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otp_login_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('response_payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_login_requests');
    }
};
