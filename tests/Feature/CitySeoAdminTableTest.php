<?php

namespace Tests\Feature;

use App\Models\{CitySeoPage, Restaurant, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
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

        Livewire::actingAs($admin)->test(\App\Filament\Pages\CitySeoPages::class)
            ->set('tableSearch', 'Ville 1')
            ->assertSee('Ville 1');
    }

    public function test_a_city_without_override_opens_an_editable_fallback_form_without_creating_a_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Restaurant::create(['legacy_wp_id' => 99001, 'name' => 'Marseille test', 'slug' => 'marseille-test', 'status' => 'published', 'city_name' => 'Marseille']);
        Restaurant::create(['legacy_wp_id' => 99002, 'name' => 'Lyon test', 'slug' => 'lyon-test', 'status' => 'published', 'city_name' => 'Lyon']);

        $this->actingAs($admin)->get('/admin/pages-villes-seo?city=Marseille')
            ->assertOk()->assertSee('Modification :')->assertSee('Marseille');
        $this->assertDatabaseMissing('city_seo_pages', ['city_name' => 'Marseille']);
        $this->assertDatabaseMissing('city_seo_pages', ['city_name' => 'Lyon']);

        Livewire::actingAs($admin)->test(\App\Filament\Pages\CitySeoPages::class, ['city' => 'Marseille'])
            ->assertSet('data', fn (array $data): bool => array_key_exists('seo_description', $data)
                && array_key_exists('content_top', $data)
                && array_key_exists('content_bottom', $data))
            ->set('data.h1', 'Restaurants halal à Marseille')
            ->call('save');
        $this->assertDatabaseHas('city_seo_pages', ['city_name' => 'Marseille', 'h1' => 'Restaurants halal à Marseille']);
        Livewire::actingAs($admin)->test(\App\Filament\Pages\CitySeoPages::class, ['city' => 'Marseille'])
            ->assertSet('data.h1', 'Restaurants halal à Marseille');
    }
}
