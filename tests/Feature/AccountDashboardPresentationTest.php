<?php

namespace Tests\Feature;

use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AccountDashboardPresentationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_account_presents_existing_authorized_restaurants_with_plain_french_relationships_and_statuses(): void
    {
        $user = User::factory()->create(['name' => 'Amina Martin']);
        $owner = $this->restaurant('Le Jasmin', 'published');
        $historical = $this->restaurant('Le Safran', 'archived');
        $deposited = $this->restaurant('Le Cèdre', 'pending');
        $formerDeposit = $this->restaurant('Ancienne fiche', 'published');

        RestaurantClaim::create(['restaurant_id' => $owner->id, 'user_id' => $user->id, 'status' => 'approved', 'submitted_at' => now()]);
        LegacyRestaurantAuthorship::create(['restaurant_id' => $historical->id, 'user_id' => $user->id, 'legacy_wp_id' => 91002, 'legacy_wp_user_id' => 71002, 'source_post_status' => 'publish']);
        RestaurantSubmission::create(['restaurant_id' => $deposited->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'customer', 'status' => 'pending_admin_review', 'submitted_at' => now()]);

        $formerDepositor = User::factory()->create();
        RestaurantSubmission::create(['restaurant_id' => $formerDeposit->id, 'user_id' => $formerDepositor->id, 'submitter_email' => $formerDepositor->email, 'submitter_role' => 'customer', 'status' => 'published', 'submitted_at' => now()]);
        RestaurantClaim::create(['restaurant_id' => $formerDeposit->id, 'user_id' => User::factory()->create()->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSeeText('Bonjour Amina Martin')
            ->assertSeeText('Restaurateur')
            ->assertSeeText('Le Jasmin')
            ->assertSeeText('Le Safran')
            ->assertSeeText('Le Cèdre')
            ->assertSeeText('Publié')
            ->assertSeeText('Archivé')
            ->assertSeeText('En attente')
            ->assertSeeText('Déposant')
            ->assertSeeText('Modifier la fiche')
            ->assertSeeText('Voir la fiche')
            ->assertDontSeeText('Ancienne fiche')
            ->assertDontSeeText('restaurant_owner');
    }

    public function test_account_empty_state_keeps_existing_add_and_security_actions_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSeeText('Vous n’avez aucun restaurant à gérer pour le moment.')
            ->assertSeeText('Ajouter un restaurant')
            ->assertSeeText('Changer le mot de passe')
            ->assertSeeText('Déconnexion');
    }

    private function restaurant(string $name, string $status): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => random_int(100000, 999999),
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.str()->random(8)),
            'city_name' => 'Paris',
            'status' => $status,
        ]);
    }
}
