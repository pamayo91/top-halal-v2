<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Feature;
use App\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncQuickRestaurantsCommand extends Command
{
    protected $signature = 'restaurants:sync-quick {--apply : Persist after dry-run review} {--snapshot=storage/app/audits/quick/quick-restaurants.json} {--audit=docs/generated/quick-restaurants-audit.csv} {--out=docs/generated/quick-restaurants-import.md}';
    protected $description = 'Idempotently synchronise the audited official Quick restaurants into V2.';
    private const ARGML = 'Les viandes servies dans ce restaurant sont contrôlées et certifiées HALAL par l’Association Rituelle de la Grande Mosquée de Lyon (ARGML).';

    public function handle(): int
    {
        $snapshot=base_path((string)$this->option('snapshot')); $audit=base_path((string)$this->option('audit'));
        if (!File::exists($snapshot)||!File::exists($audit)) { $this->error('Quick audit snapshot or CSV is missing.'); return self::FAILURE; }
        $data=json_decode(File::get($snapshot),true,512,JSON_THROW_ON_ERROR)['restaurants']??[]; $rows=collect(array_map('str_getcsv',file($audit))); $headers=$rows->shift(); $auditBySlug=$rows->mapWithKeys(fn($r)=>[array_combine($headers,$r)['quick_slug']=>array_combine($headers,$r)]);
        $fast=Category::where('slug','cuisine-fast-food')->firstOrFail(); $features=Feature::pluck('id','slug'); $apply=(bool)$this->option('apply');
        $summary=['official'=>count($data),'created'=>0,'updated'=>0,'unchanged'=>0,'probable_resolved'=>0,'errors'=>0,'service_mappings'=>0,'argml_descriptions'=>0];
        if($apply) { $backup=storage_path('app/backups/quick/quick-before-'.now()->format('Ymd-His').'.json'); File::ensureDirectoryExists(dirname($backup)); File::put($backup,Restaurant::whereIn('id',$auditBySlug->pluck('top_halal_restaurant_id')->filter())->with(['categories','features','openingHours'])->get()->toJson(JSON_PRETTY_PRINT)); }
        foreach($data as $quick) try {
            $slug=(string)$quick['quick_slug']; $auditRow=$auditBySlug[$slug]??null; $existingId=in_array($auditRow['match_status']??'', ['MATCH_EXACT','MATCH_UPDATE','MATCH_PROBABLE'], true) ? (int)($auditRow['top_halal_restaurant_id']??0) : 0;
            $restaurant=$existingId?Restaurant::find($existingId):Restaurant::where('slug','quick-'.$slug)->first(); $new=!$restaurant;
            $a=$quick['quick_raw']['attributes']; $line1=trim((string)($a['address2']?:$a['address1']?:'')); $line2=filled($a['address2']??null)?trim((string)$a['address1']):null;
            $cityCode=$restaurant?->city_code ?: Restaurant::where('postal_code',$quick['quick_postal_code'])->where('city_name',$quick['quick_city'])->whereNotNull('city_code')->value('city_code');
            $values=['name'=>$quick['quick_name'],'address'=>$quick['quick_address'],'address_line1'=>$line1?:null,'address_line2'=>$line2,'postal_code'=>$quick['quick_postal_code'],'city_name'=>Str::title(Str::lower((string)$quick['quick_city'])),'city_code'=>$cityCode,'country_code'=>'FR','latitude'=>$quick['quick_latitude'],'longitude'=>$quick['quick_longitude'],'has_halal_meat'=>true,'has_halal_chicken'=>true];
            if($new) $restaurant=new Restaurant($values+['slug'=>'quick-'.$slug,'status'=>'published']); else $restaurant->fill($values);
            $before=$restaurant->getDirty(); $description=$this->description($restaurant->description); if($description!==$restaurant->description){$restaurant->description=$description;$before['description']=$description;}
            if($apply && ($new||$before)) $restaurant->save();
            if($apply){ $restaurant=$restaurant->fresh(); $restaurant->categories()->syncWithoutDetaching([$fast->id]); $ids=$this->featureIds($a,$features); if($ids){$restaurant->features()->syncWithoutDetaching($ids);$summary['service_mappings']+=count($ids);} foreach($quick['quick_opening_hours'] as $day=>$range) $this->hour($restaurant,$slug,$day,$range); }
            $summary[$new?'created':($before?'updated':'unchanged')]++; if(($auditRow['match_status']??'')==='MATCH_PROBABLE')$summary['probable_resolved']++; $summary['argml_descriptions']++;
        } catch(\Throwable $e) {$summary['errors']++;$this->error(($quick['quick_slug']??'?').': '.$e->getMessage());}
        $summary['fast_food']= $apply ? Restaurant::whereIn('slug',collect($data)->map(fn($q)=>'quick-'.$q['quick_slug']))->whereHas('categories',fn($q)=>$q->whereKey($fast->id))->count()+$auditBySlug->pluck('top_halal_restaurant_id')->filter()->unique()->count() : 0;
        File::put(base_path((string)$this->option('out')),"# Import Quick\n\nMode: `".($apply?'apply':'dry-run')."`\n\n".collect($summary)->map(fn($v,$k)=>"- {$k}: **{$v}**")->implode("\n")."\n");
        $this->info(json_encode($summary)); return $summary['errors']?self::FAILURE:self::SUCCESS;
    }
    private function description(?string $current): string { $current=trim((string)$current); if(preg_match('/Association Rituelle de la Grande Mosquée de Lyon \(ARGML\)/u',$current)) return preg_replace('/[^.]*Association Rituelle de la Grande Mosquée de Lyon \(ARGML\)\.?/u',self::ARGML,$current,1); return $current===''?self::ARGML:$current."\n\n".self::ARGML; }
    private function featureIds(array $a,$features): array { $map=['terrace'=>'terrasse','takeaway'=>'vente-a-emporter','wifi'=>'wi-fi','pmr'=>'acces-handicape','certifHalal'=>'certifie-halal']; return collect($map)->filter(fn($slug,$field)=>($a[$field]??null)==='Oui'&&$features->has($slug))->map(fn($slug)=>$features[$slug])->values()->all(); }
    private function hour(Restaurant $r,string $slug,string $day,string $range): void { if(!preg_match('/^(\d\d:\d\d)\s*-\s*(\d\d:\d\d)$/',$range,$m))return; $r->openingHours()->updateOrCreate(['legacy_key'=>"quick:$slug:dining:$day"],['day'=>$day,'slot'=>1,'opens_at'=>$m[1],'closes_at'=>$m[2],'is_closed'=>false]); }
}
