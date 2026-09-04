<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class RequestedRestaurantSpecialtiesTest extends TestCase
{
    use DatabaseMigrations;

    public function test_requested_specialties_are_available_in_directory_and_submission_forms(): void
    {
        foreach (['Burger', 'Brunch', 'Grillades'] as $specialty) {
            $this->get('/restaurants')->assertOk()->assertSee($specialty);
            $this->get('/ajouter-un-restaurant')->assertOk()->assertSee($specialty);
        }
    }

    public function test_requested_specialties_are_suggested_before_a_restaurant_is_published(): void
    {
        $this->getJson('/restaurants/recherche/suggestions?q=bru')
            ->assertOk()
            ->assertJsonPath('specialties.0.slug', 'brunch');

        $this->getJson('/restaurants/recherche/suggestions?q=gri')
            ->assertOk()
            ->assertJsonPath('specialties.0.slug', 'grillades');
    }
}
