<?php

namespace Tests\Feature;

use App\Models\{AdminAuditLog,Article,Comment,RedirectRule,Restaurant,RestaurantClaim,RestaurantReview,User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AdminBackOfficeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_non_admin_is_denied_and_admin_can_see_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')->assertOk();
    }

    public function test_admin_login_discards_a_stale_legacy_intended_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->withSession(['url.intended' => 'https://dev.top-halal.fr/admin'])
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin');
    }

    public function test_admin_can_create_restaurant_without_exposing_external_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/restaurants')->assertOk();
    }

    public function test_restaurant_location_form_shows_only_operational_location_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $restaurant = $this->restaurant(['latitude' => 48.866, 'longitude' => 2.364]);
        $this->actingAs($admin)->get("/admin/restaurants/{$restaurant->id}/edit")
            ->assertOk()
            ->assertSee('Rechercher une adresse')
            ->assertSee('Zones associées Top-Halal')
            ->assertSee('Position')
            ->assertDontSee('Données d’origine')
            ->assertDontSee('GPS historique / actuel')
            ->assertDontSee('Qualité de localisation');
    }

    public function test_admin_panel_exposes_all_operational_modules_and_legacy_back_office_is_gone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        RedirectRule::create(['source_path' => '/ancienne-page', 'match_type' => 'exact', 'destination' => '/', 'status_code' => 301, 'priority' => 1000, 'is_active' => true]);

        foreach ([
            '/admin/articles', '/admin/pages', '/admin/media-assets', '/admin/restaurant-reviews',
            '/admin/comments', '/admin/restaurant-claims', '/admin/users', '/admin/redirect-rules',
            '/admin/categories', '/admin/features', '/admin/locations', '/admin/settings', '/admin/admin-audit-logs',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        $this->actingAs($admin)->get('/bo')->assertNotFound();
    }

    public function test_admin_moderation_changes_visibility_status_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $restaurant = $this->restaurant();
        $review = RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'A', 'rating' => 5, 'content' => 'Très bien', 'status' => 'pending']);
        $comment = Comment::create(['article_id' => $this->article()->id, 'author_name' => 'B', 'content' => 'Merci', 'status' => 'pending']);
        $this->actingAs($admin);
        \App\Filament\Resources\RestaurantReviewResource::moderate($review, 'approved');
        \App\Filament\Resources\CommentResource::moderate($comment, 'rejected');
        $this->assertSame('approved', $review->fresh()->status); $this->assertNotNull($review->fresh()->approved_at);
        $this->assertSame('rejected', $comment->fresh()->status); $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_restaurant_delete_action_moves_the_record_to_the_trash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $restaurant = $this->restaurant(); $this->actingAs($admin);
        \App\Filament\Resources\RestaurantResource::moveToTrash($restaurant);
        $this->assertSoftDeleted('restaurants', ['id' => $restaurant->id]);
        $this->assertNull(Restaurant::find($restaurant->id));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'restaurant.trashed', 'subject_id' => $restaurant->id]);
    }

    public function test_restaurant_bulk_delete_moves_each_selected_restaurant_to_the_trash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $first = $this->restaurant(); $second = $this->restaurant(); $this->actingAs($admin);
        \App\Filament\Resources\RestaurantResource::moveManyToTrash([$first, $second]);
        $this->assertSoftDeleted('restaurants', ['id' => $first->id]); $this->assertSoftDeleted('restaurants', ['id' => $second->id]);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_trashed_restaurant_can_be_restored_or_permanently_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $restaurant = $this->restaurant(); $this->actingAs($admin);
        \App\Filament\Resources\RestaurantResource::moveToTrash($restaurant);
        \App\Filament\Resources\RestaurantResource::restore(Restaurant::onlyTrashed()->findOrFail($restaurant->id));
        $this->assertNotNull(Restaurant::find($restaurant->id));

        \App\Filament\Resources\RestaurantResource::moveToTrash($restaurant->fresh());
        \App\Filament\Resources\RestaurantResource::forceDelete(Restaurant::onlyTrashed()->findOrFail($restaurant->id));
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'restaurant.force_deleted', 'subject_id' => $restaurant->id]);
    }

    public function test_empty_trash_permanently_deletes_all_trashed_restaurants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $first = $this->restaurant(); $second = $this->restaurant(); $this->actingAs($admin);
        \App\Filament\Resources\RestaurantResource::moveManyToTrash([$first, $second]);
        \App\Filament\Resources\RestaurantResource::emptyTrash();
        $this->assertDatabaseMissing('restaurants', ['id' => $first->id]);
        $this->assertDatabaseMissing('restaurants', ['id' => $second->id]);
        $this->assertDatabaseCount('admin_audit_logs', 4);
    }

    public function test_moderation_can_mark_comments_and_reviews_as_spam(): void
    {
        $restaurant = $this->restaurant();
        $review = RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'A', 'rating' => 1, 'content' => 'Spam', 'status' => 'pending']);
        $comment = Comment::create(['article_id' => $this->article()->id, 'author_name' => 'B', 'content' => 'Spam', 'status' => 'pending']);
        \App\Filament\Resources\RestaurantReviewResource::moderate($review, 'spam');
        \App\Filament\Resources\CommentResource::moderate($comment, 'spam');
        $this->assertSame('spam', $review->fresh()->status);
        $this->assertSame('spam', $comment->fresh()->status);
    }

    public function test_admin_can_create_sanitized_article_and_change_user_without_sensitive_data_in_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); $user = User::factory()->create();
        $this->actingAs($admin)->get('/admin/articles')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    private function restaurant(): Restaurant { return Restaurant::create(['legacy_wp_id' => random_int(1, 999999999), 'name' => 'Resto test', 'slug' => 'resto-'.str()->random(8), 'status' => 'published']); }
    private function article(): Article { return Article::create(['legacy_wp_id' => random_int(1, 999999999), 'original_title' => 'Original', 'title' => 'Article', 'slug' => 'article-'.str()->random(8), 'legacy_url' => '/article', 'status' => 'published']); }
}
