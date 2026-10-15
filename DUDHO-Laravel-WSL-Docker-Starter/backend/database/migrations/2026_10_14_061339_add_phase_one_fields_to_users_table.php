<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('mobile_country_code', 5)->default('+91')->after('uuid');
            $table->string('mobile', 20)->nullable()->after('mobile_country_code');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            $table->string('preferred_language', 10)->default('en')->after('email_verified_at');
            $table->string('status', 20)->default('active')->after('preferred_language');
            $table->timestamp('last_login_at')->nullable()->after('status');

            $table->string('name', 150)->nullable()->change();
            $table->string('email', 191)->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        DB::table('users')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique(['mobile_country_code', 'mobile']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', ['mobile_country_code', 'mobile'], 'unique')) {
                $table->dropUnique(['mobile_country_code', 'mobile']);
            }

            if (Schema::hasIndex('users', ['uuid'], 'unique')) {
                $table->dropUnique(['uuid']);
            }

            $table->dropColumn([
                'uuid',
                'mobile_country_code',
                'mobile',
                'mobile_verified_at',
                'preferred_language',
                'status',
                'last_login_at',
            ]);
        });
    }
};
