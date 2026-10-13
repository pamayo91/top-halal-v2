<?php

namespace App\Console\Commands;

use App\Models\{CityServiceSeoPage, CitySpecialtySeoPage, Restaurant};
use App\Services\{AdministrativeGeography, CityPageResolver, GeographicBreadcrumbs, GeographicPageResolver};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Read-only verification of every published geographic breadcrumb destination. */
class AuditGeographicBreadcrumbsCommand extends Command
{
    protected $signature = 'geography:audit-breadcrumbs {--json : Emit JSON only} {--out=docs/generated/geographic-breadcrumb-audit.json : Public report path}';

    protected $description = 'Audits published restaurant, city and open facet breadcrumbs without changing data.';

    public function handle(AdministrativeGeography $administrative, CityPageResolver $cities, GeographicPageResolver $geography, GeographicBreadcrumbs $breadcrumbs): int
    {
        $before = $this->fingerprint();
        $errors = [];
        $counts = ['restaurants' => 0, 'cities' => 0, 'specialty_facets' => 0, 'service_facets' => 0, 'departments' => 0, 'regions' => 0];
        $citiesByCode = $cities->cities()->keyBy('city_code');
        $citiesBySlug = $citiesByCode->keyBy('slug');
        $departmentsByCode = $geography->departments()->keyBy('code');
        $departmentsBySlug = $departmentsByCode->keyBy('slug');
        $regionsByCode = $geography->regions()->keyBy('code');
        $regionsBySlug = $regionsByCode->keyBy('slug');

        Restaurant::query()->where('status', 'published')->orderBy('id')->each(function (Restaurant $restaurant) use (&$errors, &$counts, $administrative, $citiesByCode, $citiesBySlug, $departmentsByCode, $departmentsBySlug, $regionsByCode, $regionsBySlug, $breadcrumbs): void {
            $counts['restaurants']++;
            $city = $citiesByCode->get($administrative->canonicalCityCode((string) $restaurant->city_code));
            if ($city === null) {
                $errors[] = $this->auditError('restaurant_city_unresolved', (string) $restaurant->id, (string) $restaurant->city_code);
                return;
            }
            $this->verifyCityTrail($breadcrumbs->forRestaurant($restaurant, $city), $city, $citiesBySlug, $departmentsByCode, $departmentsBySlug, $regionsByCode, $regionsBySlug, "restaurant:{$restaurant->id}", $errors);
        });

        foreach ($citiesByCode as $city) {
            $counts['cities']++;
            $this->verifyCityTrail($breadcrumbs->forCity($city), $city, $citiesBySlug, $departmentsByCode, $departmentsBySlug, $regionsByCode, $regionsBySlug, "city:{$city->city_code}", $errors);
        }

        foreach (CitySpecialtySeoPage::query()->with('category')->where('state', 'open')->get() as $facet) {
            $counts['specialty_facets']++;
            $city = $citiesByCode->get($administrative->canonicalCityCode($facet->city_code));
            if ($city === null || $facet->category === null) {
                $errors[] = $this->auditError('specialty_facet_identity_unresolved', (string) $facet->id);
                continue;
            }
            $this->verifyCityTrail($breadcrumbs->forCityFacet($city, $facet->category), $city, $citiesBySlug, $departmentsByCode, $departmentsBySlug, $regionsByCode, $regionsBySlug, "specialty_facet:{$facet->id}", $errors);
        }

        foreach (CityServiceSeoPage::query()->with('feature')->where('state', 'open')->get() as $facet) {
            $counts['service_facets']++;
            $city = $citiesByCode->get($administrative->canonicalCityCode($facet->city_code));
            if ($city === null || $facet->feature === null) {
                $errors[] = $this->auditError('service_facet_identity_unresolved', (string) $facet->id);
                continue;
            }
            $this->verifyCityTrail($breadcrumbs->forCityFacet($city, $facet->feature), $city, $citiesBySlug, $departmentsByCode, $departmentsBySlug, $regionsByCode, $regionsBySlug, "service_facet:{$facet->id}", $errors);
        }

        foreach ($departmentsByCode as $department) {
            $counts['departments']++;
            foreach ($breadcrumbs->forDepartment($department) as $item) {
                if ($item['url'] !== null && $item['label'] !== 'Accueil' && $item['label'] !== 'Restaurants') {
                    $this->verifyDestination('region', $item['url'], $department->region['code'], $citiesBySlug, $departmentsBySlug, $regionsBySlug, "department:{$department->code}", $errors);
                }
            }
        }

        foreach ($regionsByCode as $region) {
            $counts['regions']++;
            $this->verifyDestination('region', route('cities.show', $region->slug), $region->code, $citiesBySlug, $departmentsBySlug, $regionsBySlug, "region:{$region->code}", $errors);
        }

        if ($before !== $this->fingerprint()) {
            $errors[] = $this->auditError('read_only_guard_failed', 'restaurants_changed_during_audit');
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
    private function verifyCityTrail(array $items, object $city, \Illuminate\Support\Collection $citiesBySlug, \Illuminate\Support\Collection $departmentsByCode, \Illuminate\Support\Collection $departmentsBySlug, \Illuminate\Support\Collection $regionsByCode, \Illuminate\Support\Collection $regionsBySlug, string $scope, array &$errors): void
    {
        $region = $regionsByCode->get($city->region['code']);
        $department = $departmentsByCode->get($city->department['code']);
        if ($region === null || $department === null) {
            $errors[] = $this->auditError('administrative_identity_unresolved', $scope);
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
                $errors[] = $this->auditError('unexpected_geographic_destination', $scope, $item['url']);
                continue;
            }
            [$kind, $identity] = $expected[$item['url']];
            $this->verifyDestination($kind, $item['url'], $identity, $citiesBySlug, $departmentsBySlug, $regionsBySlug, $scope, $errors);
        }
    }

    /** @param list<array{scope:string,kind:string,identity:string,actual:string}> $errors */
    private function verifyDestination(string $kind, string $url, string $identity, \Illuminate\Support\Collection $citiesBySlug, \Illuminate\Support\Collection $departmentsBySlug, \Illuminate\Support\Collection $regionsBySlug, string $scope, array &$errors): void
    {
        $slug = basename((string) parse_url($url, PHP_URL_PATH));
        $resolved = match ($kind) {
            'city' => $citiesBySlug->get($slug),
            'department' => $departmentsBySlug->get($slug),
            'region' => $regionsBySlug->get($slug),
        };
        $actual = $resolved?->{$kind === 'city' ? 'city_code' : 'code'} ?? 'unresolved';
        if ($actual !== $identity) $errors[] = $this->auditError("{$kind}_destination_mismatch", $scope, "expected={$identity};actual={$actual};slug={$slug}");
    }

    /** @return array{scope:string,kind:string,identity:string,actual:string} */
    private function auditError(string $kind, string $scope, string $actual = ''): array
    {
        return ['scope' => $scope, 'kind' => $kind, 'identity' => '', 'actual' => $actual];
    }

    private function fingerprint(): string
    {
        return json_encode([Restaurant::query()->count(), Restaurant::query()->max('updated_at')], JSON_THROW_ON_ERROR);
    }
}
