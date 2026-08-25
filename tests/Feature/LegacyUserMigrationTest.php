<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyUserMigrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.legacy_wp', [...config('database.connections.sqlite'), 'prefix' => '']);
        DB::purge('legacy_wp');
        Schema::connection('legacy_wp')->create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('ID')->primary();
            $table->string('display_name');
            $table->string('user_email');
            $table->string('user_registered');
            $table->string('user_pass');
        });
        Schema::connection('legacy_wp')->table('users')->insert([
            ['ID' => 17, 'display_name' => 'Élodie', 'user_email' => 'elodie@example.test', 'user_registered' => '2020-01-02 03:04:05', 'user_pass' => 'legacy-hash-never-migrated'],
            ['ID' => 18, 'display_name' => 'Sans e-mail', 'user_email' => '', 'user_registered' => '2020-01-02 03:04:05', 'user_pass' => 'legacy-hash-never-migrated'],
        ]);
    }

    public function test_dry_run_and_apply_are_idempotent_and_never_write_legacy(): void
    {
        $writes = [];
        DB::connection('legacy_wp')->listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|drop)/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->artisan('legacy:migrate-users', ['--ids' => '17,18', '--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('users', 0);

        putenv('LEGACY_MIGRATION_TEMP_PASSWORD=test-temporary-password');
        $this->artisan('legacy:migrate-users', ['--ids' => '17,18', '--apply' => true])->assertExitCode(0);
        $this->artisan('legacy:migrate-users', ['--ids' => '17,18', '--apply' => true])->assertExitCode(0);
        $user = User::where('legacy_wp_user_id', 17)->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('test-temporary-password', $user->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertSame([], $writes);
        putenv('LEGACY_MIGRATION_TEMP_PASSWORD');
    }
}
