<?php
namespace App\Http\Controllers;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(MediaAsset $asset, string $version, ?int $width = null)
    {
        abort_unless(hash_equals((string) $asset->checksum, $version), 404);

        $variant = $width === null ? null : $asset->variants()->where('width', $width)->where('format', 'webp')->first();
        abort_if($width !== null && $variant === null, 404);

        $path = $variant?->path ?? $asset->original_path;
        $disk = Storage::disk(config('legacy-media.disk'));
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $variant ? 'image/webp' : $asset->mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function legacy(MediaAsset $asset, ?int $width = null)
    {
        return redirect()->to($asset->deliveryUrl($width));
    }
}
