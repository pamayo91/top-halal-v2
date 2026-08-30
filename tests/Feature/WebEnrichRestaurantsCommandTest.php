<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantOpeningHour;
use App\Models\RestaurantWebEnrichment;
use App\Services\WebEnrichment\RestaurantWebSourceProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class WebEnrichRestaurantsCommandTest extends TestCase
{
    use DatabaseMigrations;

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::create($attributes + ['legacy_wp_id'=>random_int(1, 999999999), 'name'=>'Restaurant Test', 'slug'=>'restaurant-'.str()->random(10), 'status'=>'published', 'address'=>'10 rue du Test', 'address_line1'=>'10 rue du Test', 'postal_code'=>'75001', 'city_name'=>'Paris']);
    }

    private function source(array $result): void
    {
        $this->app->instance(RestaurantWebSourceProvider::class, new class($result) implements RestaurantWebSourceProvider { public function __construct(private array $result) {} public function find(Restaurant $restaurant): array { return $this->result; } });
    }

    public function test_it_adds_only_missing_hours_and_an_eligible_description_then_skips_the_same_restaurant(): void
    {
        $restaurant=$this->restaurant(['description'=>'  KEBAB FRITES DE ancien texte']);
        $this->source(['state'=>'matched','sources'=>['https://internal-source.test/a'],'confidence'=>94,'hours_source'=>'https://internal-source.test/a','description_sources'=>['https://internal-source.test/a'],'hours'=>[['day'=>'monday','opens_at'=>'11:30:00','closes_at'=>'23:30:00','is_closed'=>false],['day'=>'monday','opens_at'=>'00:00:00','closes_at'=>'01:00:00','is_closed'=>false]],'description'=>'Restaurant situé à Paris. La vente à emporter et le service sur place sont proposés.']);
        $this->artisan('restaurants:web-enrich',['--limit'=>50,'--out'=>'docs/generated/test-web-enrichment'])->assertSuccessful();
        $this->assertSame('UPDATED',RestaurantWebEnrichment::first()->status); $this->assertCount(2,$restaurant->fresh()->openingHours); $this->assertSame('Restaurant situé à Paris. La vente à emporter et le service sur place sont proposés.',$restaurant->fresh()->description);
        $this->artisan('restaurants:web-enrich',['--limit'=>50,'--out'=>'docs/generated/test-web-enrichment'])->assertSuccessful();
        $this->assertSame(1,RestaurantWebEnrichment::count()); $this->assertCount(2,$restaurant->fresh()->openingHours);
    }

    public function test_it_never_overwrites_existing_hours_and_routes_closures_to_review_without_mutation(): void
    {
        $restaurant=$this->restaurant(['description'=>'Bonne description existante.']); RestaurantOpeningHour::create(['restaurant_id'=>$restaurant->id,'day'=>'monday','opens_at'=>'09:00:00','closes_at'=>'18:00:00','is_closed'=>false,'legacy_key'=>'manual']);
        $this->source(['state'=>'matched','sources'=>['official:1'],'closure'=>'confirmed','closure_sources'=>['official:1'],'hours'=>[['day'=>'monday','opens_at'=>'01:00:00','closes_at'=>'02:00:00','is_closed'=>false]],'description'=>'Restaurant situé à Paris. Service sur place proposé.']);
        $this->artisan('restaurants:web-enrich',['--limit'=>50,'--out'=>'docs/generated/test-web-enrichment'])->assertSuccessful();
        $this->assertSame('CLOSED_CONFIRMED_REVIEW',RestaurantWebEnrichment::first()->status); $this->assertSame('Bonne description existante.',$restaurant->fresh()->description); $this->assertCount(1,$restaurant->fresh()->openingHours);
    }

    public function test_dry_run_and_high_non_contiguous_id_do_not_persist_a_checkpoint(): void
    {
        $restaurant=$this->restaurant(['id'=>9000]); $this->source(['state'=>'unmatched','sources'=>[],'reason'=>'not found']);
        $this->artisan('restaurants:web-enrich',['--limit'=>1,'--dry-run'=>true,'--out'=>'docs/generated/test-web-enrichment'])->assertSuccessful();
        $this->assertDatabaseMissing('restaurant_web_enrichments',['restaurant_id'=>9000]);
    }

    public function test_retry_errors_reclaims_a_stale_error_only(): void
    {
        $restaurant=$this->restaurant(); RestaurantWebEnrichment::create(['restaurant_id'=>$restaurant->id,'legacy_wp_id'=>$restaurant->legacy_wp_id,'status'=>'ERROR','technical_error'=>'timeout']); $this->source(['state'=>'unmatched','sources'=>[],'reason'=>'not found']);
        $this->artisan('restaurants:web-enrich',['--retry-errors'=>true,'--limit'=>1,'--out'=>'docs/generated/test-web-enrichment'])->assertSuccessful();
        $this->assertSame('INSUFFICIENT_DATA',RestaurantWebEnrichment::first()->status);
    }
}
