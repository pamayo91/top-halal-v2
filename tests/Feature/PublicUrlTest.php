<?php

namespace Tests\Feature;

use App\Models\{Article, Comment, Restaurant, RestaurantReview};
use App\Services\PublicUrl;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PublicUrlTest extends TestCase
{
    use DatabaseMigrations;
    public function test_published_restaurant_uses_environment_route(): void { $r = Restaurant::create(['legacy_wp_id'=>1,'name'=>'Test','slug'=>'test','status'=>'published']); $this->assertSame(url('/resto/test'), app(PublicUrl::class)->for($r)); }
    public function test_non_public_content_has_no_public_url(): void { $a = Article::create(['legacy_wp_id'=>1,'original_title'=>'A','title'=>'A','slug'=>'a','legacy_url'=>'/a','status'=>'draft']); $this->assertNull(app(PublicUrl::class)->for($a)); }
    public function test_review_and_comment_link_to_their_public_parent(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id'=>2,'name'=>'R','slug'=>'r','status'=>'published']);
        $article = Article::create(['legacy_wp_id'=>3,'original_title'=>'A','title'=>'A','slug'=>'a','legacy_url'=>'/a','status'=>'published']);
        $this->assertSame(url('/resto/r').'#avis', app(PublicUrl::class)->for(RestaurantReview::create(['restaurant_id'=>$restaurant->id,'author_name'=>'A','rating'=>5,'content'=>'Bien','status'=>'approved'])));
        $this->assertSame(url('/a'), app(PublicUrl::class)->for(Comment::create(['article_id'=>$article->id,'author_name'=>'A','content'=>'Bien','status'=>'approved'])));
    }
}
