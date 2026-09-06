<?php

namespace App\Console\Commands;

use App\Models\CityReferencePoint;
use App\Services\{CityPageResolver, NearbyCityService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncCityReferencePointsCommand extends Command
{
    protected $signature = 'city-reference-points:sync {--refresh : Refresh already stored official points too}';

    protected $description = 'Synchronize local commune reference points from the official French administrative API.';

    public function handle(CityPageResolver $cities, NearbyCityService $nearby): int
    {
        $cityCodes = $cities->cities()->pluck('city_code')->unique()->values();
        $knownCodes = CityReferencePoint::query()->whereIn('city_code', $cityCodes)->pluck('city_code');
        $targetCodes = $this->option('refresh') ? $cityCodes : $cityCodes->diff($knownCodes)->values();

        if ($targetCodes->isEmpty()) {
            $this->info('All published city pages already have a local reference point.');

            return self::SUCCESS;
        }

        $response = Http::acceptJson()
            ->timeout(90)
            ->retry(2, 500)
            ->get(config('city-nearby.reference_source_url'), ['fields' => 'code,centre']);

        if (! $response->successful() || ! is_array($response->json())) {
            $this->error('The official commune reference could not be synchronized.');

            return self::FAILURE;
        }

        $requested = array_fill_keys($targetCodes->all(), true);
        $now = now();
        $rows = collect($response->json())
            ->map(function (mixed $commune) use ($requested, $now): ?array {
                $code = is_array($commune) ? ($commune['code'] ?? null) : null;
                $coordinates = is_array($commune) ? ($commune['centre']['coordinates'] ?? null) : null;

                if (! is_string($code) || ! isset($requested[$code]) || ! is_array($coordinates) || count($coordinates) !== 2
                    || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
                    return null;
                }

                $longitude = (float) $coordinates[0];
                $latitude = (float) $coordinates[1];

                if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    return null;
                }

                return [
                    'city_code' => $code,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'source' => 'geo.api.gouv.fr/communes',
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($rows !== []) {
            CityReferencePoint::query()->upsert(
                $rows,
                ['city_code'],
                ['latitude', 'longitude', 'source', 'synced_at', 'updated_at'],
            );
        }

        $nearby->forget();
        $missing = $targetCodes->count() - count($rows);
        $this->info(sprintf('%d local commune reference point(s) synchronized; %d unavailable.', count($rows), $missing));

        return self::SUCCESS;
    }
}
