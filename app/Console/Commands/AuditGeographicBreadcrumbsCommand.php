<?php

namespace App\Console\Commands;

use App\Models\{CityServiceSeoPage, CitySpecialtySeoPage, Restaurant};
use App\Services\{CityPageResolver, GeographicBreadcrumbs, GeographicPageResolver};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Read-only verification of every published geographic breadcrumb destination. */
class AuditGeographicBreadcrumbsCommand extends Command
{
    protected $signature = 'geography:audit-breadcrumbs {--json : Emit JSON only} {--out=docs/generated/geographic-breadcrumb-audit.json : Public report path}';

    protected $description = 'Audits published restaurant, city and open facet breadcrumbs without changing data.';

    public function handle(CityPageResolver $cities, GeographicPageResolver $geography, GeographicBreadcrumbs $breadcrumbs): int
    {
        $before = $this->fingerprint();
        $errors = [];
        $counts = ['restaurants' => 0, 'cities' => 0, 'specialty_facets' => 0, 'service_facets' => 0, 'departments' => 0, 'regions' => 0];

        Restaurant::query()->where('status', 'published')->orderBy('id')->each(function (Restaurant $restaurant) use (&$errors, &$counts, $cities, $geography, $breadcrumbs): void {
            $counts['restaurants']++;
            $city = $cities->cityForCode((string) $restaurant->city_code);
            if ($city === null) {
                $errors[] = $this->error('restaurant_city_unresolved', (string) $restaurant->id, (string) $restaurant->city_code);
                return;
            }
            $this->verifyCityTrail($breadcrumbs->forRestaurant($restaurant), $city, $cities, $geography, "restaurant:{$restaurant->id}", $errors);
        });

        foreach ($cities->cities() as $city) {
            $counts['cities']++;
            $this->verifyCityTrail($breadcrumbs->forCity($city), $city, $cities, $geography, "city:{$city->city_code}", $errors);
        }

        foreach (CitySpecialtySeoPage::query()->with('category')->where('state', 'open')->get() as $facet) {
            $counts['specialty_facets']++;
            $city = $cities->cityForCode($facet->city_code);
            if ($city === null || $facet->category === null) {
                $errors[] = $this->error('specialty_facet_identity_unresolved', (string) $facet->id);
                continue;
            }
            $this->verifyCityTrail($breadcrumbs->forCityFacet($city, $facet->category), $city, $cities, $geography, "specialty_facet:{$facet->id}", $errors);
        }

        foreach (CityServiceSeoPage::query()->with('feature')->where('state', 'open')->get() as $facet) {
            $counts['service_facets']++;
            $city = $cities->cityForCode($facet->city_code);
            if ($city === null || $facet->feature === null) {
                $errors[] = $this->error('service_facet_identity_unresolved', (string) $facet->id);
                continue;
            }
            $this->verifyCityTrail($breadcrumbs->forCityFacet($city, $facet->feature), $city, $cities, $geography, "service_facet:{$facet->id}", $errors);
        }

        foreach ($geography->departments() as $department) {
            $counts['departments']++;
            foreach ($breadcrumbs->forDepartment($department) as $item) {
                if ($item['url'] !== null && $item['label'] !== 'Accueil' && $item['label'] !== 'Restaurants') {
                    $this->verifyDestination('region', $item['url'], $department->region['code'], $cities, $geography, "department:{$department->code}", $errors);
                }
            }
        }

        foreach ($geography->regions() as $region) {
            $counts['regions']++;
            $this->verifyDestination('region', route('cities.show', $region->slug), $region->code, $cities, $geography, "region:{$region->code}", $errors);
        }

        if ($before !== $this->fingerprint()) {
            $errors[] = $this->error('read_only_guard_failed', 'restaurants_changed_during_audit');
        }

        $report = ['audited_at' => now('Europe/Paris')->toIso8601String(), 'read_only' => true, 'counts' => $counts, 'errors' => $errors];
        if (! $this->option('json')) {
            $path = base_path((string) $this->option('out'));
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            $this->info($path);
        }
        $this->output->write(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<array{label:string,url:?string}> $items @param list<array{scope:string,kind:string,identity:string,actual:string}> $errors */
    private function verifyCityTrail(array $items, object $city, CityPageResolver $cities, GeographicPageResolver $geography, string $scope, array &$errors): void
    {
        $region = $geography->regionForCode($city->region['code']);
        $department = $geography->departmentForCode($city->department['code']);
        if ($region === null || $department === null) {
            $errors[] = $this->error('administrative_identity_unresolved', $scope);
            return;
        }

        $expected = [
            route('cities.show', $region->slug) => ['region', $region->code],
            route('cities.show', $department->slug) => ['department', $department->code],
            route('cities.show', $city->slug) => ['city', $city->city_code],
        ];

        foreach ($items as $item) {
            if ($item['url'] === null || in_array($item['label'], ['Accueil', 'Restaurants'], true)) continue;
            if (! isset($expected[$item['url']])) {
                $errors[] = $this->error('unexpected_geographic_destination', $scope, $item['url']);
                continue;
            }
            [$kind, $identity] = $expected[$item['url']];
            $this->verifyDestination($kind, $item['url'], $identity, $cities, $geography, $scope, $errors);
        }
    }

    /** @param list<array{scope:string,kind:string,identity:string,actual:string}> $errors */
    private function verifyDestination(string $kind, string $url, string $identity, CityPageResolver $cities, GeographicPageResolver $geography, string $scope, array &$errors): void
    {
        $slug = basename((string) parse_url($url, PHP_URL_PATH));
        $resolved = match ($kind) {
            'city' => $cities->cityForSlug($slug),
            'department' => $geography->departmentForSlug($slug),
            'region' => $geography->regionForSlug($slug),
        };
        $actual = $resolved?->{$kind === 'city' ? 'city_code' : 'code'} ?? 'unresolved';
        if ($actual !== $identity) $errors[] = $this->error("{$kind}_destination_mismatch", $scope, "expected={$identity};actual={$actual};slug={$slug}");
        if ($kind === 'city' && $cities->ambiguityForSlug($slug)->isNotEmpty()) $errors[] = $this->error('city_disambiguation_used_as_precise_destination', $scope, $slug);
    }

    /** @return array{scope:string,kind:string,identity:string,actual:string} */
    private function error(string $kind, string $scope, string $actual = ''): array
    {
        return ['scope' => $scope, 'kind' => $kind, 'identity' => '', 'actual' => $actual];
    }

    private function fingerprint(): string
    {
        return json_encode([Restaurant::query()->count(), Restaurant::query()->max('updated_at')], JSON_THROW_ON_ERROR);
    }
}
