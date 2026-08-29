<?php

namespace App\Console\Commands;

use App\Services\Geocoding\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GeocodingPilotCommand extends Command
{
    protected $signature = 'data:geocoding-pilot {--out=docs/generated/geocoding-pilot.md}';
    protected $description = 'Calls Géoplateforme for a deterministic, non-persisting address/GPS pilot.';

    public function handle(GeocodingService $geocoder): int
    {
        $rows = $this->rows(); $selected = $this->select($rows); $results = [];
        foreach ($selected as $r) {
            $direct = $this->hasAddress($r) ? $geocoder->search($r->address) : null;
            $reverse = $r->latitude !== null && $r->longitude !== null ? $geocoder->reverse((float) $r->latitude, (float) $r->longitude) : null;
            $d = $direct['features'][0] ?? null; $v = $reverse['features'][0] ?? null;
            $distance = $d && $r->latitude !== null ? $this->distance((float) $r->latitude, (float) $r->longitude, $d['latitude'], $d['longitude']) : null;
            $results[] = ['row' => $r, 'direct' => $direct, 'reverse' => $reverse, 'distance' => $distance, 'direct_class' => $distance === null ? null : ($distance <= 30 ? 'MATCH_EXCELLENT' : ($distance <= 150 ? 'MATCH_GOOD' : ($distance <= 1000 ? 'MATCH_APPROXIMATE' : 'CONFLICT'))), 'postal_match' => $d && $r->derived_postal ? $d['postcode'] === $r->derived_postal : null, 'city_match' => $d && $r->derived_city ? $this->normal($d['city']) === $this->normal($r->derived_city) : null, 'reverse_city_match' => $v && $r->derived_city ? $this->normal($v['city']) === $this->normal($r->derived_city) : null];
        }
        File::put(base_path((string) $this->option('out')), $this->report($results));
        $this->info('Pilot complete: '.count($results).' restaurants, '.collect($results)->filter(fn ($x) => $x['direct']['ok'] ?? false)->count().' direct request successes.');
        return self::SUCCESS;
    }

    private function rows()
    {
        return DB::table('restaurants as r')->leftJoin('restaurant_location as rl', 'rl.restaurant_id', '=', 'r.id')->leftJoin('locations as l', 'l.id', '=', 'rl.location_id')->select('r.*', 'l.name as geography')->orderBy('r.legacy_wp_id')->get()->groupBy('id')->map(function ($g) {
            $r = $g->first(); $r->geographies = $g->pluck('geography')->filter()->unique()->values()->all(); [$r->derived_postal, $r->derived_city] = $this->parts((string) $r->address); $gps = $r->latitude !== null && $r->longitude !== null;
            $complete = $r->address && $r->derived_postal && $r->derived_city; $mismatch = $r->derived_city && $r->geographies !== [] && ! collect($r->geographies)->contains(fn ($x) => $this->normal($x) === $this->normal($r->derived_city));
            $r->class = $mismatch ? 'CONFLICT' : ($complete ? ($gps ? 'COMPLETE_WITH_GPS' : 'COMPLETE_NO_GPS') : ($gps ? 'PARTIAL_WITH_GPS' : 'PARTIAL_NO_GPS'));
            $r->coordinate = $gps ? sprintf('%.7F,%.7F', $r->latitude, $r->longitude) : null; return $r;
        })->values();
    }
    private function select($rows): array
    {
        $out = []; $add = function ($items, $n) use (&$out) { foreach ($items as $r) { if (count($out) >= 100 || isset($out[$r->id])) continue; $out[$r->id] = $r; if (--$n === 0) break; } };
        $add($rows->where('class','COMPLETE_WITH_GPS'), 20); $add($rows->where('class','COMPLETE_NO_GPS'), 8); $add($rows->where('class','PARTIAL_WITH_GPS'), 20); $add($rows->where('class','PARTIAL_NO_GPS'), 8); $add($rows->where('class','CONFLICT'), 20);
        $clusters = $rows->filter(fn ($r) => $r->coordinate)->groupBy('coordinate')->filter(fn ($g) => $g->count() >= 3)->sortByDesc(fn ($g) => $g->count()); foreach ($clusters->take(8) as $group) $add($group, $group->count());
        return array_values($out);
    }
    private function report(array $results): string
    {
        $direct = collect($results)->filter(fn ($x) => $x['direct'] !== null); $reverse = collect($results)->filter(fn ($x) => $x['reverse'] !== null); $distances = $direct->pluck('distance')->filter(fn ($x) => $x !== null)->sort()->values();
        $count = fn ($field, $value) => $direct->filter(fn ($x) => $x[$field] === $value)->count(); $median = $distances->isEmpty() ? null : $distances[(int) floor(($distances->count()-1)/2)];
        $lines = ['# Pilote Géocodage / Reverse-géocodage', '', 'Mode : `pilote sans aucune écriture restaurants, coordonnées ou Geography`.', 'Provider : `Géoplateforme / Base Adresse Nationale`.', 'Cache : Laravel cache, 30 jours par requête normalisée ; timeout 10 s ; 2 retries ; 4 requêtes/s maximum hors cache.', '', '## Synthèse', '', '- Restaurants testés : **'.count($results).'**.', '- Requêtes directes : **'.$direct->count().'** ; succès : **'.$direct->filter(fn ($x) => $x['direct']['ok'])->count().'** ; échecs : **'.$direct->filter(fn ($x) => ! $x['direct']['ok'])->count().'**.', '- Reverse : **'.$reverse->count().'** ; succès : **'.$reverse->filter(fn ($x) => $x['reverse']['ok'])->count().'** ; échecs : **'.$reverse->filter(fn ($x) => ! $x['reverse']['ok'])->count().'**.', '- Distance GPS historique → résultat direct : médiane **'.($median === null ? 'n/a' : round($median).' m').'** ; min '.($distances->first() === null ? 'n/a' : round($distances->first())).' m ; max '.($distances->last() === null ? 'n/a' : round($distances->last())).' m.', '- Classes GPS : excellent '.$count('direct_class','MATCH_EXCELLENT').', bon '.$count('direct_class','MATCH_GOOD').', approximatif '.$count('direct_class','MATCH_APPROXIMATE').', conflit '.$count('direct_class','CONFLICT').'.', '- CP extrait identique : '.$count('postal_match',true).' / '.$direct->filter(fn ($x) => $x['postal_match'] !== null)->count().'; ville identique : '.$count('city_match',true).' / '.$direct->filter(fn ($x) => $x['city_match'] !== null)->count().'.', '', '## Résultats détaillés', ''];
        foreach ($results as $x) { $r=$x['row']; $d=$x['direct']['features'][0]??null; $v=$x['reverse']['features'][0]??null; $lines[]='### V2 #'.$r->id.' / legacy #'.$r->legacy_wp_id.' — '.$r->class; $lines[]='- Adresse brute : `'.str_replace('`','\\`',$r->address).'`; CP/ville extraits : `'.$r->derived_postal.'` / `'.$r->derived_city.'`; Geography : `'.implode(' | ',$r->geographies).'`.'; $lines[]='- GPS historique : `'.$r->latitude.', '.$r->longitude.'`; direct : '.json_encode($d,JSON_UNESCAPED_UNICODE).'; reverse : '.json_encode($v,JSON_UNESCAPED_UNICODE).'.'; $lines[]='- Distance : '.($x['distance']===null?'n/a':round($x['distance']).' m').'; classe : `'.($x['direct_class']??'n/a').'`; CP match : '.json_encode($x['postal_match']).'; ville match : '.json_encode($x['city_match']).'; reverse ville match : '.json_encode($x['reverse_city_match']).'.'; if (($x['direct']['error']??null)||($x['reverse']['error']??null)) $lines[]='- Erreur fournisseur : `'.(($x['direct']['error']??null) ?: ($x['reverse']['error']??null)).'`.'; $lines[]=''; }
        $lines[]='## Estimation prudente sur 7 704'; $lines[]=''; $lines[]='- Ne pas extrapoler automatiquement les résultats du pilote : la phase 3 devra réutiliser les seuils observés, avec journalisation et validation humaine des conflits.'; $lines[]='- Les GPS participant à « autour de moi » devront être limités aux classes `VERIFIED`/`HIGH_CONFIDENCE`; exclure les clusters artificiels, approximatifs et conflits.'; return implode("\n",$lines)."\n";
    }
    private function parts(string $a): array { return preg_match('/\\b((?:0[1-9]|[1-8]\\d|9[0-5]|97[1-8]|98[0-8])\\d{3})\\s+([^,;\\n]+)\\s*$/u',trim($a),$m) ? [$m[1],trim($m[2])] : [null,null]; }
    private function hasAddress($r): bool { return trim((string) $r->address) !== ''; }
    private function normal(?string $v): string { return preg_replace('/[^a-z0-9]/','',Str::ascii(Str::lower(trim((string)$v)))) ?: ''; }
    public function distance(float $a,float $b,float $c,float $d): float { $x=sin(deg2rad($a))*sin(deg2rad($c))+cos(deg2rad($a))*cos(deg2rad($c))*cos(deg2rad($d-$b)); return 6371000*acos(min(1,max(-1,$x))); }
}
