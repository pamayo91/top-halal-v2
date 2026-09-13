<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantReview;
use App\Models\RestaurantSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LegacyContributorLoginMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_disables_only_unactivated_legacy_identities_linked_only_to_contributions(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 99001, 'name' => 'Avis migration', 'slug' => 'avis-migration', 'status' => 'published']);
        $article = Article::create(['legacy_wp_id' => 99002, 'original_title' => 'Commentaire migration', 'title' => 'Commentaire migration', 'slug' => 'commentaire-migration', 'legacy_url' => '/commentaire-migration/', 'status' => 'published']);
        $reviewAuthor = $this->legacyUser(101);
        $commentAuthor = $this->legacyUser(102);
        $verifiedUser = $this->legacyUser(103, ['email_verified_at' => now()]);
        $v2Contributor = User::factory()->create(['login_enabled' => true, 'role' => 'user', 'email_verified_at' => null, 'must_change_password' => true]);
        $claimant = $this->legacyUser(104);
        $depositor = $this->legacyUser(105);
        $historicalManager = $this->legacyUser(106);
        $admin = $this->legacyUser(107, ['role' => 'admin']);

        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'legacy_user_id' => 101, 'author_name' => 'Avis', 'author_email' => $reviewAuthor->email, 'rating' => 5, 'content' => 'Avis historique', 'status' => 'approved']);
        Comment::create(['article_id' => $article->id, 'legacy_user_id' => null, 'author_name' => 'Commentaire', 'author_email' => $commentAuthor->email, 'content' => 'Commentaire historique', 'status' => 'approved']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'V2', 'author_email' => $v2Contributor->email, 'rating' => 5, 'content' => 'Avis V2', 'status' => 'approved']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'legacy_user_id' => 103, 'author_name' => 'Vérifié', 'author_email' => $verifiedUser->email, 'rating' => 5, 'content' => 'Avis vérifié', 'status' => 'approved']);
        RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $claimant->id, 'status' => 'approved', 'submitted_at' => now()]);
        RestaurantSubmission::create(['restaurant_id' => $restaurant->id, 'user_id' => $depositor->id, 'submitter_email' => $depositor->email, 'submitter_role' => 'customer', 'status' => 'published', 'submitted_at' => now()]);
        LegacyRestaurantAuthorship::create(['restaurant_id' => $restaurant->id, 'user_id' => $historicalManager->id, 'legacy_wp_id' => 99001, 'legacy_wp_user_id' => 106, 'source_post_status' => 'publish']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'legacy_user_id' => 104, 'author_name' => 'Claim', 'author_email' => $claimant->email, 'rating' => 5, 'content' => 'Avis claim', 'status' => 'approved']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'legacy_user_id' => 105, 'author_name' => 'Dépôt', 'author_email' => $depositor->email, 'rating' => 5, 'content' => 'Avis dépôt', 'status' => 'approved']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'legacy_user_id' => 106, 'author_name' => 'Historique', 'author_email' => $historicalManager->email, 'rating' => 5, 'content' => 'Avis historique', 'status' => 'approved']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'legacy_user_id' => 107, 'author_name' => 'Admin', 'author_email' => $admin->email, 'rating' => 5, 'content' => 'Avis admin', 'status' => 'approved']);

        $migration = require base_path('database/migrations/2026_09_13_000200_disable_legacy_contributor_logins.php');
        $migration->up();

        $this->assertFalse($reviewAuthor->fresh()->login_enabled);
        $this->assertFalse($commentAuthor->fresh()->login_enabled);
        $this->assertSame('Désactivée', \App\Filament\Resources\UserResource::connectionLabel($reviewAuthor->fresh()));
        $this->assertSame('Désactivée', \App\Filament\Resources\UserResource::connectionLabel($commentAuthor->fresh()));
        $this->assertTrue($verifiedUser->fresh()->login_enabled);
        $this->assertTrue($v2Contributor->fresh()->login_enabled);
        $this->assertTrue($claimant->fresh()->login_enabled);
        $this->assertTrue($depositor->fresh()->login_enabled);
        $this->assertTrue($historicalManager->fresh()->login_enabled);
        $this->assertTrue($admin->fresh()->login_enabled);

        $migration->up();

        $this->assertFalse($reviewAuthor->fresh()->login_enabled);
        $this->assertFalse($commentAuthor->fresh()->login_enabled);
        $this->assertSame(1, RestaurantClaim::count());
        $this->assertSame(1, RestaurantSubmission::count());
        $this->assertSame(1, LegacyRestaurantAuthorship::count());
    }

    private function legacyUser(int $legacyId, array $attributes = []): User
    {
        return User::factory()->create([
            'legacy_wp_user_id' => $legacyId,
            'role' => 'user',
            'login_enabled' => true,
            'email_verified_at' => null,
            'must_change_password' => true,
            ...$attributes,
        ]);
    }
}
