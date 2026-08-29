<?php

namespace Tests\Feature;

use App\Models\{Article, Restaurant};
use App\Services\PublicUrl;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PublicUrlTest extends TestCase
{
    use DatabaseMigrations;
    public function test_published_restaurant_uses_environment_route(): void { $r = Restaurant::create(['legacy_wp_id'=>1,'name'=>'Test','slug'=>'test','status'=>'published']); $this->assertSame(url('/resto/test'), app(PublicUrl::class)->for($r)); }
    public function test_non_public_content_has_no_public_url(): void { $a = Article::create(['legacy_wp_id'=>1,'original_title'=>'A','title'=>'A','slug'=>'a','legacy_url'=>'/a','status'=>'draft']); $this->assertNull(app(PublicUrl::class)->for($a)); }
}
