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
        Schema::create('dairy_quality_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dairy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_parameter_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('usage', 30)->default('record_only');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dairy_id', 'quality_parameter_id']);
            $table->index(['dairy_id', 'is_enabled', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dairy_quality_parameters');
    }
};
