<?php

namespace App\Services;

use App\Models\ContentMedia;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class EditorialMediaAttachments
{
    public function upload(UploadedFile $file): MediaAsset
    {
        return app(MediaIngestor::class)->ingest($file);
    }

    public function url(mixed $id): ?string
    {
        if (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1) {
            return null;
        }

        $asset = MediaAsset::query()
            ->whereKey((int) $id)
            ->whereIn('mime', MediaAsset::RESTAURANT_IMAGE_MIMES)
            ->where('status', 'ready')
            ->first();

        return $asset?->deliveryUrl();
    }

    public function sync(Model $content, string $type): void
    {
        preg_match_all('#/media/(\d+)/v/[a-f0-9]{64}(?:/\d+)?#', (string) $content->content_html, $matches);
        $assetIds = collect($matches[1] ?? [])->map(static fn (string $id): int => (int) $id)->filter()->unique();

        foreach (MediaAsset::query()->whereIn('id', $assetIds)->whereIn('mime', MediaAsset::RESTAURANT_IMAGE_MIMES)->pluck('id') as $assetId) {
            ContentMedia::query()->firstOrCreate([
                'content_type' => $type,
                'content_id' => $content->getKey(),
                'media_asset_id' => $assetId,
                'role' => 'inline',
            ]);
        }
    }
}
