<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** A deliberately read-only audit. It only writes the requested report artifacts. */
class AuditAddressesCommand extends Command
{
    protected $signature = 'data:audit-addresses
        {--sample=100 : Maximum deterministic sample size (1-100)}
        {--out=docs/generated/address-gps-audit.md : Markdown report path}
        {--sample-out=docs/generated/address-gps-sample.csv : CSV sample path}
        {--legacy-connection=legacy_wp : Read-only WordPress connection}';

    protected $description = 'Read-only audit of migrated restaurant address, GPS and geography data.';

    public function handle(): int
    {
        $run = (string) Str::uuid();
        $limit = min(100, max(1, (int) $this->option('sample')));
        $restaurants = DB::table('restaurants as r')
            ->leftJoin('restaurant_location as rl', 'rl.restaurant_id', '=', 'r.id')
            ->leftJoin('locations as l', 'l.id', '=', 'rl.location_id')
            ->select('r.id', 'r.legacy_wp_id', 'r.name', 'r.status', 'r.address', 'r.postal_code', 'r.city_name', 'r.latitude', 'r.longitude', 'r.legacy_published_at', 'l.id as location_id', 'l.name as location_name', 'l.slug as location_slug')
            ->orderBy('r.id')->get()->groupBy('id');

        $rows = $restaurants->map(function ($group) {
            $r = $group->first();
            $locations = $group->filter(fn ($x) => $x->location_id !== null)->map(fn ($x) => ['id' => (int) $x->location_id, 'name' => (string) $x->location_name, 'slug' => (string) $x->location_slug])->unique('id')->values()->all();
            return (object) [...(array) $r, 'locations' => $locations];
        })->values();

        $summary = $this->summarize($rows);
        $legacy = $this->legacyInventory((string) $this->option('legacy-connection'));
        $sample = $this->sample($rows, $limit);
        $report = $this->markdown($run, $summary, $legacy, $sample, $limit);

        File::ensureDirectoryExists(dirname(base_path((string) $this->option('out'))));
        File::ensureDirectoryExists(dirname(base_path((string) $this->option('sample-out'))));
        File::put(base_path((string) $this->option('out')), $report);
        $this->writeCsv(base_path((string) $this->option('sample-out')), $sample);
        $this->info("Read-only audit {$run}: {$summary['total']} restaurants; {$summary['gps_present']} with GPS.");
        return self::SUCCESS;
    }

    private function summarize($rows): array
    {
        $s = array_fill_keys(['total','published','other_status','address_present','address_empty','city_present','postal_present','complete_address','address_no_postal','address_no_city','city_only','postal_only','gps_present','latitude_only','longitude_only','gps_absent','gps_zero','gps_out_of_range','gps_swapped','gps_france','gps_dom','gps_outside_france','geography_linked','geography_missing','multiple_geography','geography_mismatch','invalid_text','conflict','invalid','duplicate_coordinate_restaurants'], 0);
        $coordinates = []; $anomalies = []; $duplicates = ['same_name_address' => [], 'same_name_gps' => [], 'same_address_different_names' => []];
        foreach ($rows as $r) {
            $s['total']++; $s[$r->status === 'published' ? 'published' : 'other_status']++;
            $address = $this->present($r->address); $city = $this->present($r->city_name); $postal = $this->present($r->postal_code);
            $s[$address ? 'address_present' : 'address_empty']++; if ($city) $s['city_present']++; if ($postal) $s['postal_present']++;
            if ($address && $city && $postal) $s['complete_address']++;
            if ($address && ! $postal) $s['address_no_postal']++; if ($address && ! $city) $s['address_no_city']++;
            if (! $address && $city && ! $postal) $s['city_only']++; if (! $address && ! $city && $postal) $s['postal_only']++;
            $lat = $r->latitude === null ? null : (float) $r->latitude; $lng = $r->longitude === null ? null : (float) $r->longitude;
            $hasLat = $lat !== null; $hasLng = $lng !== null; $gps = $hasLat && $hasLng;
            if ($gps) $s['gps_present']++; elseif ($hasLat) $s['latitude_only']++; elseif ($hasLng) $s['longitude_only']++; else $s['gps_absent']++;
            $gpsIssue = false;
            if ($gps) {
                if ($lat == 0.0 && $lng == 0.0) { $s['gps_zero']++; $gpsIssue = true; }
                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) { $s['gps_out_of_range']++; $gpsIssue = true; }
                if (abs($lat) <= 10 && $lng >= 41 && $lng <= 52) { $s['gps_swapped']++; $gpsIssue = true; }
                if ($this->metropolitanFrance($lat, $lng)) $s['gps_france']++; elseif ($this->domTom($lat, $lng)) $s['gps_dom']++; else $s['gps_outside_france']++;
                $coordinates[sprintf('%.7F,%.7F', $lat, $lng)][] = $r;
            }
            $locationMismatch = $city && $r->locations !== [] && ! collect($r->locations)->contains(fn ($l) => $this->normal($l['name']) === $this->normal($r->city_name));
            if ($r->locations === []) $s['geography_missing']++; else { $s['geography_linked']++; if (count($r->locations) > 1) $s['multiple_geography']++; }
            if ($locationMismatch) $s['geography_mismatch']++;
            $textIssues = $this->textIssues([$r->address, $r->city_name, $r->postal_code]); if ($textIssues !== []) $s['invalid_text']++;
            $class = $locationMismatch || $gpsIssue ? 'CONFLICT' : ($address && $city && $postal ? ($gps ? 'COMPLETE_WITH_GPS' : 'COMPLETE_NO_GPS') : (!$address && !$city && !$postal && $gps ? 'GPS_ONLY' : (!$address && $city && !$postal ? 'CITY_ONLY' : (($address || $city || $postal) ? ($gps ? 'PARTIAL_WITH_GPS' : 'PARTIAL_NO_GPS') : 'INVALID'))));
            if ($class === 'CONFLICT') $s['conflict']++; if ($class === 'INVALID') $s['invalid']++;
            $r->classification = $class; $r->issues = array_merge($textIssues, $locationMismatch ? ['city_geography_mismatch'] : [], $gpsIssue ? ['gps_suspect'] : []);
            foreach ($r->issues as $issue) if (count($anomalies[$issue] ?? []) < 20) $anomalies[$issue][] = $this->brief($r);
            if ($address) $duplicates['same_name_address'][$this->normal($r->name).'|'.$this->normal($r->address)][] = $this->brief($r);
            if ($gps) $duplicates['same_name_gps'][$this->normal($r->name).'|'.sprintf('%.7F,%.7F', $lat, $lng)][] = $this->brief($r);
            if ($address) $duplicates['same_address_different_names'][$this->normal($r->address)][] = $this->brief($r);
        }
        $clusters = collect($coordinates)->filter(fn ($x) => count($x) > 1); $s['coordinate_unique'] = count($coordinates); $s['coordinate_used_by_2'] = $clusters->filter(fn ($x) => count($x) === 2)->count(); $s['coordinate_used_by_3_plus'] = $clusters->filter(fn ($x) => count($x) >= 3)->count();
        $s['duplicate_coordinate_restaurants'] = $clusters->sum(fn ($x) => count($x));
        $s['clusters'] = $clusters->sortByDesc(fn ($x) => count($x))->take(50)->map(fn ($x, $coordinate) => ['coordinate' => $coordinate, 'count' => count($x), 'restaurants' => collect($x)->take(10)->map(fn ($r) => $this->brief($r))->all()])->values()->all();
        $s['anomalies'] = $anomalies;
        $s['duplicates'] = collect($duplicates)->map(fn ($groups) => collect($groups)->filter(fn ($g) => count($g) > 1)->sortByDesc(fn ($g) => count($g))->take(50)->map(fn ($g) => $g)->values()->all())->all();
        $s['classes'] = $rows->countBy('classification')->all();
        return $s;
    }

    private function legacyInventory(string $connection): array
    {
        $db = DB::connection($connection); $prefix = $db->getTablePrefix();
        $columns = fn (string $table) => array_map(fn ($x) => (array) $x, $db->select('SELECT column_name, column_type, is_nullable FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position', [$db->getDatabaseName(), $prefix.$table]));
        $meta = $db->table('postmeta as m')->join('posts as p', 'p.ID', '=', 'm.post_id')->where('p.post_type', 'listing')->where(function ($q) { foreach (['address','city','town','postcode','postal','zip','latitude','longitude','location','map'] as $needle) $q->orWhere('m.meta_key', 'like', '%'.$needle.'%'); })->select('m.meta_key', DB::raw('COUNT(*) as occurrences'))->groupBy('m.meta_key')->orderByDesc('occurrences')->get()->map(fn ($x) => (array) $x)->all();
        return ['legacy_tables' => ['posts' => $columns('posts'), 'postmeta' => $columns('postmeta'), 'terms' => $columns('terms'), 'term_taxonomy' => $columns('term_taxonomy')], 'meta_key_candidates' => $meta, 'listing_count' => $db->table('posts')->where('post_type', 'listing')->count()];
    }

    private function sample($rows, int $limit): array
    {
        $selected = []; foreach (['COMPLETE_WITH_GPS','COMPLETE_NO_GPS','PARTIAL_WITH_GPS','PARTIAL_NO_GPS','CITY_ONLY','GPS_ONLY','CONFLICT','INVALID'] as $class) foreach ($rows->where('classification', $class)->sortBy('legacy_wp_id')->take(15) as $r) $selected[$r->id] = $r;
        foreach ($rows->filter(fn ($r) => $r->issues !== [])->sortBy('legacy_wp_id') as $r) { if (count($selected) >= $limit) break; $selected[$r->id] = $r; }
        foreach ($rows->sortBy('legacy_wp_id') as $r) { if (count($selected) >= $limit) break; $selected[$r->id] = $r; }
        return array_slice(array_values($selected), 0, $limit);
    }

    private function markdown(string $run, array $s, array $legacy, array $sample, int $limit): string
    {
        $g = fn ($k) => $s[$k] ?? 0; $lines = ['# Audit adresses et GPS — Top-Halal V2', '', "Run UUID : `{$run}`", 'Mode : `strictement lecture seule` (les seules écritures sont les deux artefacts de rapport).', '', '## Résumé', '', "TOTAL RESTAURANTS : {$g('total')}", '', "- Adresse complète + GPS : {$g('classes')['COMPLETE_WITH_GPS'] ?? 0}", "- Adresse complète sans GPS : {$g('classes')['COMPLETE_NO_GPS'] ?? 0}", "- Adresse partielle + GPS : {$g('classes')['PARTIAL_WITH_GPS'] ?? 0}", "- Adresse partielle sans GPS : {$g('classes')['PARTIAL_NO_GPS'] ?? 0}", "- Ville seule : {$g('classes')['CITY_ONLY'] ?? 0}", "- GPS seul : {$g('classes')['GPS_ONLY'] ?? 0}", "- Sans adresse ni GPS / invalide : {$g('classes')['INVALID'] ?? 0}", "- Conflits locaux : {$g('conflict')}", '', "- Coordonnées présentes : {$g('gps_present')}", "- Coordonnées absentes : {$g('gps_absent')}", "- Coordonnées invalides ou suspectes : ".($g('gps_zero') + $g('gps_out_of_range') + $g('gps_swapped')), "- Restaurants à coordonnées dupliquées : {$g('duplicate_coordinate_restaurants')}", '', "- Geography liée : {$g('geography_linked')}", "- Sans Geography : {$g('geography_missing')}", "- Mismatch ville / Geography : {$g('geography_mismatch')}", '', '## Inventaire des champs', '', '### V2', '', '| Table | Champ | Type / nullable | Exemple |', '| --- | --- | --- | --- |', '| restaurants | address | varchar / nullable | valeur non exposée dans ce rapport |', '| restaurants | postal_code | varchar(20) / nullable, indexé | valeur non exposée |', '| restaurants | city_name | varchar / nullable, indexé | valeur non exposée |', '| restaurants | latitude, longitude | decimal(10,7) / nullable | valeur non exposée |', '| restaurants | legacy_wp_id | bigint / non nullable, unique | identifiant de réconciliation |', '| locations + restaurant_location | name, slug, parent_id | relation N:N ; aucun code INSEE/département/région dédié | relation géographique legacy |', '', '### Legacy WordPress', '', "- Listings lus : {$legacy['listing_count']}. Les coordonnées/adresses sont stockées dans `{$this->legacyTable('postmeta')}` (notamment les payloads sérialisés `lp_listingpro_options` / `lp_listingpro_options_fields`), pas dans les options WordPress globales.", '- Champs physiques pertinents : `posts.ID`, `posts.post_status`, `posts.post_name`, `postmeta.post_id/meta_key/meta_value`, `terms.name/slug`, `term_taxonomy.taxonomy/parent`.', '- Aucun champ INSEE, département, région, pays, ligne 2 ou précision de géocodage n’existe dans le modèle V2 actuel.', '', 'Meta keys legacy candidates (occurrences) :'];
        foreach ($legacy['meta_key_candidates'] as $x) $lines[] = "- `{$x['meta_key']}` : {$x['occurrences']}";
        $lines = array_merge($lines, ['', '## Compteurs détaillés', '', "- Publiés : {$g('published')} ; autres statuts : {$g('other_status')}.", "- Adresse présente : {$g('address_present')} ; vide : {$g('address_empty')} ; ville : {$g('city_present')} ; code postal : {$g('postal_present')}.", "- Adresse + code postal + ville : {$g('complete_address')} ; adresse sans CP : {$g('address_no_postal')} ; adresse sans ville : {$g('address_no_city')} ; CP seul : {$g('postal_only')}.", "- Latitude seule : {$g('latitude_only')} ; longitude seule : {$g('longitude_only')} ; 0,0 : {$g('gps_zero')} ; hors plage GPS : {$g('gps_out_of_range')} ; possiblement inversées : {$g('gps_swapped')}.", "- France métropolitaine : {$g('gps_france')} ; DOM/TOM : {$g('gps_dom')} ; hors France : {$g('gps_outside_france')}.", "- Coordonnées uniques : {$g('coordinate_unique')} ; utilisées par 2 : {$g('coordinate_used_by_2')} ; par 3+ : {$g('coordinate_used_by_3_plus')}.", "- Plusieurs Geography : {$g('multiple_geography')}. La compatibilité CP/Geography est non déterminable localement : `locations` ne porte aucun code postal.", '', '## Anomalies (exemples plafonnés à 20)', '']);
        foreach ($s['anomalies'] as $code => $items) { $lines[] = "### {$code}"; foreach ($items as $x) $lines[] = '- '.json_encode($x, JSON_UNESCAPED_UNICODE); $lines[] = ''; }
        $lines[] = '## Clusters GPS dupliqués (50 plus grands)'; $lines[] = ''; foreach ($s['clusters'] as $x) $lines[] = '- `'.$x['coordinate'].'` : '.$x['count'].' restaurant(s) — '.json_encode($x['restaurants'], JSON_UNESCAPED_UNICODE);
        $lines[] = ''; $lines[] = '## Doublons potentiels (groupes plafonnés à 50)'; $lines[] = ''; foreach ($s['duplicates'] as $kind => $groups) { $lines[] = "### {$kind}"; foreach ($groups as $group) $lines[] = '- '.json_encode($group, JSON_UNESCAPED_UNICODE); $lines[] = ''; }
        $lines = array_merge($lines, ['## Index actuels et recommandations', '', '- Actuels : index sur `status`, `postal_code`, `city_name`, unicité `legacy_wp_id`; aucune indexation GPS dédiée. Les pivots ont une clé primaire `(restaurant_id, location_id)`.', '- Phase 2 : index composite de recherche/qualité sur `(status, city_name, postal_code)`, index batch sur `geocoding_status` une fois créé, et index spatial/stratégie de proximité validée contre le plan MariaDB. Ne pas créer avant la conception de la phase 2.', '', '## Modèle cible proposé (non implémenté)', '', '- Réutiliser `address` comme `address_raw` pendant une transition contrôlée; conserver `postal_code`, `city_name`, `latitude`, `longitude`.', '- Ajouter : `address_line1`, `address_line2`, `country_code`, `geocoding_provider`, `geocoding_source_id`, `geocoding_precision`, `geocoding_status`, `geocoded_at`, `manually_verified_at` et une trace de décision/audit.', '- Les régions, départements et codes INSEE devraient provenir d’une source de référence versionnée et ne doivent pas être déduits aveuglément du texte.', '', '## Triage recommandé pour la phase 2', '', "- KEEP AS IS : {$g('classes')['COMPLETE_WITH_GPS'] ?? 0} à échantillonner par reverse-geocoding, sans écriture automatique.", "- AUTO-GEOCODE HIGH CONFIDENCE : {$g('classes')['COMPLETE_NO_GPS'] ?? 0} adresses complètes, seulement après pilote Géoplateforme et seuil strict.", "- AUTO-REVERSE-GEOCODE : {$g('classes')['GPS_ONLY'] ?? 0} GPS seuls + les {$g('classes')['PARTIAL_WITH_GPS'] ?? 0} partiels avec GPS, pour enrichissement proposé et non appliqué.", "- MANUAL REVIEW : {$g('conflict')} conflits + {$g('invalid_text')} textes anormaux + clusters GPS massifs.", "- UNUSABLE : {$g('classes')['INVALID'] ?? 0} sans adresse, ville, CP ni GPS.", '', "## Échantillon déterministe", '', "Le CSV associé contient ".count($sample)." enregistrement(s), plafonné à {$limit}; sélection par classification, anomalies puis `legacy_wp_id` croissant."]);
        return implode("\n", $lines)."\n";
    }

    private function writeCsv(string $path, array $sample): void { $out = fopen($path, 'w'); fputcsv($out, ['v2_id','legacy_wp_id','status','name','address','postal_code','city_name','latitude','longitude','geography','classification','issues','legacy_published_at']); foreach ($sample as $r) fputcsv($out, [$r->id,$r->legacy_wp_id,$r->status,$r->name,$r->address,$r->postal_code,$r->city_name,$r->latitude,$r->longitude,implode(' | ', array_column($r->locations, 'name')),$r->classification,implode(' | ', $r->issues),$r->legacy_published_at]); fclose($out); }
    private function present(?string $value): bool { return trim((string) $value) !== ''; }
    private function normal(?string $value): string { return preg_replace('/[^a-z0-9]/', '', Str::ascii(Str::lower(trim((string) $value)))) ?: ''; }
    private function metropolitanFrance(float $lat, float $lng): bool { return $lat >= 41.0 && $lat <= 51.5 && $lng >= -5.6 && $lng <= 10.0; }
    private function domTom(float $lat, float $lng): bool { foreach ([[14,19,-64,-59],[-23,-20,54,56],[-22,-20,164,167],[2,6,-55,-50],[-13,-11,43,46]] as [$a,$b,$c,$d]) if ($lat >= $a && $lat <= $b && $lng >= $c && $lng <= $d) return true; return false; }
    private function textIssues(array $values): array { $v = implode(' ', array_filter($values)); $issues = []; if (preg_match('/&(?:amp|quot|#\d+);/i', $v)) $issues[] = 'html_entity'; if (preg_match('/(?:Ã.|â€™|â€œ|�)/u', $v)) $issues[] = 'mojibake'; if (mb_strlen($v) > 255) $issues[] = 'extremely_long'; if (preg_match('/https?:\/\/|www\./i', $v)) $issues[] = 'url_in_address'; if (preg_match('/<script|union\s+select|<\/?[a-z]+/i', $v)) $issues[] = 'hostile_payload'; if (preg_match('/\b(?:n\/?a|nc|inconnu|none)\b/i', trim($v)) || preg_match('/^[\s.\-_,]+$/', $v)) $issues[] = 'placeholder'; if (preg_match('/\b\d{2,3}[,.]\d{3,}\s*[,;]\s*-?\d{1,3}[,.]\d+/',$v)) $issues[] = 'gps_in_address'; if (preg_match('/\b(?:\+?33|0\d)(?:[ .-]?\d{2}){4}\b|[\w.+-]+@[\w.-]+\.[a-z]{2,}/i', $v)) $issues[] = 'contact_in_address'; return $issues; }
    private function brief($r): array { return ['v2_id' => (int) $r->id, 'legacy_wp_id' => (int) $r->legacy_wp_id, 'name' => Str::limit((string) $r->name, 80, ''), 'city' => Str::limit((string) $r->city_name, 60, '')]; }
    private function legacyTable(string $table): string { return DB::connection((string) $this->option('legacy-connection'))->getTablePrefix().$table; }
}
