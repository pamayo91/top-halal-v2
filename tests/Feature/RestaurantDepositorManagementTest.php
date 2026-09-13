<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Services\ClaimModeration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RestaurantDepositorManagementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_an_approved_claim_transfers_management_from_the_depositor_without_deleting_history(): void
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 12001,
            'name' => 'Restaurant du déposant',
            'slug' => 'restaurant-deposant-'.str()->random(8),
            'status' => 'published',
        ]);
        $depositor = User::factory()->create(['role' => 'restaurant_owner']);
        $manager = User::factory()->create(['role' => 'restaurant_owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $submission = RestaurantSubmission::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $depositor->id,
            'submitter_email' => $depositor->email,
            'submitter_role' => 'customer',
            'status' => 'published',
            'submitted_at' => now(),
        ]);

        $this->assertTrue($depositor->can('manage', $restaurant));
        $this->actingAs($depositor)->get(route('account.dashboard'))->assertOk()->assertSee($restaurant->name);
        $this->actingAs($depositor)->get(route('owner.restaurants.edit', $restaurant))->assertOk();

        $claim = RestaurantClaim::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $manager->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->assertTrue($depositor->can('manage', $restaurant));
        $this->actingAs($depositor)->get(route('account.dashboard'))->assertOk()->assertSee($restaurant->name);

        $claim->update(['status' => 'rejected']);
        $this->assertTrue($depositor->can('manage', $restaurant));
        $this->actingAs($depositor)->get(route('account.dashboard'))->assertOk()->assertSee($restaurant->name);

        Notification::fake();
        $this->actingAs($admin);
        app(ClaimModeration::class)->approve($claim->fresh());

        $this->assertFalse($depositor->can('manage', $restaurant));
        $this->assertTrue($manager->can('manage', $restaurant));
        $this->assertTrue($admin->can('manage', $restaurant));
        $this->actingAs($depositor)->get(route('account.dashboard'))->assertOk()->assertDontSee($restaurant->name);
        $this->actingAs($depositor)->get(route('owner.restaurants.edit', $restaurant))->assertForbidden();
        $this->actingAs($manager)->get(route('account.dashboard'))->assertOk()->assertSee($restaurant->name);
        $this->actingAs($manager)->get(route('owner.restaurants.edit', $restaurant))->assertOk();
        $this->actingAs($admin)->get(route('owner.restaurants.edit', $restaurant))->assertOk();

        $this->assertDatabaseHas('restaurant_submissions', ['id' => $submission->id, 'restaurant_id' => $restaurant->id, 'user_id' => $depositor->id]);
        $this->assertDatabaseHas('users', ['id' => $depositor->id]);
        $this->assertDatabaseHas('restaurant_claims', ['id' => $claim->id, 'restaurant_id' => $restaurant->id, 'user_id' => $manager->id, 'status' => 'approved']);
        $this->assertDatabaseMissing('restaurant_claims', ['restaurant_id' => $restaurant->id, 'user_id' => $depositor->id]);
    }
}
