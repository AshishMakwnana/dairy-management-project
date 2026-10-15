<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasIndex('users', ['uuid'], 'unique')) {
            return;
        }

        $hasDuplicateUuids = DB::table('users')
            ->select('uuid')
            ->whereNotNull('uuid')
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateUuids) {
            throw new RuntimeException(
                'Cannot add the unique users.uuid index because duplicate UUID values exist. Resolve duplicates before retrying this migration.',
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('users', ['uuid'], 'unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
            });
        }
    }
};
