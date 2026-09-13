<?php

namespace Tests\Feature;

use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LegacyRestaurantAuthorshipManagementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_exact_historical_authorship_grants_management_without_creating_a_claim_or_changing_the_relation(): void
    {
        $historicalManager = User::factory()->create(['legacy_wp_user_id' => 401, 'role' => 'user']);
        $historicalUserWithoutRestaurant = User::factory()->create(['legacy_wp_user_id' => 402, 'role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $restaurant = $this->restaurant(701);
        $authorship = LegacyRestaurantAuthorship::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $historicalManager->id,
            'legacy_wp_id' => 701,
            'legacy_wp_user_id' => 401,
            'source_post_status' => 'publish',
        ]);

        $claimsBefore = RestaurantClaim::count();
        $authorshipsBefore = LegacyRestaurantAuthorship::count();

        $this->assertTrue($historicalManager->can('manage', $restaurant));
        $this->assertFalse($historicalUserWithoutRestaurant->can('manage', $restaurant));
        $this->assertFalse($otherUser->can('manage', $restaurant));
        $this->assertTrue($admin->can('manage', $restaurant));
        $this->assertFalse($restaurant->isClaimable());

        $this->actingAs($historicalManager)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee($restaurant->name);
        $this->actingAs($historicalManager)
            ->get(route('owner.restaurants.edit', $restaurant))
            ->assertOk();
        $this->actingAs($historicalManager)
            ->put(route('owner.restaurants.update', $restaurant), ['name' => 'Restaurant historique modifié'])
            ->assertRedirect();
        $this->assertSame('Restaurant historique modifié', $restaurant->fresh()->name);

        $this->actingAs($historicalUserWithoutRestaurant)
            ->get(route('owner.restaurants.edit', $restaurant))
            ->assertRedirect(route('owner.restaurants.management-unavailable', $restaurant));
        $this->actingAs($otherUser)
            ->get(route('owner.restaurants.edit', $restaurant))
            ->assertRedirect(route('owner.restaurants.management-unavailable', $restaurant));
        $this->actingAs($admin)
            ->get(route('owner.restaurants.edit', $restaurant))
            ->assertOk();
        $this->get(route('claims.create', $restaurant))->assertStatus(409);

        $this->assertSame($claimsBefore, RestaurantClaim::count());
        $this->assertSame($authorshipsBefore, LegacyRestaurantAuthorship::count());
        $this->assertDatabaseHas('legacy_restaurant_authorships', ['id' => $authorship->id, 'user_id' => $historicalManager->id, 'restaurant_id' => $restaurant->id]);
    }

    private function restaurant(int $legacyId): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => $legacyId,
            'name' => 'Restaurant historique',
            'slug' => 'restaurant-historique-'.$legacyId,
            'status' => 'published',
        ]);
    }
}
