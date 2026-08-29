<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingConfidence;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ResolveAddressExceptionsCommand extends Command
{
    protected $signature = 'data:resolve-address-exceptions {--apply} {--ids= : Comma-separated V2 IDs} {--out=docs/generated/address-exceptions-report.md}';

    public function handle(GeocodingService $geo, GeocodingConfidence $confidence): int
    {
        $ids = collect(explode(',', (string) $this->option('ids')))->map(fn ($id) => (int) trim($id))->filter()->all();
        $rows = Restaurant::with('locations')->where(function ($q) {
            foreach (['address_line1', 'postal_code', 'city_name', 'city_code', 'country_code'] as $field) $q->orWhereNull($field)->orWhere($field, '');
        })->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderBy('id')->get();
        $report = [];

        foreach ($rows as $r) {
            $direct = blank($r->address) ? ['ok' => false, 'features' => [], 'error' => 'empty_address', 'cached' => true] : $geo->search($r->address);
            $reverse = $r->latitude === null || $r->longitude === null ? ['ok' => false, 'features' => [], 'error' => 'missing_gps', 'cached' => true] : $geo->reverse((float) $r->latitude, (float) $r->longitude);
            $candidate = $direct['features'][0] ?? $reverse['features'][0] ?? null;
            $complete = $candidate && $candidate['postcode'] && $candidate['city'] && $candidate['citycode'];
            $geography = $r->locations->pluck('name')->filter()->values()->all();
            $compatible = $geography === [] || ! $complete || collect($geography)->contains(fn ($name) => $confidence->sameCity($name, $candidate['city']));
            $reason = $this->reason($r, $candidate, $complete, $compatible);
            $applied = false;

            if ($this->option('apply') && $complete && $compatible && in_array($candidate['type'], ['housenumber', 'street'], true)) {
                $line = $this->line($r->address, $candidate['label']);
                $fields = [
                    'address_line1' => $r->address_line1 ?: $line,
                    'postal_code' => $r->postal_code ?: $candidate['postcode'],
                    'city_name' => $r->city_name ?: $candidate['city'],
                    'city_code' => $r->city_code ?: $candidate['citycode'],
                    'country_code' => $r->country_code ?: 'FR',
                    'geocoding_provider' => $r->geocoding_provider ?: 'geoplateforme',
                    'geocoding_source_id' => $r->geocoding_source_id ?: $candidate['id'],
                    'geocoding_precision' => $r->geocoding_precision ?: $candidate['type'],
                    'geocoding_score' => $r->geocoding_score ?: $candidate['score'],
                    'geocoded_at' => $r->geocoded_at ?: now(),
                    'geocoding_review_reason' => $reason,
                ];
                $decision = $confidence->decide($candidate, null, null, null, $r->latitude !== null, false, false);
                $fields['geocoding_status'] = $decision['status'];
                if ($r->latitude === null && $candidate['type'] === 'housenumber' && (float) $candidate['score'] >= .80) {
                    $fields['latitude'] = $candidate['latitude']; $fields['longitude'] = $candidate['longitude'];
                }
                $r->forceFill(array_filter($fields, fn ($v) => $v !== null))->save();
                $applied = true;
            }
            $report[] = ['id' => $r->id, 'legacy_wp_id' => $r->legacy_wp_id, 'name' => $r->name, 'status_before' => $r->geocoding_status, 'gps_before' => $r->latitude !== null && $r->longitude !== null, 'geography' => $geography, 'direct' => $direct['features'][0] ?? null, 'reverse' => $reverse['features'][0] ?? null, 'reason' => $reason, 'applied' => $applied, 'legacy' => $this->legacy((int) $r->legacy_wp_id)];
        }
        $this->write($report);
        return self::SUCCESS;
    }

    private function reason(Restaurant $r, ?array $candidate, bool $complete, bool $compatible): string
    {
        if (blank($r->address)) return 'données insuffisantes : adresse absente';
        if (! $candidate) return $r->latitude === null ? 'sans GPS et géocodage direct absent' : 'géocodage direct/reverse absent';
        if (! $complete) return 'géocodage incomplet (CP, ville ou code INSEE absent)';
        if (! $compatible) return 'ambigu : Geography incompatible';
        if (! in_array($candidate['type'], ['housenumber', 'street'], true)) return 'précision insuffisante : '.$candidate['type'];
        return 'résolu automatiquement';
    }

    private function line(?string $raw, ?string $label): ?string
    {
        $raw = trim((string) strtok((string) $raw, ','));
        if (preg_match('/^\d+[\p{L}\d\s\-' . "'" . ']+$/u', $raw)) return $raw;
        return $label ? trim((string) preg_replace('/\s+(?:\d{5}|97\d{3}|98\d{3})\s+.*$/u', '', $label)) : null;
    }

    private function legacy(int $id): array
    {
        $post = DB::connection('legacy_wp')->table('posts')->where('ID', $id)->first(['post_date_gmt', 'post_author']);
        $meta = DB::connection('legacy_wp')->table('postmeta')->where('post_id', $id)->whereIn('meta_key', ['lp_listingpro_options', 'listingpro_options', 'phone', 'email', 'website'])->pluck('meta_value', 'meta_key')->all();
        return ['exists' => $post !== null, 'date' => $post?->post_date_gmt, 'author' => $post?->post_author, 'meta_keys' => array_keys($meta)];
    }

    private function write(array $items): void
    {
        $lines = ['# Exceptions adresses — Phase 5.1', '', '| V2 | Legacy | Restaurant | GPS initial | Résultat | Raison |', '|---:|---:|---|---|---|---|'];
        foreach ($items as $item) $lines[] = '| '.$item['id'].' | '.$item['legacy_wp_id'].' | '.str_replace('|', '/', $item['name']).' | '.($item['gps_before'] ? 'oui' : 'non').' | '.($item['applied'] ? 'résolu' : 'à traiter').' | '.$item['reason'].' |';
        File::put(base_path($this->option('out')), implode("\n", $lines)."\n");
        File::put(base_path(str_replace('.md', '.json', $this->option('out'))), json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
