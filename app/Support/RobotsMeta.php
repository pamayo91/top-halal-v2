<?php

namespace App\Support;

use App\Models\Restaurant;

class RobotsMeta
{
    /** @var array<string, string> */
    public const DIRECTIVE_OPTIONS = [
        'all' => 'Tout autoriser (valeur par défaut)',
        'noindex' => 'Ne pas indexer',
        'nofollow' => 'Ne pas suivre les liens',
        'none' => 'Ne pas indexer ni suivre (none)',
        'nosnippet' => 'Ne pas afficher d’extrait',
        'indexifembedded' => 'Indexer uniquement si intégré',
        'noimageindex' => 'Ne pas indexer les images',
        'notranslate' => 'Ne pas proposer de traduction',
    ];

    public static function forRestaurant(Restaurant $restaurant): string
    {
        $directives = self::normalize($restaurant->seo_robots);

        if ($restaurant->seo_max_snippet !== null) {
            $directives[] = 'max-snippet:'.$restaurant->seo_max_snippet;
        }

        if ($restaurant->seo_max_image_preview !== null) {
            $directives[] = 'max-image-preview:'.$restaurant->seo_max_image_preview;
        }

        if ($restaurant->seo_max_video_preview !== null) {
            $directives[] = 'max-video-preview:'.$restaurant->seo_max_video_preview;
        }

        if ($restaurant->seo_unavailable_after !== null) {
            $directives[] = 'unavailable_after: '.$restaurant->seo_unavailable_after->utc()->toIso8601String();
        }

        return $directives === [] ? 'index,follow' : implode(',', $directives);
    }

    public static function normalize(?string $value): array
    {
        $directives = collect(explode(',', (string) $value))
            ->map(fn (string $directive): string => trim(strtolower($directive)))
            ->filter(fn (string $directive): bool => array_key_exists($directive, self::DIRECTIVE_OPTIONS))
            ->unique()
            ->values()
            ->all();

        if (count($directives) > 1 && in_array('all', $directives, true)) {
            $directives = array_values(array_diff($directives, ['all']));
        }

        return $directives;
    }
}
