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
        Schema::create('dairies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name', 191);
            $table->string('legal_name', 191)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('village_area', 150)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('district', 150)->nullable();
            $table->string('state', 150)->nullable();
            $table->string('country', 100)->default('India');
            $table->char('country_code', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->uuid('creation_request_uuid')->nullable();
            $table->char('creation_request_hash', 64)->nullable();
            $table->timestamps();

            $table->index('created_by_user_id');
            $table->unique(['created_by_user_id', 'creation_request_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dairies');
    }
};
