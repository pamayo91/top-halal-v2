<?php

namespace Tests\Feature;

use App\Models\{Restaurant, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CitySeoAdminTableTest extends TestCase
{
    use DatabaseMigrations;

    public function test_city_seo_page_uses_a_paginated_filament_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 26) as $number) {
            Restaurant::create(['legacy_wp_id' => 90000 + $number, 'name' => "Ville $number", 'slug' => "ville-$number", 'status' => 'published', 'city_name' => "Ville $number"]);
        }
        $this->actingAs($admin)->get('/admin/pages-villes-seo')->assertOk()->assertSee('Ville')->assertSee('Nombre de restaurants')->assertSee('État SEO')->assertSee('Modifier')->assertSee('25');
    }
}
