<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Quick\QuickRestaurantMatcher;
use App\Services\Quick\QuickRestaurantSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditQuickRestaurantsCommand extends Command
{
    protected $signature = 'restaurants:audit-quick {--out=docs/generated : Public report directory} {--snapshot=storage/app/audits/quick/quick-restaurants.json : Private raw snapshot path}';
    protected $description = 'Read Quick official data and compare it with restaurants without changing business data.';

    public function handle(QuickRestaurantSource $source, QuickRestaurantMatcher $matcher): int
    {
        $before = $this->fingerprint(); $checkedAt = now('Europe/Paris')->toIso8601String();
        try { $quickRestaurants = $source->restaurants(); }
        catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }

        $restaurants = Restaurant::query()->select(['id','legacy_wp_id','name','address','address_line1','postal_code','city_name','phone','latitude','longitude'])->get();
        $rows=[]; foreach ($quickRestaurants as $quick) {
            $match=$matcher->match($quick,$restaurants); $top=$match['restaurant'];
            $rows[]=[
                'quick_name'=>$quick['quick_name'],'quick_slug'=>$quick['quick_slug'],'quick_url'=>$quick['quick_url'],'quick_address'=>$quick['quick_address'],'quick_address_line1'=>$quick['quick_address_line1'],'quick_postal_code'=>$quick['quick_postal_code'],'quick_city'=>$quick['quick_city'],'quick_phone'=>$quick['quick_phone'],'quick_latitude'=>$quick['quick_latitude'],'quick_longitude'=>$quick['quick_longitude'],'quick_halal_status'=>$quick['quick_halal_status'],'quick_halal_certifier'=>$quick['quick_halal_certifier'],'quick_halal_evidence'=>$quick['quick_halal_evidence'],
                'top_halal_restaurant_id'=>$top?->id,'top_halal_legacy_wp_id'=>$top?->legacy_wp_id,'top_halal_name'=>$top?->name,'top_halal_address_line1'=>$top?->address_line1 ?: $top?->address,'top_halal_postal_code'=>$top?->postal_code,'top_halal_city'=>$top?->city_name,'top_halal_phone'=>$top?->phone,'top_halal_latitude'=>$top?->latitude,'top_halal_longitude'=>$top?->longitude,
                'match_status'=>$match['status'],'match_score'=>$match['score'],'differences'=>implode(',',$match['differences']),'proposed_actions'=>implode('|',$match['actions']),'checked_at'=>$checkedAt,
                '_quick_hours'=>$quick['quick_opening_hours'],'_quick_services'=>$quick['quick_services'],'_candidate_ids'=>collect($match['candidates'])->pluck('id')->all(),
            ];
        }
        $out=base_path((string)$this->option('out')); File::ensureDirectoryExists($out);
        $this->writeCsv($out.'/quick-restaurants-audit.csv',$rows);
        File::put($out.'/quick-restaurants-audit.md',$this->markdown($rows));
        $snapshot=base_path((string)$this->option('snapshot')); File::ensureDirectoryExists(dirname($snapshot)); File::put($snapshot,json_encode(['source'=>QuickRestaurantSource::DIRECTORY_URL,'checked_at'=>$checkedAt,'restaurants'=>$quickRestaurants], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        if ($before !== $this->fingerprint()) { $this->error('Read-only guard failed: restaurants changed during audit.'); return self::FAILURE; }
        $this->info('Quick audit complete: '.count($rows).' official restaurants; no restaurant write detected.');
        return self::SUCCESS;
    }

    private function fingerprint(): string { return json_encode([Restaurant::withTrashed()->count(), Restaurant::withTrashed()->max('updated_at')]); }
    /** @param array<int,array<string,mixed>> $rows */
    private function writeCsv(string $path,array $rows): void
    {
        $headers=['quick_name','quick_slug','quick_url','quick_address','quick_address_line1','quick_postal_code','quick_city','quick_phone','quick_latitude','quick_longitude','quick_halal_status','quick_halal_certifier','quick_halal_evidence','top_halal_restaurant_id','top_halal_legacy_wp_id','top_halal_name','top_halal_address_line1','top_halal_postal_code','top_halal_city','top_halal_phone','top_halal_latitude','top_halal_longitude','match_status','match_score','differences','proposed_actions','checked_at'];
        $handle=fopen($path,'wb'); fputcsv($handle,$headers); foreach($rows as $row) fputcsv($handle,array_map(fn($h)=>$row[$h] ?? null,$headers)); fclose($handle);
    }
    /** @param array<int,array<string,mixed>> $rows */
    private function markdown(array $rows): string
    {
        $count=collect($rows)->countBy('match_status'); $halal=collect($rows)->countBy('quick_halal_status');
        $out=['# Audit restaurants Quick','', 'Source : `https://www.quick.fr/tous-les-quicks` — données Next.js embarquées (`__NEXT_DATA__`, requête unique, sans exploration agressive des fiches).', '', '## Synthèse','', '- Quick récupérés : **'.count($rows).'**.','- Fiches analysées : **'.count($rows).'**.','- Halal confirmé : **'.($halal['halal_confirmed']??0).'** ; halal non confirmé : **'.(count($rows)-($halal['halal_confirmed']??0)).'**.'];
        foreach(['MATCH_EXACT','MATCH_UPDATE','MATCH_PROBABLE','MISSING_TOP_HALAL','DUPLICATE_TOP_HALAL','SCRAPE_ERROR'] as $status) $out[]='- '.$status.' : **'.($count[$status]??0).'**.';
        $out[]=''; $out=array_merge($out,$this->table('Quick absents de Top-Halal',['Nom','CP','Ville','Halal','Source'],collect($rows)->where('match_status','MISSING_TOP_HALAL')->map(fn($r)=>[$r['quick_name'],$r['quick_postal_code'],$r['quick_city'],$r['quick_halal_status'],$r['quick_url']])->all()));
        $out=array_merge($out,$this->table('Quick nécessitant une mise à jour',['ID Top-Halal','Quick','Champ','Valeur Top-Halal','Valeur Quick'],collect($rows)->where('match_status','MATCH_UPDATE')->flatMap(function($r){return collect(explode(',',$r['differences']))->filter()->map(fn($field)=>[$r['top_halal_restaurant_id'],$r['quick_name'],$field,$this->topValue($r,$field),$this->quickValue($r,$field)]);})->all()));
        $out=array_merge($out,$this->table('Correspondances incertaines',['Quick','Restaurant Top-Halal candidat','Motif du doute'],collect($rows)->where('match_status','MATCH_PROBABLE')->map(fn($r)=>[$r['quick_name'],$r['top_halal_name'],'score '.$r['match_score'].'; '.($r['differences'] ?: 'signaux insuffisants')])->all()));
        $out=array_merge($out,$this->table('Doublons potentiels',['Quick','IDs Top-Halal concernés'],collect($rows)->where('match_status','DUPLICATE_TOP_HALAL')->map(fn($r)=>[$r['quick_name'],implode(', ',$r['_candidate_ids'])])->all()));
        return implode("\n",$out)."\n";
    }
    private function topValue(array $row,string $field): mixed { return match($field){'name'=>$row['top_halal_name'],'address'=>$row['top_halal_address_line1'],'postal_code'=>$row['top_halal_postal_code'],'city'=>$row['top_halal_city'],'phone'=>$row['top_halal_phone'],'coordinates'=>trim($row['top_halal_latitude'].','.$row['top_halal_longitude'],','),default=>''}; }
    private function quickValue(array $row,string $field): mixed { return match($field){'name'=>$row['quick_name'],'address'=>$row['quick_address_line1'],'postal_code'=>$row['quick_postal_code'],'city'=>$row['quick_city'],'phone'=>$row['quick_phone'],'coordinates'=>trim($row['quick_latitude'].','.$row['quick_longitude'],','),default=>''}; }
    private function table(string $title,array $headers,array $rows): array { $out=['## '.$title,'','| '.implode(' | ',$headers).' |','| '.implode(' | ',array_fill(0,count($headers),'---')).' |']; foreach($rows as $row)$out[]='| '.implode(' | ',array_map(fn($v)=>str_replace('|','\\|',(string)$v),$row)).' |'; return [...$out,'']; }
}
