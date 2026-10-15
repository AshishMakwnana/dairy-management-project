<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PhaseOneMigrationTest extends TestCase
{
    private ?string $migrationDatabasePath = null;

    protected function tearDown(): void
    {
        if ($this->migrationDatabasePath !== null) {
            DB::disconnect('sqlite');
            DB::purge('sqlite');

            if (is_file($this->migrationDatabasePath) && ! unlink($this->migrationDatabasePath)) {
                throw new RuntimeException('Unable to remove the isolated migration test database.');
            }
        }

        parent::tearDown();
    }

    public function test_phase_one_migrations_run_on_a_fresh_isolated_database(): void
    {
        $this->useIsolatedDatabase();

        $this->artisan('migrate:fresh', [
            '--database' => 'sqlite',
            '--force' => true,
        ])->assertExitCode(0);

        $schema = Schema::connection('sqlite');

        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('dairies'));
        $this->assertTrue($schema->hasIndex('users', ['uuid'], 'unique'));
        $this->assertTrue($schema->hasIndex('users', ['mobile_country_code', 'mobile'], 'unique'));
        $this->assertFalse($schema->hasColumn('users', 'role'));
    }

    public function test_phase_one_migrations_roll_back_on_a_fresh_isolated_database(): void
    {
        $this->useIsolatedDatabase();

        $this->artisan('migrate:fresh', [
            '--database' => 'sqlite',
            '--force' => true,
        ])->assertExitCode(0);

        $this->artisan('migrate:rollback', [
            '--database' => 'sqlite',
            '--force' => true,
        ])->assertExitCode(0);

        $schema = Schema::connection('sqlite');

        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('dairies'));
        $this->assertFalse($schema->hasTable('otp_challenges'));
    }

    private function useIsolatedDatabase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dudho-p1-migrations-');

        if ($path === false) {
            throw new RuntimeException('Unable to create the isolated migration test database.');
        }

        $this->migrationDatabasePath = $path;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
        ]);

        DB::purge('sqlite');
    }
}
