<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaVariantGenerator
{
    /** @return list<string> */
    public function generate(MediaAsset $asset): array
    {
        if (! in_array($asset->mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ! function_exists('imagewebp') || ! $asset->width || ! $asset->height) {
            return [];
        }

        $disk = Storage::disk(config('legacy-media.disk'));
        $source = @imagecreatefromstring($disk->get($asset->original_path));

        if ($source === false) {
            throw new RuntimeException('Unable to decode copied image.');
        }

        try {
            $paths = [];
            foreach (config('legacy-media.variants') as $variant) {
                $width = min($variant['width'], $asset->width);
                $height = (int) round($asset->height * $width / $asset->width);
                $path = "media/variants/{$asset->checksum}-{$width}.webp";

                if (! $disk->exists($path)) {
                    $image = imagecreatetruecolor($width, $height);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $asset->width, $asset->height);
                    ob_start();
                    imagewebp($image, null, 82);
                    $disk->put($path, (string) ob_get_clean());
                    imagedestroy($image);
                }

                $asset->variants()->updateOrCreate(
                    ['format' => 'webp', 'width' => $width],
                    ['height' => $height, 'path' => $path],
                );
                $paths[] = $path;
            }

            return array_values(array_unique($paths));
        } finally {
            imagedestroy($source);
        }
    }
}
