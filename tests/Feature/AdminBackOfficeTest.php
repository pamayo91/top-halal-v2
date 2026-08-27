<?php

namespace Tests\Feature;

use App\Models\{AdminAuditLog,Article,Comment,Restaurant,RestaurantClaim,RestaurantReview,User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminBackOfficeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_non_admin_is_denied_and_admin_can_see_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/bo')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/bo')->assertOk();
    }

    public function test_admin_login_discards_a_stale_legacy_intended_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->withSession(['url.intended' => 'https://dev.top-halal.fr/admin'])
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/bo');
    }

    public function test_admin_can_create_restaurant_without_exposing_external_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/bo/restaurants', ['name' => 'Test restaurant', 'status' => 'published', 'external_label' => 'Site', 'external_url' => 'https://example.test/private'])
            ->assertRedirect();
        $restaurant = Restaurant::where('name', 'Test restaurant')->firstOrFail();
        $this->assertDatabaseHas('restaurant_outbound_links', ['restaurant_id' => $restaurant->id, 'destination_url' => 'https://example.test/private']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'restaurant.created']);
    }

    public function test_admin_moderation_changes_visibility_status_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $restaurant = $this->restaurant();
        $review = RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'A', 'rating' => 5, 'content' => 'Très bien', 'status' => 'pending']);
        $comment = Comment::create(['article_id' => $this->article()->id, 'author_name' => 'B', 'content' => 'Merci', 'status' => 'pending']);
        $this->actingAs($admin)->patch('/bo/reviews/'.$review->id, ['status' => 'approved'])->assertRedirect();
        $this->actingAs($admin)->patch('/bo/comments/'.$comment->id, ['status' => 'rejected'])->assertRedirect();
        $this->assertSame('approved', $review->fresh()->status); $this->assertNotNull($review->fresh()->approved_at);
        $this->assertSame('rejected', $comment->fresh()->status); $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_admin_can_create_sanitized_article_and_change_user_without_sensitive_data_in_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $user = User::factory()->create();
        $this->actingAs($admin)->post('/bo/content/articles', ['title' => 'Article sûr', 'status' => 'published', 'content_html' => '<script>alert(1)</script><p>Texte</p>'])->assertRedirect();
        $this->assertStringNotContainsString('<script', Article::where('title', 'Article sûr')->firstOrFail()->content_html);
        $this->actingAs($admin)->patch('/bo/users/'.$user->id, ['role' => 'restaurant_owner', 'status' => 'active'])->assertRedirect();
        $this->assertSame('restaurant_owner', $user->fresh()->role);
        $this->assertFalse(collect(AdminAuditLog::latest()->first()->changes)->has('password'));
    }

    private function restaurant(): Restaurant { return Restaurant::create(['legacy_wp_id' => random_int(1, 999999999), 'name' => 'Resto test', 'slug' => 'resto-'.str()->random(8), 'status' => 'published']); }
    private function article(): Article { return Article::create(['legacy_wp_id' => random_int(1, 999999999), 'original_title' => 'Original', 'title' => 'Article', 'slug' => 'article-'.str()->random(8), 'legacy_url' => '/article', 'status' => 'published']); }
}
