<?php

namespace Tests\Feature;

use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyRestaurantAuthorshipImportCommandTest extends TestCase
{
    use DatabaseMigrations;

    private string $legacyDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legacyDatabase = storage_path('framework/testing/legacy-authorships.sqlite');
        File::ensureDirectoryExists(dirname($this->legacyDatabase));
        File::put($this->legacyDatabase, '');
        config(['database.connections.legacy_wp' => ['driver' => 'sqlite', 'database' => $this->legacyDatabase, 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('legacy_wp');
        Schema::connection('legacy_wp')->create('users', function ($table): void { $table->unsignedBigInteger('ID')->primary(); });
        Schema::connection('legacy_wp')->create('posts', function ($table): void { $table->unsignedBigInteger('ID')->primary(); $table->unsignedBigInteger('post_author'); $table->string('post_status'); $table->string('post_type'); });
    }

    protected function tearDown(): void
    {
        DB::purge('legacy_wp');
        File::delete($this->legacyDatabase);
        parent::tearDown();
    }

    public function test_dry_run_excludes_unpublished_and_missing_restaurants_without_writing_a_relationship(): void
    {
        $user = User::factory()->create(['legacy_wp_user_id' => 400]);
        $restaurant = $this->restaurant(700);
        $this->legacyUser(400);
        $this->legacyPost(700, 400, 'publish');
        $this->legacyPost(701, 400, 'pending');
        $this->legacyPost(702, 400, 'publish');

        $this->artisan('legacy:import-restaurant-authorships', ['--out' => 'storage/framework/testing/authorship-report'])
            ->expectsOutputToContain('Lecture seule')
            ->assertExitCode(0);

        $this->assertDatabaseCount('legacy_restaurant_authorships', 0);
        $this->assertSame($user->id, User::where('legacy_wp_user_id', 400)->value('id'));
        $this->assertSame($restaurant->id, Restaurant::where('legacy_wp_id', 700)->value('id'));
    }

    public function test_apply_creates_only_the_exact_author_relationship_with_a_rollback_batch(): void
    {
        $user = User::factory()->create(['legacy_wp_user_id' => 401]);
        $restaurant = $this->restaurant(703);
        $this->legacyUser(401);
        $this->legacyPost(703, 401, 'publish');
        $batch = '2d1c97b0-7d64-4d91-a430-3c7d96b2f2d6';

        $this->artisan('legacy:import-restaurant-authorships', ['--apply' => true, '--batch' => $batch, '--out' => 'storage/framework/testing/authorship-report'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('legacy_restaurant_authorships', ['user_id' => $user->id, 'restaurant_id' => $restaurant->id, 'legacy_wp_id' => 703, 'legacy_wp_user_id' => 401, 'import_batch' => $batch]);
        $this->assertDatabaseCount('restaurant_claims', 0);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame(1, LegacyRestaurantAuthorship::where('import_batch', $batch)->count());
    }

    private function legacyUser(int $id): void { DB::connection('legacy_wp')->table('users')->insert(['ID' => $id]); }
    private function legacyPost(int $id, int $author, string $status): void { DB::connection('legacy_wp')->table('posts')->insert(['ID' => $id, 'post_author' => $author, 'post_status' => $status, 'post_type' => 'listing']); }
    private function restaurant(int $legacyId): Restaurant { return Restaurant::create(['legacy_wp_id' => $legacyId, 'name' => 'Restaurant '.$legacyId, 'slug' => 'restaurant-'.$legacyId, 'status' => 'published']); }
}
