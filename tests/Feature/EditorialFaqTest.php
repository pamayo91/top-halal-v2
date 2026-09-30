<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\ContentSanitizer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorialFaqTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_faq_is_server_rendered_with_its_existing_questions_answers_and_one_matching_schema(): void
    {
        $page = $this->page('<h2>Questions fréquentes</h2>[faq][question title="Première question ?"]<p>Première <strong>réponse</strong>.</p>[/question][question title="Deuxième question ?"]<p>Deuxième réponse.</p>[/question][/faq]');

        $response = $this->get('/'.$page->slug)->assertOk()
            ->assertSee('editorial-faq', false)
            ->assertSee('<details>', false)
            ->assertSee('Première question ?')
            ->assertSee('Première <strong>réponse</strong>.', false)
            ->assertSee('Deuxième question ?')
            ->assertDontSee('[faq]', false)
            ->assertDontSee('[question', false);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'));
        $this->assertStringContainsString('"name":"Premi\\u00e8re question ?"', $html);
        $this->assertStringContainsString('"text":"Premi\\u00e8re r\\u00e9ponse."', $html);
    }

    public function test_multiple_faq_blocks_are_rendered_independently(): void
    {
        $page = $this->page('[faq][question title="Un ?"]<p>Réponse un.</p>[/question][/faq]<p>Texte central.</p>[faq][question title="Deux ?"]<p>Réponse deux.</p>[/question][/faq]');

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="editorial-faq"'));
        $this->assertStringContainsString('Texte central.', $html);
        $this->assertStringContainsString('Réponse un.', $html);
        $this->assertStringContainsString('Réponse deux.', $html);
    }

    public function test_malformed_faq_syntax_is_hidden_and_a_script_in_an_answer_cannot_execute(): void
    {
        $page = $this->page('[faq][question title="Sûre ?"]<p>Réponse.</p><script>alert(1)</script>[/question][/faq]<p>[faq][question title="Sans fermeture"]</p>');

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertSee('Réponse.')
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('[faq]', false)
            ->assertDontSee('[question', false);

        $this->assertSame('[faq][question title="Sûre ?"]<p>Réponse.</p>[/question][/faq]', app(ContentSanitizer::class)->sanitize('[faq][question title="Sûre ?"]<p>Réponse.</p>[/question][/faq]')['html']);
    }

    public function test_an_editorial_page_without_a_faq_does_not_render_faq_markup_or_schema(): void
    {
        $page = $this->page('<h2>Contenu classique</h2><p>Sans accordéon.</p>');

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertDontSee('editorial-faq', false)
            ->assertDontSee('FAQPage', false);
    }

    private function page(string $content): Page
    {
        return Page::create([
            'legacy_wp_id' => random_int(1, 999999999),
            'original_title' => 'Page FAQ',
            'title' => 'Page FAQ',
            'slug' => 'page-faq-'.Str::lower(Str::random(10)),
            'legacy_url' => '/page-faq',
            'content_html' => $content,
            'status' => 'published',
        ]);
    }
}
