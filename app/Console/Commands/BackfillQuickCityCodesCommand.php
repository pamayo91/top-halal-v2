<?php

namespace App\Console\Commands;

use App\Models\{CommuneReference, Restaurant};
use App\Services\{CityPageResolver, CitySeoService, CommuneTextNormalizer, GeographicPageResolver};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File};

/** One-off, tightly scoped V2-only city-code correction for the current Quick batch. */
class BackfillQuickCityCodesCommand extends Command
{
    private const EXCLUDED_IDS = [7745, 7775, 7797, 7883];

    protected $signature = 'quick:backfill-city-codes
        {--apply : Apply only deterministic unique matches; dry-run is the default}
        {--json : Emit JSON only}
        {--out=docs/generated/quick-city-code-backfill-report.json : Report path}';

    protected $description = 'Dry-runs or transactionally backfills city_code only for the unresolved Quick restaurants.';

    public function handle(CommuneTextNormalizer $normalizer): int
    {
        $restaurants = Restaurant::query()->where('status', 'published')->whereNull('city_code')->orderBy('id')->get(['id', 'name', 'city_name', 'postal_code', 'city_code', 'country_code']);
        $rows = $restaurants->map(fn (Restaurant $restaurant): array => $this->resolve($restaurant, $normalizer))->all();
        $unique = array_values(array_filter($rows, fn (array $row): bool => $row['status'] === 'unique_match'));
        $before = $this->protectedSnapshot(array_column($unique, 'restaurant_id'));
        $applied = 0;

        if ($this->option('apply') && $unique !== []) {
            DB::transaction(function () use ($unique, &$applied): void {
                foreach ($unique as $row) {
                    $written = DB::table('restaurants')->where('id', $row['restaurant_id'])->whereNull('city_code')->update(['city_code' => $row['proposed_city_code']]);
                    if ($written !== 1) throw new \RuntimeException('Unexpected city_code write scope for restaurant '.$row['restaurant_id']);
                    $applied += $written;
                }
            });
            if ($this->protectedSnapshot(array_column($unique, 'restaurant_id')) !== $before) throw new \RuntimeException('Protected restaurant fields changed during city_code backfill.');
            app(CityPageResolver::class)->forget(); app(CitySeoService::class)->forget(); app(GeographicPageResolver::class)->forget();
        }

        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry_run', 'read_only' => ! $this->option('apply'), 'analyzed' => count($rows), 'applied' => $applied, 'summary' => collect($rows)->countBy('status')->sortKeys()->all(), 'rows' => $rows];
        if (! $this->option('json')) { $path = base_path((string) $this->option('out')); File::ensureDirectoryExists(dirname($path)); File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n"); $this->info($path); }
        $this->output->write(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function resolve(Restaurant $restaurant, CommuneTextNormalizer $normalizer): array
    {
        $base = ['restaurant_id' => $restaurant->id, 'name' => $restaurant->name, 'city_name' => $restaurant->city_name, 'postal_code' => $restaurant->postal_code, 'city_code' => $restaurant->city_code, 'commune_found' => null, 'proposed_city_code' => null];
        if (in_array($restaurant->id, self::EXCLUDED_IDS, true)) return array_merge($base, ['status' => 'excluded_manual_review', 'reason' => 'Explicitly excluded non-municipal or special city label.']);
        if (strtoupper((string) $restaurant->country_code) !== 'FR') return array_merge($base, ['status' => 'no_match', 'reason' => 'Country is outside the French local reference.']);
        $department = $this->postalDepartment((string) $restaurant->postal_code);
        if ($department === null) return array_merge($base, ['status' => 'no_match', 'reason' => 'Postal code cannot deterministically bound a French department.']);
        $matches = CommuneReference::query()->where('normalized_name', $normalizer->normalize((string) $restaurant->city_name))->get(['city_code', 'city_name', 'department_code'])
            ->filter(fn (CommuneReference $commune): bool => in_array($commune->department_code, $department, true))->values();
        if ($matches->count() !== 1) return array_merge($base, ['status' => $matches->isEmpty() ? 'no_match' : 'ambiguous', 'reason' => $matches->isEmpty() ? 'No local commune matches normalized name and postal department.' : 'Several local communes match normalized name and postal department.']);
        $commune = $matches->first();
        return array_merge($base, ['commune_found' => ['city_name' => $commune->city_name, 'city_code' => $commune->city_code, 'department_code' => $commune->department_code], 'proposed_city_code' => $commune->city_code, 'status' => 'unique_match', 'reason' => 'Unique local match on normalized city name and postal department.']);
    }

    /** @return list<string>|null */
    private function postalDepartment(string $postalCode): ?array
    {
        $postalCode = preg_replace('/\s+/', '', $postalCode) ?? '';
        if (! preg_match('/^\d{5}$/', $postalCode)) return null;
        if (preg_match('/^97[1-8]/', $postalCode)) return [substr($postalCode, 0, 3)];
        if (str_starts_with($postalCode, '20')) return ['2A', '2B'];
        return [substr($postalCode, 0, 2)];
    }

    /** @param list<int> $ids */
    private function protectedSnapshot(array $ids): string
    {
        return Restaurant::query()->whereIn('id', $ids)->orderBy('id')->get(['id', 'city_name', 'postal_code', 'address', 'address_line1', 'address_line2', 'country_code', 'latitude', 'longitude', 'slug', 'status'])->toJson(JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
