<?php

namespace Tests\Feature;

use App\Models\{Article, Comment, ContributionVerification, Restaurant, RestaurantReview, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PurgeContributionVerificationsCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_only_purges_verifications_older_than_the_seven_day_retention_window_and_keeps_related_records(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 8101, 'name' => 'Purge avis', 'slug' => 'purge-avis', 'status' => 'published']);
        $article = Article::create(['legacy_wp_id' => 8102, 'original_title' => 'Purge commentaires', 'title' => 'Purge commentaires', 'slug' => 'purge-commentaires', 'legacy_url' => '/purge-commentaires', 'status' => 'published']);
        $user = User::factory()->create();
        $review = RestaurantReview::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'author_name' => 'Amina', 'rating' => 5, 'content' => 'Avis conservé', 'status' => 'pending']);
        $comment = Comment::create(['article_id' => $article->id, 'user_id' => $user->id, 'author_name' => 'Amina', 'content' => 'Commentaire conservé', 'status' => 'pending']);

        $valid = $this->verification($article, expiresAt: now()->addDay());
        $recentExpired = $this->verification($article, expiresAt: now()->subDays(6));
        $oldExpired = $this->verification($article, expiresAt: now()->subDays(8));
        $recentUsed = $this->verification($restaurant, usedAt: now()->subDays(6));
        $oldUsed = $this->verification($restaurant, usedAt: now()->subDays(8));
        $oldExpired->update(['user_id' => $user->id, 'created_contribution_type' => 'comment', 'created_contribution_id' => $comment->id]);
        $oldUsed->update(['user_id' => $user->id, 'created_contribution_type' => 'review', 'created_contribution_id' => $review->id]);

        $this->artisan('contributions:purge-verifications', ['--dry-run' => true])
            ->expectsOutput('Vérifications éligibles : 2.')
            ->expectsOutput('Vérifications supprimées : 0 (simulation).')
            ->assertSuccessful();
        $this->assertDatabaseCount('contribution_verifications', 5);

        $this->artisan('contributions:purge-verifications', ['--batch' => 1])
            ->expectsOutput('Vérifications éligibles : 2.')
            ->expectsOutput('Vérifications supprimées : 2.')
            ->assertSuccessful();

        foreach ([$valid, $recentExpired, $recentUsed] as $verification) {
            $this->assertDatabaseHas('contribution_verifications', ['id' => $verification->id]);
        }
        foreach ([$oldExpired, $oldUsed] as $verification) {
            $this->assertDatabaseMissing('contribution_verifications', ['id' => $verification->id]);
        }
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('restaurant_reviews', ['id' => $review->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'user_id' => $user->id]);

        $this->artisan('contributions:purge-verifications')
            ->expectsOutput('Vérifications éligibles : 0.')
            ->expectsOutput('Vérifications supprimées : 0.')
            ->assertSuccessful();
    }

    private function verification(Article|Restaurant $target, ?\DateTimeInterface $expiresAt = null, ?\DateTimeInterface $usedAt = null): ContributionVerification
    {
        return ContributionVerification::create([
            'email' => fake()->unique()->safeEmail(),
            'author_name' => 'Purge test',
            'contribution_type' => $target instanceof Restaurant ? 'review' : 'comment',
            'target_type' => $target instanceof Restaurant ? 'restaurant' : 'article',
            'target_id' => $target->id,
            'payload' => $target instanceof Restaurant ? ['rating' => 5, 'content' => 'Avis'] : ['content' => 'Commentaire'],
            'token_hash' => hash('sha256', fake()->unique()->uuid()),
            'expires_at' => $expiresAt ?? now()->subDays(10),
            'used_at' => $usedAt,
        ]);
    }
}
