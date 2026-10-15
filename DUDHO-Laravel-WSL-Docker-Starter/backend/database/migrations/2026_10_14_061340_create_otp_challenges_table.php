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
        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mobile_country_code', 5)->default('+91');
            $table->string('mobile', 20);
            $table->string('channel', 20);
            $table->string('otp_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamp('last_sent_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['mobile_country_code', 'mobile', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
