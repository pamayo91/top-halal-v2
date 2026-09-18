<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Restaurant, RestaurantReview};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
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
    public function test_new_review_requires_verification_and_urls_and_invalid_rating_are_rejected(): void
    {
        Mail::fake();
        $url = '/_preview/restaurant/13567/reviews';
        $this->post($url,['name'=>'Élodie','email'=>'e@example.test','rating'=>5,'content'=>'Très bon'])->assertRedirect(); $this->assertSame(0,RestaurantReview::count());
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'contribution_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();
        $this->assertSame('pending',RestaurantReview::first()->status);
        $this->post($url,['name'=>'Élodie','email'=>'e@example.test','rating'=>6,'content'=>'Très bon'])->assertSessionHasErrors('rating');
        $this->post($url,['name'=>'Élodie','email'=>'e@example.test','rating'=>5,'content'=>'https://example.test'])->assertSessionHasErrors('content');
    }

    public function test_public_review_form_uses_accessible_stars_and_preserves_historical_titles(): void
    {
        RestaurantReview::create(['restaurant_id' => $this->restaurant->id, 'author_name' => 'Amina', 'rating' => 4, 'title' => 'Titre historique', 'content' => 'Très bon accueil.', 'status' => 'approved']);

        $this->get(route('restaurants.show', $this->restaurant->slug))
            ->assertOk()
            ->assertSee('Prénom ou pseudo')
            ->assertSee('Votre note')
            ->assertSee('Choisissez une note')
            ->assertSee('Votre e-mail ne sera jamais affiché publiquement.')
            ->assertSee('Envoyer mon avis')
            ->assertSee('Titre historique')
            ->assertSee('name="rating" value="1"', false)
            ->assertSee('name="rating" value="5"', false)
            ->assertDontSee('Titre (facultatif)')
            ->assertDontSee('<select name="rating"', false);
    }

    public function test_empty_review_state_does_not_repeat_a_zero_review_count(): void
    {
        $this->get(route('restaurants.show', $this->restaurant->slug))
            ->assertOk()
            ->assertSee('Aucun avis pour le moment.')
            ->assertDontSee('0 avis')
            ->assertSee('<strong>Vous connaissez Étoile ?</strong> Partagez votre expérience avec la communauté Top Halal.', false)
            ->assertDontSee('Étoile ?<br>Partagez', false)
            ->assertSee('Donner mon avis');
    }

    public function test_review_validation_reopens_the_form_and_keeps_the_selected_rating(): void
    {
        $this->followingRedirects()
            ->from(route('restaurants.show', $this->restaurant->slug))
            ->post(route('restaurants.reviews.store', $this->restaurant->slug), ['name' => 'Élodie', 'email' => 'e@example.test', 'rating' => 4, 'content' => 'https://example.test'])
            ->assertSee('class="review-form-disclosure" open', false)
            ->assertSee('value="4" required checked', false)
            ->assertSee('id="review-content-error"', false);
    }

    public function test_new_reviews_ignore_a_forged_historical_title(): void
    {
        Mail::fake();

        $this->post('/_preview/restaurant/13567/reviews', ['name' => 'Élodie', 'email' => 'e@example.test', 'rating' => 5, 'title' => 'Titre forgé', 'content' => 'Très bon'])
            ->assertRedirect();

        $this->assertNull(\App\Models\ContributionVerification::sole()->payload['title'] ?? null);
    }
}
