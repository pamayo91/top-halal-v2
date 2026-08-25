<?php

namespace Tests\Feature;

use App\Models\{Restaurant, RestaurantReview};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class RestaurantReviewTest extends TestCase
{
    use DatabaseMigrations;

    private Restaurant $restaurant;
    protected function setUp(): void { parent::setUp(); $this->restaurant = Restaurant::create(['legacy_wp_id' => 13567, 'name' => 'Étoile', 'slug' => 'etoile', 'status' => 'published']); }
    public function test_aggregate_only_counts_approved_reviews(): void
    {
        foreach ([[5,'approved'],[3,'approved'],[1,'pending'],[1,'spam'],[1,'rejected']] as [$rating,$status]) RestaurantReview::create(['restaurant_id'=>$this->restaurant->id,'author_name'=>'Élodie','rating'=>$rating,'content'=>'Très bon','status'=>$status]);
        $this->assertSame(['count'=>2,'average'=>4.0], $this->restaurant->approvedReviewAggregate());
    }
    public function test_new_review_is_pending_and_urls_and_invalid_rating_are_rejected(): void
    {
        $url = '/_preview/restaurant/13567/reviews';
        $this->post($url,['name'=>'Élodie','email'=>'e@example.test','rating'=>5,'content'=>'Très bon'])->assertRedirect(); $this->assertSame('pending',RestaurantReview::first()->status);
        $this->post($url,['name'=>'Élodie','email'=>'e@example.test','rating'=>6,'content'=>'Très bon'])->assertSessionHasErrors('rating');
        $this->post($url,['name'=>'Élodie','email'=>'e@example.test','rating'=>5,'content'=>'https://example.test'])->assertSessionHasErrors('content');
    }
}
