<?php

namespace App\Console\Commands;

use App\Models\{Category, MediaAsset, Restaurant, RestaurantMedia};
use App\Services\{AdminAudit, MediaIngestor, SpecialtyImageProcessor};
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, File};
use Illuminate\Support\Str;
use RuntimeException;

class ApplySpecialtyThumbnailsCommand extends Command
{
    protected $signature = 'data:apply-specialty-thumbnails {--apply : Write the V2 specialty media and fallback thumbnails} {--slugs= : Comma-separated specialty slugs, for a reviewed subset} {--source= : Private directory containing specialty source images} {--output= : Private directory for normalized WebP files} {--report= : Private JSON report path}';
    protected $description = 'Converts reviewed specialty source images into V2 media and assigns them as card-only fallbacks to restaurants without a photo.';

    public function handle(MediaIngestor $ingestor, SpecialtyImageProcessor $processor): int
    {
        $source = $this->option('source') ?: storage_path('app/private/source/photos-specialites');
        $generated = $this->option('output') ?: storage_path('app/private/generated/specialty-images');
        $reportPath = $this->option('report') ?: storage_path('app/private/reports/specialty-thumbnail-assignment.json');
        $slugs = collect(explode(',', (string) $this->option('slugs')))->map(fn (string $slug) => Str::slug(trim($slug)))->filter()->values();
        $files = collect(File::files($source))->mapWithKeys(fn ($file) => [Str::slug(pathinfo($file->getFilename(), PATHINFO_FILENAME)) => $file->getPathname()]);
        $categories = Category::query()->when($slugs->isNotEmpty(), fn ($query) => $query->whereIn('slug', $slugs))->orderBy('name')->get();
        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'specialties' => [], 'thumbnails' => ['eligible' => 0, 'assigned' => 0, 'skipped_without_specialty_image' => 0], 'mauricienne' => null];

        if ($categories->isEmpty()) {
            $this->error('No requested V2 specialty exists.');
            return self::FAILURE;
        }

        foreach ($categories as $category) {
            $path = $files->get($category->slug);
            if ($path === null) {
                if ($category->slug === 'mauricienne') continue;
                throw new RuntimeException("Missing source image for specialty {$category->slug}.");
            }
            $report['specialties'][$category->slug] = ['source' => basename($path), 'output' => "{$category->slug}.webp", 'media_asset_id' => $category->media_asset_id];
            if (! $this->option('apply')) continue;

            File::ensureDirectoryExists($generated);
            $output = $generated.DIRECTORY_SEPARATOR.$category->slug.'.webp';
            $processor->convert($path, $output);
            $asset = $ingestor->ingest(new UploadedFile($output, $category->slug.'.webp', 'image/webp', null, true), "Illustration de la spécialité {$category->name}");
            $category->update(['media_asset_id' => $asset->id]);
            $report['specialties'][$category->slug]['media_asset_id'] = $asset->id;
        }

        $mauricienne = Category::where('slug', 'mauricienne')->first();
        if ($mauricienne) {
            $usage = DB::table('restaurant_category')->where('category_id', $mauricienne->id)->count();
            $report['mauricienne'] = ['restaurant_relations' => $usage, 'deleted' => false];
            if ($this->option('apply') && ($slugs->isEmpty() || $slugs->contains('mauricienne')) && $usage === 0) {
                $mauricienne->delete();
                $report['mauricienne']['deleted'] = true;
            }
        }

        $eligible = Restaurant::query()->whereDoesntHave('media.asset', fn ($query) => $query->whereIn('mime', MediaAsset::RESTAURANT_IMAGE_MIMES)->where('restaurant_media.role', '!=', 'fallback_thumbnail'));
        $eligible->with(['categories.media'])->orderBy('id')->chunkById(200, function ($restaurants) use (&$report): void {
            foreach ($restaurants as $restaurant) {
                $report['thumbnails']['eligible']++;
                $category = $restaurant->categories->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->first();
                $asset = $category?->media;
                if (! $asset?->isRestaurantImage()) {
                    $report['thumbnails']['skipped_without_specialty_image']++;
                    continue;
                }
                if ($this->option('apply')) {
                    RestaurantMedia::firstOrCreate(['restaurant_id' => $restaurant->id, 'media_asset_id' => $asset->id], ['sort_order' => 0, 'status' => 'ready', 'role' => 'fallback_thumbnail']);
                    app(AdminAudit::class)->record('restaurant.specialty_thumbnail_assigned', $restaurant, ['category_id' => $category->id, 'media_asset_id' => $asset->id]);
                }
                $report['thumbnails']['assigned']++;
            }
        }, 'restaurants.id', 'id');

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info(json_encode($report, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
