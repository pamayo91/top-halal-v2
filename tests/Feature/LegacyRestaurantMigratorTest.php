<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\LegacyRestaurantMigrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyRestaurantMigratorTest extends TestCase
{
    use DatabaseMigrations;

    private LegacyRestaurantMigrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.legacy_test', [...config('database.connections.sqlite'), 'prefix' => 'wp_']);
        DB::purge('legacy_test');
        $schema = Schema::connection('legacy_test');
        $schema->create('posts', function (Blueprint $table): void {
            $table->unsignedBigInteger('ID')->primary(); $table->unsignedBigInteger('post_author')->default(0); $table->string('post_date_gmt'); $table->string('post_modified_gmt'); $table->string('post_content'); $table->string('post_title'); $table->string('post_status'); $table->string('post_name'); $table->string('post_type'); $table->string('guid')->default(''); $table->text('post_excerpt')->default('');
        });
        $schema->create('postmeta', function (Blueprint $table): void { $table->id('meta_id'); $table->unsignedBigInteger('post_id'); $table->string('meta_key'); $table->text('meta_value'); });
        $schema->create('terms', function (Blueprint $table): void { $table->unsignedBigInteger('term_id')->primary(); $table->string('name'); $table->string('slug'); });
        $schema->create('term_taxonomy', function (Blueprint $table): void { $table->unsignedBigInteger('term_taxonomy_id')->primary(); $table->unsignedBigInteger('term_id'); $table->string('taxonomy'); $table->unsignedBigInteger('parent')->default(0); });
        $schema->create('term_relationships', function (Blueprint $table): void { $table->unsignedBigInteger('object_id'); $table->unsignedBigInteger('term_taxonomy_id'); });
        $this->migrator = new LegacyRestaurantMigrator('legacy_test');
    }

    public function test_it_migrates_a_complete_restaurant_with_multiple_relations_and_utf8(): void
    {
        $this->seedRestaurant(10, 'L’Étoile d’Orient', 'l-etoile-d-orient', 'publish', true, true);
        $record = $this->migrator->inspect(10, 'test_complete');
        $restaurant = $this->migrator->persist($record);

        $this->assertSame('L’Étoile d’Orient', $restaurant->name);
        $this->assertSame('l-etoile-d-orient', $restaurant->slug);
        $this->assertSame('published', $restaurant->status);
        $this->assertTrue($restaurant->is_claimed);
        $this->assertSame('48.8566000', $restaurant->latitude);
        $this->assertSame(2, $restaurant->categories()->count());
        $this->assertSame(2, $restaurant->features()->count());
        $this->assertSame(1, $restaurant->locations()->count());
        $this->assertSame(1, $restaurant->media()->count());
        $this->assertNotEmpty($restaurant->openingHours);
    }

    public function test_it_reports_an_incomplete_restaurant_without_silently_inventing_data(): void
    {
        $this->seedRestaurant(11, 'Sans coordonnées', 'sans-coordonnees', 'pending', false, false);
        $record = $this->migrator->inspect(11);
        $restaurant = $this->migrator->persist($record);

        $this->assertSame('pending', $restaurant->status);
        $this->assertNull($restaurant->latitude);
        $this->assertContains('missing_or_invalid_coordinates', $record['anomalies']);
        $this->assertContains('no_gallery_media', $record['anomalies']);
    }

    public function test_it_does_not_treat_a_time_like_value_in_an_email_field_as_opening_hours(): void
    {
        $this->seedRestaurant(14, 'Valeur legacy hostile', 'valeur-legacy-hostile', 'publish', false, false);
        DB::connection('legacy_test')->table('postmeta')->where('post_id', 14)->where('meta_key', 'lp_listingpro_options')->update(['meta_value' => serialize(['email' => "1 waitfor delay '0:0:15' --"])]);

        $record = $this->migrator->inspect(14);

        $this->assertSame([], $record['target']['hours']);
        $this->assertContains('no_recognized_opening_hours', $record['anomalies']);
    }

    public function test_it_is_idempotent_and_only_reads_the_legacy_connection(): void
    {
        $this->seedRestaurant(12, 'Idempotent', 'idempotent', 'publish', true, false);
        $writes = [];
        DB::connection('legacy_test')->listen(function ($query) use (&$writes): void { if ($query->connectionName === 'legacy_test' && preg_match('/^\s*(insert|update|delete|alter|drop)/i', $query->sql)) $writes[] = $query->sql; });
        $record = $this->migrator->inspect(12);
        $this->assertSame([], $writes);
        $this->migrator->persist($record);
        $this->migrator->persist($this->migrator->inspect(12));

        $this->assertSame(1, Restaurant::where('legacy_wp_id', 12)->count());
        $this->assertSame(2, DB::table('restaurant_category')->count());
    }

    public function test_a_persistence_failure_rolls_back_the_restaurant_and_relations(): void
    {
        $this->seedRestaurant(13, 'Collision', 'collision', 'publish', true, false);
        Restaurant::create(['legacy_wp_id' => 99, 'name' => 'Existing', 'slug' => 'collision', 'status' => 'published']);

        $this->expectException(\Throwable::class);
        try { $this->migrator->persist($this->migrator->inspect(13)); }
        finally {
            $this->assertSame(0, Restaurant::where('legacy_wp_id', 13)->count());
            $this->assertSame(0, DB::table('restaurant_category')->count());
        }
    }

    private function seedRestaurant(int $id, string $title, string $slug, string $status, bool $complete, bool $claimed): void
    {
        $legacy = DB::connection('legacy_test');
        $legacy->table('posts')->insert(['ID' => $id, 'post_author' => 7, 'post_date_gmt' => '2025-01-01 12:00:00', 'post_modified_gmt' => '2025-02-01 12:00:00', 'post_content' => 'Cuisine halal [vc_row]propre[/vc_row]', 'post_title' => $title, 'post_status' => $status, 'post_name' => $slug, 'post_type' => 'listing', 'guid' => '', 'post_excerpt' => '']);
        $options = $complete ? serialize(['address' => '12 rue de l’Opéra', 'phone' => '0102030405', 'email' => 'contact@example.test', 'latitude' => '48.8566', 'longitude' => '2.3522', 'business_hours' => serialize(['monday' => '09:00 - 18:00'])]) : serialize(['address' => 'Incomplète']);
        $meta = [['post_id' => $id, 'meta_key' => 'lp_listingpro_options', 'meta_value' => $options]];
        if ($complete) { $meta[] = ['post_id' => $id, 'meta_key' => 'gallery_image_ids', 'meta_value' => '900']; $legacy->table('posts')->insert(['ID' => 900, 'post_author' => 0, 'post_date_gmt' => '2025-01-01 12:00:00', 'post_modified_gmt' => '2025-01-01 12:00:00', 'post_content' => '', 'post_title' => '', 'post_status' => 'inherit', 'post_name' => '', 'post_type' => 'attachment', 'guid' => 'https://legacy.test/uploads/image.jpg', 'post_excerpt' => 'Photo été']); }
        if ($claimed) $meta[] = ['post_id' => $id, 'meta_key' => 'claimed', 'meta_value' => '1'];
        $legacy->table('postmeta')->insert($meta);
        foreach ([[1, 'Marocain', 'marocain', 'listing-category'], [2, 'Libanais', 'libanais', 'listing-category'], [3, 'Terrasse', 'terrasse', 'features'], [4, 'À emporter', 'a-emporter', 'features'], [5, 'Paris', 'paris', 'location']] as [$termId, $name, $termSlug, $taxonomy]) { $legacy->table('terms')->insert(['term_id' => $termId, 'name' => $name, 'slug' => $termSlug]); $legacy->table('term_taxonomy')->insert(['term_taxonomy_id' => $termId, 'term_id' => $termId, 'taxonomy' => $taxonomy, 'parent' => 0]); $legacy->table('term_relationships')->insert(['object_id' => $id, 'term_taxonomy_id' => $termId]); }
    }
}
