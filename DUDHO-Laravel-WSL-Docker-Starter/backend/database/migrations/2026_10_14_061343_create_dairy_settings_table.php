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
        Schema::create('dairy_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dairy_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('measurement_unit', 20)->default('litre');
            $table->char('currency', 3)->default('INR');
            $table->string('language', 10)->default('en');
            $table->string('settlement_frequency', 30)->nullable();
            $table->unsignedInteger('settings_version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dairy_settings');
    }
};
