<?php
namespace Tests\Feature;
use App\Models\{Restaurant,RestaurantClaim,RestaurantRemovalRequest,User};
use App\Services\RestaurantRemovalModeration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
class RestaurantRemovalRequestTest extends TestCase { use DatabaseMigrations;
 public function test_only_owner_can_request_and_admin_archives_without_deleting():void { $restaurant=Restaurant::create(['legacy_wp_id'=>12,'name'=>'Test','slug'=>'test-'.str()->random(5),'status'=>'published']);$owner=User::factory()->create(['role'=>'restaurant_owner']);RestaurantClaim::create(['restaurant_id'=>$restaurant->id,'user_id'=>$owner->id,'status'=>'approved','submitted_at'=>now()]);$this->actingAs(User::factory()->create())->post(route('owner.restaurants.removal.store',$restaurant),['reason'=>'closed'])->assertForbidden();$this->actingAs($owner)->post(route('owner.restaurants.removal.store',$restaurant),['reason'=>'closed'])->assertRedirect();$this->assertSame('published',$restaurant->fresh()->status);$this->actingAs($owner)->post(route('owner.restaurants.removal.store',$restaurant),['reason'=>'closed'])->assertStatus(409);$request=RestaurantRemovalRequest::firstOrFail();$this->actingAs(User::factory()->create(['role'=>'admin']));app(RestaurantRemovalModeration::class)->approve($request);$this->assertSame('archived',$restaurant->fresh()->status);$this->assertDatabaseHas('restaurants',['id'=>$restaurant->id]); }
 public function test_rejected_removal_keeps_restaurant_published():void { $r=Restaurant::create(['legacy_wp_id'=>13,'name'=>'Test','slug'=>'test-'.str()->random(5),'status'=>'published']);$request=RestaurantRemovalRequest::create(['restaurant_id'=>$r->id,'user_id'=>User::factory()->create()->id,'reason'=>'closed','status'=>'pending','submitted_at'=>now()]);$this->actingAs(User::factory()->create(['role'=>'admin']));app(RestaurantRemovalModeration::class)->reject($request);$this->assertSame('published',$r->fresh()->status); }
}
