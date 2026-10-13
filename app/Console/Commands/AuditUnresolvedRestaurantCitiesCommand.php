<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\{AdministrativeGeography, CityPageResolver};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Produces a read-only, deterministic explanation for every unresolved restaurant city. */
class AuditUnresolvedRestaurantCitiesCommand extends Command
{
    protected $signature = 'geography:audit-unresolved-restaurant-cities
        {--json : Emit the report as JSON only}
        {--out=docs/generated/unresolved-restaurant-cities-audit.md : Markdown report path}
        {--csv-out=docs/generated/unresolved-restaurant-cities-audit.csv : CSV report path}';

    protected $description = 'Classifies published restaurants whose city cannot be resolved without changing V2 data.';

    /** @var list<string> */
    private const FIELDS = ['id', 'legacy_wp_id', 'name', 'slug', 'status', 'address_line1', 'postal_code', 'city_name', 'city_code', 'country_code', 'latitude', 'longitude'];

    public function handle(AdministrativeGeography $administrative, CityPageResolver $cities): int
    {
        $before = $this->fingerprint();
        $rows = Restaurant::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->get(self::FIELDS)
            ->filter(fn (Restaurant $restaurant): bool => $cities->cityForCode((string) $restaurant->city_code) === null)
            ->map(fn (Restaurant $restaurant): array => $this->row($restaurant, $administrative, $cities))
            ->values()
            ->all();

        $after = $this->fingerprint();
        if ($before !== $after) {
            $this->error('Read-only guard failed: restaurant data changed during the audit.');

            return self::FAILURE;
        }

        $summary = $this->summary($rows);
        $report = [
            'audited_at' => now('Europe/Paris')->toIso8601String(),
            'read_only' => true,
            'selection' => 'Published restaurants for which CityPageResolver::cityForCode() returns null.',
            'summary' => $summary,
            'restaurants' => $rows,
        ];

        if ($this->option('json')) {
            $this->output->write(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->writeMarkdown($report, base_path((string) $this->option('out')));
        $this->writeCsv($rows, base_path((string) $this->option('csv-out')));
        $this->info('Read-only audit completed for '.count($rows).' restaurant(s).');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function row(Restaurant $restaurant, AdministrativeGeography $administrative, CityPageResolver $cities): array
    {
        $cityCode = trim((string) $restaurant->city_code);
        $reference = $cityCode === ''
            ? 'non applicable : code INSEE absent'
            : ($administrative->resolve($cityCode) === null ? 'aucune correspondance administrative locale' : 'correspondance administrative locale trouvée');
        [$classification, $failure, $action, $confidence, $notes] = $this->classify($restaurant, $administrative, $cities);

        return [
            'restaurant_id' => $restaurant->id,
            'legacy_wp_id' => $restaurant->legacy_wp_id,
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'status' => $restaurant->status,
            'address_line1' => $restaurant->address_line1,
            'postal_code' => $restaurant->postal_code,
            'city_name' => $restaurant->city_name,
            'city_code' => $restaurant->city_code,
            'country_code' => $restaurant->country_code,
            'latitude' => $restaurant->latitude,
            'longitude' => $restaurant->longitude,
            'classification' => $classification,
            'resolver_failure' => $failure,
            'administrative_reference_match' => $reference,
            'recommended_action' => $action,
            'confidence' => $confidence,
            'notes' => $notes,
        ];
    }

    /** @return array{string, string, string, string, string} */
    private function classify(Restaurant $restaurant, AdministrativeGeography $administrative, CityPageResolver $cities): array
    {
        $cityCode = trim((string) $restaurant->city_code);
        $country = strtoupper(trim((string) $restaurant->country_code));

        if ($country !== '' && $country !== 'FR') {
            return ['E — Commune étrangère / hors référentiel France', 'country_outside_french_reference', 'Décision métier : confirmer la prise en charge de ce pays avant toute résolution.', 'high', 'Le référentiel local est français ; aucun code ne doit être déduit automatiquement.'];
        }

        if ($cityCode === '') {
            if ($this->structuredFieldsAreIncomplete($restaurant)) {
                return ['F — Données structurées incomplètes', 'city_code_absent_and_required_location_fields_incomplete', 'Compléter les données structurées depuis une source validée ; ne pas inférer de code automatiquement.', 'high', 'Au moins un champ de localisation requis est absent.'];
            }

            if ($this->isManifestlyNonAdministrativeValue($restaurant)) {
                return ['G — Valeur manifestement anormale ou donnée parasite', 'city_code_absent_with_non_administrative_city_value', 'Vérification manuelle de la fiche et de la source avant toute correction ; aucun code proposé.', 'high', 'Le libellé de ville est manifestement un quartier, une adresse postale ou une valeur de laboratoire.'];
            }

            return ['A — city_code absent', 'city_code_absent', 'Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement.', 'high', 'Les autres champs structurés sont présents, mais ils ne constituent pas une identité communale suffisante.'];
        }

        if ($administrative->resolve($cityCode) === null) {
            return ['B — city_code invalide ou inconnu', 'city_code_not_resolved_by_local_administrative_reference', 'Vérifier le code dans la source administrative ; ne pas le remplacer automatiquement.', 'high', 'Le code ne peut pas être résolu par le référentiel administratif local.'];
        }

        $canonical = $administrative->canonicalCityCode($cityCode);
        if ($canonical !== $cityCode) {
            return ['D — Code arrondissement / ancienne représentation territoriale', 'canonical_city_code_not_published_by_resolver', 'Vérifier la disponibilité de la commune canonique dans les données V2 avant toute décision.', 'medium', 'Le code est canoniquement ramené à '.$canonical.'.'];
        }

        $candidate = $cities->cityForCode($cityCode);
        if ($candidate !== null && ! $this->sameCityName((string) $restaurant->city_name, (string) $candidate->city_name)) {
            return ['C — city_name / city_code incohérents', 'city_name_differs_from_local_canonical_city_label', 'Vérifier la divergence dans la source ; aucune réécriture automatique.', 'medium', 'Le libellé diffère de la ville canonique actuellement résolue dans V2.'];
        }

        return ['H — Bug probable du resolver', 'administrative_code_present_but_city_page_resolver_returned_null', 'Investigation logicielle du resolver requise ; ne pas modifier la fiche.', 'medium', 'Le code semble administrativement résoluble, mais CityPageResolver ne retourne aucune ville.'];
    }

    private function structuredFieldsAreIncomplete(Restaurant $restaurant): bool
    {
        return blank($restaurant->address_line1) || blank($restaurant->postal_code) || blank($restaurant->city_name)
            || $restaurant->latitude === null || $restaurant->longitude === null;
    }

    private function isManifestlyNonAdministrativeValue(Restaurant $restaurant): bool
    {
        $city = Str::of((string) $restaurant->city_name)->ascii()->lower()->squish()->value();

        return in_array($city, ['auber lab', 'toulouse mirail', 'paris la defense cedex'], true)
            || Str::contains(Str::lower((string) $restaurant->name), 'quick lab');
    }

    private function sameCityName(string $left, string $right): bool
    {
        return Str::of($left)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->value()
            === Str::of($right)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();
    }

    /** @param list<array<string, mixed>> $rows @return array<string, mixed> */
    private function summary(array $rows): array
    {
        $categories = collect($rows)->countBy('classification')->sortKeys()->all();
        $normal = 0;
        $resolver = collect($rows)->where('classification', 'H — Bug probable du resolver')->count();
        $business = collect($rows)->where('classification', 'E — Commune étrangère / hors référentiel France')->count();
        $ambiguous = collect($rows)->filter(fn (array $row): bool => $row['classification'] === 'G — Valeur manifestement anormale ou donnée parasite')->pluck('restaurant_id')->values()->all();

        return [
            'total_analyzed' => count($rows),
            'by_category' => $categories,
            'normal_or_acceptable' => $normal,
            'potential_data_corrections' => count($rows) - $normal - $resolver - $business,
            'potential_resolver_bugs' => $resolver,
            'business_decisions_required' => $business,
            'ambiguous_restaurant_ids' => $ambiguous,
        ];
    }

    /** @param array<string, mixed> $report */
    private function writeMarkdown(array $report, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        $summary = $report['summary'];
        $lines = [
            '# Audit des restaurants sans ville résolue',
            '',
            '- Exécuté : '.$report['audited_at'],
            '- Sélection : '.$report['selection'],
            '- Garantie : lecture seule V2 ; aucun accès legacy, aucune redirection ou donnée métier modifiée.',
            '',
            '## Synthèse',
            '',
            '- Total analysé : '.$summary['total_analyzed'],
            '- Cas normaux / acceptables : '.$summary['normal_or_acceptable'],
            '- Corrections de données potentielles : '.$summary['potential_data_corrections'],
            '- Bugs resolver potentiels : '.$summary['potential_resolver_bugs'],
            '- Décisions métier nécessaires : '.$summary['business_decisions_required'],
            '- Cas ambigus : '.($summary['ambiguous_restaurant_ids'] === [] ? 'aucun' : implode(', ', $summary['ambiguous_restaurant_ids'])),
            '',
            '### Répartition par catégorie',
            '',
        ];
        foreach ($summary['by_category'] as $category => $count) $lines[] = '- '.$category.' : '.$count;
        $lines[] = '';
        $lines[] = '## Détail';
        $lines[] = '';
        $lines[] = '| ID | Nom | Ville | Code ville | Catégorie | Échec resolver | Action recommandée |';
        $lines[] = '| ---: | --- | --- | --- | --- | --- | --- |';
        foreach ($report['restaurants'] as $row) {
            $lines[] = sprintf('| %s | %s | %s | %s | %s | %s | %s |', $row['restaurant_id'], $this->markdown($row['name']), $this->markdown($row['city_name']), $this->markdown($row['city_code']), $this->markdown($row['classification']), $this->markdown($row['resolver_failure']), $this->markdown($row['recommended_action']));
        }
        $lines[] = '';
        $lines[] = 'Le CSV compagnon contient les champs structurés complets de chaque ligne. Aucune recommandation ne constitue une correction automatique.';
        File::put($path, implode("\n", $lines)."\n");
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeCsv(array $rows, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, array_keys($rows[0] ?? array_fill_keys(self::FIELDS, '')));
        foreach ($rows as $row) fputcsv($handle, $row);
        fclose($handle);
    }

    private function markdown(mixed $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], (string) ($value ?? ''));
    }

    private function fingerprint(): string
    {
        return hash('sha256', Restaurant::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->get(['id', 'city_code', 'city_name', 'postal_code', 'address_line1', 'country_code', 'latitude', 'longitude', 'updated_at'])
            ->toJson(JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
