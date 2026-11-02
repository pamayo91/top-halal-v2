<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Article, ContributionVerification, Restaurant, Setting};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Crypt, Mail};
use Tests\TestCase;

class ContributionSpamProtectionTest extends TestCase
{
    use DatabaseMigrations;

    private Restaurant $restaurant;
    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::create(['legacy_wp_id' => 8901, 'name' => 'Protection contribution', 'slug' => 'protection-contribution', 'status' => 'published']);
        $this->article = Article::create(['legacy_wp_id' => 8902, 'original_title' => 'Protection commentaire', 'title' => 'Protection commentaire', 'slug' => 'protection-commentaire', 'legacy_url' => '/protection-commentaire', 'status' => 'published']);
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'operations@example.test']]);
        Mail::fake();
    }

    public function test_a_legitimate_contribution_creates_one_verification_and_one_email(): void
    {
        $this->postReview()->assertRedirect();
        $this->assertDatabaseCount('contribution_verifications', 1);
        $this->assertDatabaseCount('email_delivery_logs', 1);
        Mail::assertQueued(TemplateMailable::class, 1);
    }

    public function test_duplicate_honeypot_and_time_trap_posts_create_nothing(): void
    {
        $payload = $this->reviewPayload();
        $this->post('/resto/protection-contribution/avis', $payload)->assertRedirect();
        $this->post('/resto/protection-contribution/avis', $payload)->assertRedirect();
        $this->post('/resto/protection-contribution/avis', $this->reviewPayload(['website' => 'bot']))->assertRedirect();
        $this->post('/resto/protection-contribution/avis', $this->reviewPayload(['form_token' => $this->immediateToken()]))->assertRedirect();
        $this->assertDatabaseCount('contribution_verifications', 1);
        $this->assertDatabaseCount('email_delivery_logs', 1);
        Mail::assertQueued(TemplateMailable::class, 1);
    }

    public function test_email_and_ip_limits_stop_before_a_verification_or_email(): void
    {
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
                ->post('/resto/protection-contribution/avis', $this->reviewPayload(['content' => 'Avis distinct '.$i]))->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/resto/protection-contribution/avis', $this->reviewPayload(['content' => 'Avis distinct 4']))->assertRedirect();
        $this->assertDatabaseCount('contribution_verifications', 5);
        $this->assertDatabaseCount('email_delivery_logs', 5);

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
                ->post('/protection-commentaire/commentaires', $this->commentPayload(['email' => 'ip-'.$i.'@example.test', 'content' => 'Commentaire distinct '.$i]))->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->post('/protection-commentaire/commentaires', $this->commentPayload(['email' => 'ip-6@example.test', 'content' => 'Commentaire distinct 6']))->assertRedirect();
        $this->assertDatabaseCount('contribution_verifications', 10);
        $this->assertDatabaseCount('email_delivery_logs', 10);
    }

    private function postReview(array $overrides = [])
    {
        return $this->post('/resto/protection-contribution/avis', $this->reviewPayload($overrides));
    }

    private function reviewPayload(array $overrides = []): array
    {
        return [...['name' => 'Amina', 'email' => 'amina@example.test', 'rating' => 5, 'content' => 'Très bon accueil.', 'form_token' => $this->validToken()], ...$overrides];
    }

    private function commentPayload(array $overrides = []): array
    {
        return [...['name' => 'Amina', 'email' => 'comment@example.test', 'content' => 'Merci pour ces informations.', 'form_token' => $this->validToken()], ...$overrides];
    }

    private function validToken(): string
    {
        return Crypt::encryptString(json_encode(['issued_at' => now()->subSeconds(3)->timestamp, 'nonce' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));
    }

    private function immediateToken(): string
    {
        return Crypt::encryptString(json_encode(['issued_at' => now()->timestamp, 'nonce' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));
    }
}
