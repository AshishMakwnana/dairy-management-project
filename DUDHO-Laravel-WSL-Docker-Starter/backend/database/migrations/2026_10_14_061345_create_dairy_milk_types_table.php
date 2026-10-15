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
        Schema::create('dairy_milk_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dairy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milk_type_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['dairy_id', 'milk_type_id']);
            $table->index(['dairy_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dairy_milk_types');
    }
};
