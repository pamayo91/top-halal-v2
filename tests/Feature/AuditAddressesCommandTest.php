<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Restaurant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditAddressesCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_is_read_only_for_v2_and_legacy_data(): void
    {
        config()->set('database.connections.audit_legacy', [...config('database.connections.sqlite'), 'prefix' => '']);
        DB::purge('audit_legacy');
        $legacy = Schema::connection('audit_legacy');
        $legacy->create('posts', function (Blueprint $t): void { $t->unsignedBigInteger('ID')->primary(); $t->string('post_type'); });
        $legacy->create('postmeta', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('post_id'); $t->string('meta_key'); $t->text('meta_value'); });
        $legacy->create('terms', function (Blueprint $t): void { $t->id(); });
        $legacy->create('term_taxonomy', function (Blueprint $t): void { $t->id(); });
        DB::connection('audit_legacy')->table('posts')->insert(['ID' => 77, 'post_type' => 'listing']);
        DB::connection('audit_legacy')->table('postmeta')->insert(['post_id' => 77, 'meta_key' => 'lp_listingpro_options', 'meta_value' => 'x']);
        $restaurant = Restaurant::create(['legacy_wp_id' => 77, 'name' => 'Audit', 'slug' => 'audit', 'status' => 'published', 'address' => '1 rue Test', 'postal_code' => '75001', 'city_name' => 'Paris', 'latitude' => '48.8566000', 'longitude' => '2.3522000']);
        $location = Location::create(['legacy_term_id' => 8, 'name' => 'Paris', 'slug' => 'paris']);
        $restaurant->locations()->attach($location);
        $beforeRestaurant = $restaurant->fresh()->getAttributes(); $beforeLegacy = DB::connection('audit_legacy')->table('postmeta')->get()->map(fn ($x) => (array) $x)->all();
        $out = 'docs/generated/testing-address-audit.md'; $csv = 'docs/generated/testing-address-sample.csv';
        $this->artisan('data:audit-addresses', ['--legacy-connection' => 'audit_legacy', '--out' => $out, '--sample-out' => $csv])->assertSuccessful();
        $this->assertSame($beforeRestaurant, $restaurant->fresh()->getAttributes());
        $this->assertSame($beforeLegacy, DB::connection('audit_legacy')->table('postmeta')->get()->map(fn ($x) => (array) $x)->all());
        $this->assertStringContainsString('TOTAL RESTAURANTS : 1', File::get(base_path($out)));
        File::delete([base_path($out), base_path($csv)]);
    }
}
