<?php

namespace App\Services\Quick;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Reads the server-rendered Next.js directory published by quick.fr. */
class QuickRestaurantSource
{
    public const DIRECTORY_URL = 'https://www.quick.fr/tous-les-quicks';

    /** @return array<int, array<string, mixed>> */
    public function restaurants(): array
    {
        try {
            $html = Http::accept('text/html,application/xhtml+xml')
                ->withUserAgent('Top-Halal Quick audit/1.0 (+https://top-halal.fr)')
                ->timeout(20)->retry(2, 800, throw: false)
                ->get(self::DIRECTORY_URL);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Quick directory request failed: '.$e->getMessage(), previous: $e);
        }

        if (! $html->successful()) {
            throw new \RuntimeException('Quick directory returned HTTP '.$html->status());
        }

        if (! preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/si', $html->body(), $match)) {
            throw new \RuntimeException('Quick directory no longer exposes __NEXT_DATA__.');
        }

        try { $data = json_decode(html_entity_decode($match[1]), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \RuntimeException('Quick embedded JSON is invalid.', previous: $e); }

        $queries = data_get($data, 'props.pageProps.dehydratedState.queries', []);
        $query = collect($queries)->first(fn (array $query) => data_get($query, 'queryKey.0') === 'restaurants');
        $restaurants = data_get($query, 'state.data');
        if (! is_array($restaurants) || $restaurants === []) throw new \RuntimeException('Quick embedded restaurant dataset is empty or changed.');

        return array_map(fn (array $row) => $this->normalise($row), $restaurants);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function normalise(array $row): array
    {
        $a = $row['attributes'] ?? [];
        $slug = (string) ($a['slug'] ?? '');
        $services = collect($a)->filter(fn ($v, $key) => in_array((string) $v, ['Oui', 'Yes'], true)
            && ! in_array($key, ['halal', 'certifHalal'], true))->keys()->values()->all();
        $hours = collect(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])
            ->mapWithKeys(fn ($day) => [$day => $a['dining'.ucfirst($day)] ?? null])->filter()->all();
        $halal = ((string) ($a['halal'] ?? '') === 'Oui' || (string) ($a['certifHalal'] ?? '') === 'Oui') ? 'halal_confirmed' : 'halal_not_found';
        $evidence = $halal === 'halal_confirmed' ? 'Quick embedded dataset: halal='.($a['halal'] ?? '').'; certifHalal='.($a['certifHalal'] ?? '') : null;

        return [
            'quick_name' => 'Quick '.trim((string) ($a['name'] ?? '')),
            'quick_slug' => $slug,
            'quick_url' => 'https://www.quick.fr/restaurants/'.$slug,
            'quick_address' => trim(implode(', ', array_filter([$a['address1'] ?? null, $a['address2'] ?? null, trim(($a['postalCode'] ?? '').' '.($a['city'] ?? ''))]))),
            'quick_address_line1' => trim(implode(' ', array_filter([$a['address1'] ?? null, $a['address2'] ?? null]))),
            'quick_postal_code' => $a['postalCode'] ?? null,
            'quick_city' => $a['city'] ?? null,
            'quick_phone' => $a['phone'] ?? null,
            'quick_latitude' => $a['lat'] ?? null,
            'quick_longitude' => $a['lng'] ?? null,
            'quick_opening_hours' => $hours,
            'quick_services' => $services,
            'quick_halal_status' => $halal,
            'quick_halal_certifier' => $a['halalCertifier'] ?? null,
            'quick_halal_evidence' => $evidence,
            'quick_source_id' => $a['fr'] ?? null,
            'quick_raw' => $row,
        ];
    }
}
