<?php

namespace App\Console\Commands;

use App\Models\{Restaurant, RestaurantMedia};
use App\Services\{AdminAudit, MediaIngestor};
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, File};

class ApplyQuickCoverMediaCommand extends Command
{
    protected $signature = 'restaurants:apply-quick-cover {--apply : Convert the reviewed source and link it as normal cover media} {--source= : Reviewed Quick logo image} {--output= : Private normalized WebP output path} {--report=docs/generated/quick-cover-media.md : Markdown reconciliation report}';
    protected $description = 'Normalizes the reviewed Quick image and assigns it as normal, back-office-editable cover media to every current Quick.';

    public function handle(MediaIngestor $ingestor): int
    {
        $source = $this->option('source') ?: storage_path('app/private/source/quick/quick-logo.jpg');
        $output = $this->option('output') ?: storage_path('app/private/generated/quick/quick-logo-cover.webp');
        $restaurants = $this->currentQuickRestaurants();
        $sourceInfo = is_file($source) ? @getimagesize($source) : false;
        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'source' => basename($source), 'source_dimensions' => $sourceInfo ? "{$sourceInfo[0]}×{$sourceInfo[1]}" : null, 'restaurants' => $restaurants->count(), 'normalized_webp' => null, 'media_asset_id' => null, 'relations_created' => 0, 'relations_existing' => 0];
        if (! $sourceInfo || ! in_array($sourceInfo['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) { $this->error('The reviewed Quick source must be a readable JPEG, PNG or WebP image.'); return self::FAILURE; }
        [$width, $height] = $this->normalizedDimensions((int) $sourceInfo[0], (int) $sourceInfo[1]);
        $report['normalized_webp'] = basename($output)." ({$width}×{$height})";
        if ($this->option('apply')) {
            $this->convertToWebp($source, $output, $width, $height);
            $asset = $ingestor->ingest(new UploadedFile($output, 'quick-logo-cover.webp', 'image/webp', null, true), 'Logo Quick');
            $report['media_asset_id'] = $asset->id;
            DB::transaction(function () use ($restaurants, $asset, &$report): void {
                foreach ($restaurants as $restaurant) {
                    $media = RestaurantMedia::firstOrCreate(['restaurant_id' => $restaurant->id, 'media_asset_id' => $asset->id], ['sort_order' => 0, 'status' => 'ready', 'role' => 'gallery']);
                    if ($media->wasRecentlyCreated) {
                        RestaurantMedia::query()->where('restaurant_id', $restaurant->id)->where('id', '!=', $media->id)->where('role', '!=', 'fallback_thumbnail')->increment('sort_order');
                        $report['relations_created']++;
                        app(AdminAudit::class)->record('restaurant.quick_cover_assigned', $restaurant, ['media_asset_id' => $asset->id]);
                    } else $report['relations_existing']++;
                }
            });
        }
        $this->writeReport((string) $this->option('report'), $report);
        $this->info(json_encode($report, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function currentQuickRestaurants()
    {
        $official = Restaurant::query()->whereNull('legacy_wp_id')->where('slug', 'like', 'quick-%')->get();
        $auditPath = base_path('docs/generated/quick-restaurants-audit.csv');
        if (! is_file($auditPath)) return $official;
        $rows = array_map('str_getcsv', file($auditPath)); $header = array_shift($rows);
        if (! $header) return $official;
        $reviewedIds = collect($rows)->map(fn (array $row) => array_combine($header, $row))->filter(fn (array $row) => in_array($row['match_status'] ?? null, ['MATCH_EXACT', 'MATCH_UPDATE', 'MATCH_PROBABLE'], true))->pluck('top_halal_restaurant_id')->filter();
        return $official->merge(Restaurant::query()->whereIn('id', $reviewedIds)->get())->keyBy('id')->values();
    }

    private function normalizedDimensions(int $sourceWidth, int $sourceHeight): array { $width = min(1440, $sourceWidth); return [$width, (int) round($sourceHeight * $width / $sourceWidth)]; }

    private function convertToWebp(string $sourcePath, string $outputPath, int $width, int $height): void
    {
        if (! function_exists('imagewebp')) throw new \RuntimeException('GD WebP support is required to normalize Quick media.');
        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($source === false) throw new \RuntimeException('Unable to decode the reviewed Quick image.');
        File::ensureDirectoryExists(dirname($outputPath));
        $target = imagecreatetruecolor($width, $height); imagealphablending($target, false); imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source)); imagewebp($target, $outputPath, 82);
        imagedestroy($target); imagedestroy($source);
    }

    private function writeReport(string $path, array $report): void
    {
        $lines = ['# Média de couverture Quick', '', '- Mode : `'.$report['mode'].'`.', '- Source revue : `'.$report['source'].'`'.($report['source_dimensions'] ? ' ('.$report['source_dimensions'].').' : '.'), '- Normalisation : `'.$report['normalized_webp'].'` (WebP qualité 82, sans recadrage ni agrandissement).', '- Quick officiels actuels : **'.$report['restaurants'].'**.', '- Asset V2 : '.($report['media_asset_id'] ? '`'.$report['media_asset_id'].'`' : 'non créé (dry-run)').'.', '- Relations créées : **'.$report['relations_created'].'** ; déjà présentes : **'.$report['relations_existing'].'**.', '', 'Le média est une relation `restaurant_media` standard de rôle `gallery`, placée en première position : il sert donc de couverture et miniature tout en restant modifiable, supprimable et réordonnable dans le back-office.'];
        File::ensureDirectoryExists(dirname(base_path($path))); File::put(base_path($path), implode("\n", $lines)."\n");
    }
}
