<?php

namespace Tests\Feature;

use App\Models\{Category, CitySpecialtySeoPage, Feature, Restaurant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GeographicBreadcrumbAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_read_only_audit_covers_published_restaurants_cities_and_open_facets_without_findings(): void
    {
        $category = Category::create(['legacy_term_id' => 1, 'name' => 'Burger', 'slug' => 'burger']);
        $feature = Feature::create(['legacy_term_id' => 2, 'name' => 'Terrasse', 'slug' => 'terrasse']);
        $north = $this->published('Saint-Denis 93', 'saint-denis-93-test', 'Saint-Denis', '93066');
        $this->published('Saint-Denis Réunion', 'saint-denis-974-test', 'Saint-Denis', '97411');
        $this->published('Indre commune', 'indre-commune-test', 'Indre', '44074');
        $this->published('Châteauroux', 'chateauroux-test', 'Châteauroux', '36044');
        $north->categories()->attach($category);
        $north->features()->attach($feature);
        CitySpecialtySeoPage::create(['city_code' => '93066', 'category_id' => $category->id, 'state' => 'open']);
        \App\Models\CityServiceSeoPage::create(['city_code' => '93066', 'feature_id' => $feature->id, 'state' => 'open']);

        $this->assertSame(0, Artisan::call('geography:audit-breadcrumbs', ['--json' => true]));
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['read_only']);
        $this->assertSame([], $payload['errors']);
        $this->assertSame(4, $payload['counts']['restaurants']);
        $this->assertSame(1, $payload['counts']['specialty_facets']);
        $this->assertSame(1, $payload['counts']['service_facets']);
    }

    private function published(string $name, string $slug, string $city, string $cityCode): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => 80000 + Restaurant::count(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'published',
            'city_name' => $city,
            'city_code' => $cityCode,
            'country_code' => 'FR',
        ]);
    }
}
