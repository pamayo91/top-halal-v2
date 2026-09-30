<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentMedia;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Services\EditorialMediaAttachments;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EditorialMediaAttachmentsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_ready_editorial_image_assets_have_only_a_versioned_v2_url(): void
    {
        $asset = $this->asset('a');

        $this->assertSame($asset->deliveryUrl(), app(EditorialMediaAttachments::class)->url((string) $asset->id));
        $this->assertNull(app(EditorialMediaAttachments::class)->url('storage/editorial-image.jpg'));

        $asset->update(['status' => 'processing']);

        $this->assertNull(app(EditorialMediaAttachments::class)->url($asset->id));
    }

    public function test_editorial_renderer_turns_a_valid_editor_image_id_into_a_v2_url(): void
    {
        $asset = $this->asset('d');

        $editorHtml = app(EditorialMediaAttachments::class)->forEditor('<p><img data-id="'.$asset->id.'" alt="Photo de test"></p>');
        $html = app(EditorialMediaAttachments::class)->render($editorHtml);

        $this->assertSame('<p><img src="'.$asset->deliveryUrl().'" data-id="'.$asset->id.'" alt="Photo de test"></p>', $editorHtml);
        $this->assertSame('<p><img src="'.$asset->deliveryUrl().'" alt="Photo de test"></p>', $html);
        $this->assertSame('', app(EditorialMediaAttachments::class)->render('<img data-id="999999">'));
    }

    public function test_saving_articles_and_pages_links_their_v2_inline_images(): void
    {
        $articleAsset = $this->asset('b');
        $pageAsset = $this->asset('c');

        $article = Article::create([
            'legacy_wp_id' => 91,
            'original_title' => 'Article image',
            'title' => 'Article image',
            'slug' => 'article-image',
            'legacy_url' => '/article-image',
            'status' => 'draft',
            'content_html' => '<p><img data-id="'.$articleAsset->id.'"></p>',
        ]);
        $page = Page::create([
            'legacy_wp_id' => 92,
            'original_title' => 'Page image',
            'title' => 'Page image',
            'slug' => 'page-image',
            'legacy_url' => '/page-image',
            'status' => 'draft',
            'content_html' => '<p><img data-id="'.$pageAsset->id.'"></p>',
        ]);

        $this->assertDatabaseHas('content_media', ['content_type' => 'post', 'content_id' => $article->id, 'media_asset_id' => $articleAsset->id, 'role' => 'inline']);
        $this->assertDatabaseHas('content_media', ['content_type' => 'page', 'content_id' => $page->id, 'media_asset_id' => $pageAsset->id, 'role' => 'inline']);

        $article->save();

        $this->assertSame(1, ContentMedia::query()->where('content_type', 'post')->where('content_id', $article->id)->where('media_asset_id', $articleAsset->id)->count());

        // The historical down migration restores a non-null legacy ID; keep
        // this isolated test database empty before DatabaseMigrations rolls it back.
        ContentMedia::query()->delete();
    }

    private function asset(string $checksumCharacter): MediaAsset
    {
        return MediaAsset::create([
            'original_path' => "media/originals/{$checksumCharacter}.jpg",
            'mime' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
            'bytes' => 10,
            'checksum' => str_repeat($checksumCharacter, 64),
            'status' => 'ready',
        ]);
    }
}
