<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\RestaurantWebEnrichment;
use App\Services\WebEnrichment\EvidenceRestaurantWebSourceProvider;
use App\Services\WebEnrichment\RestaurantWebEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WebEnrichRestaurantsCommand extends Command
{
    protected $signature = 'restaurants:web-enrich {--prepare : Reserve and export the next batch for Codex research} {--apply= : Apply a private JSON evidence file produced after Codex research} {--limit=50 : Maximum restaurants} {--restaurant= : One restaurant ID} {--dry-run : Select or validate without database writes} {--retry-errors : Reserve ERROR and stale PROCESSING rows} {--retry-insufficient : Reserve INSUFFICIENT_DATA rows only} {--out=docs/generated/web-enrichment : CSV report directory}';
    protected $description = 'Reserve restaurants for manual web research or safely apply Codex-collected evidence.';

    public function handle(): int
    {
        return $this->option('apply') ? $this->apply((string) $this->option('apply')) : $this->prepare();
    }

    private function prepare(): int
    {
        $dry = (bool) $this->option('dry-run'); $items = [];
        foreach ($this->candidates() as $restaurant) {
            if (!$dry && !$this->claim($restaurant)) continue;
            $items[] = ['restaurant_id'=>$restaurant->id,'legacy_wp_id'=>$restaurant->legacy_wp_id,'name'=>$restaurant->name,'address'=>$restaurant->address,'address_line1'=>$restaurant->address_line1,'postal_code'=>$restaurant->postal_code,'city_name'=>$restaurant->city_name,'latitude'=>$restaurant->latitude,'longitude'=>$restaurant->longitude,'phone'=>$restaurant->phone,'description'=>$restaurant->description,'hours_present'=>$restaurant->openingHours()->exists(),'evidence'=>['state'=>'unmatched','activity_status'=>'UNKNOWN','matching'=>['name'=>null,'address'=>null,'notes'=>null],'sources'=>[],'closure'=>null,'closure_sources'=>[],'hours'=>[],'hours_source'=>null,'description'=>null,'description_sources'=>[],'facts'=>[],'confidence'=>null,'reason'=>null]];
        }
        $payload = ['created_at'=>now()->toIso8601String(),'dry_run'=>$dry,'instructions'=>'Codex replaces evidence after normal web research. Sources are internal; state is matched, unmatched, error or unavailable.','restaurants'=>$items];
        $path = storage_path('app/private/web-enrichment/batch-'.now()->format('Ymd-His').'-'.Str::uuid().'.json'); File::ensureDirectoryExists(dirname($path)); File::put($path, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->info(($dry ? 'Dry-run prepared ' : 'Reserved ').count($items).' restaurant(s): '.$path); return self::SUCCESS;
    }

    private function apply(string $path): int
    {
        if (!File::exists($path)) { $this->error('Evidence file not found: '.$path); return self::FAILURE; }
        $payload = json_decode(File::get($path), true); if (!is_array($payload) || !is_array($payload['restaurants'] ?? null)) { $this->error('Invalid evidence JSON.'); return self::FAILURE; }
        $evidence=[]; foreach($payload['restaurants'] as $item) if (isset($item['restaurant_id']) && is_array($item['evidence'] ?? null)) $evidence[(int) $item['restaurant_id']]=$item['evidence'];
        $enricher = new RestaurantWebEnricher(new EvidenceRestaurantWebSourceProvider($evidence)); $rows=[];
        foreach(array_keys($evidence) as $id) { $restaurant=Restaurant::with('openingHours')->find($id); $audit=RestaurantWebEnrichment::where('restaurant_id',$id)->first(); if (!$restaurant || !$audit || $audit->status!=='PROCESSING') continue; $rows[]=$this->row($restaurant,$enricher->process($restaurant,(bool)$this->option('dry-run'))); }
        $this->report($rows); return self::SUCCESS;
    }

    private function candidates()
    {
        $q=Restaurant::query()->whereNull('deleted_at')->orderBy('restaurants.id');
        if ($id=$this->option('restaurant')) $q->whereKey((int) $id);
        elseif ($this->option('retry-insufficient')) $q->whereHas('webEnrichment',fn($x)=>$x->where('status','INSUFFICIENT_DATA'));
        elseif ($this->option('retry-errors')) $q->whereHas('webEnrichment',fn($x)=>$x->where('status','ERROR')->orWhere(fn($y)=>$y->where('status','PROCESSING')->where('processing_started_at','<',now()->subMinutes(30))));
        else $q->where(fn($x)=>$x->whereDoesntHave('webEnrichment')->orWhereHas('webEnrichment',fn($y)=>$y->where('status','PENDING')));
        return $q->limit(max(1,(int)$this->option('limit')))->get();
    }
    private function claim(Restaurant $r): bool { $audit=RestaurantWebEnrichment::firstOrCreate(['restaurant_id'=>$r->id],['legacy_wp_id'=>$r->legacy_wp_id]); $allowed=$this->option('retry-insufficient')?['INSUFFICIENT_DATA']:($this->option('retry-errors')?['ERROR','PROCESSING']:['PENDING']); return RestaurantWebEnrichment::whereKey($audit->id)->whereIn('status',$allowed)->update(['previous_status'=>$audit->status,'status'=>'PROCESSING','processing_started_at'=>now(),'attempts'=>$audit->attempts+1])===1; }
    private function row(Restaurant $r,array $x): array { $audit=RestaurantWebEnrichment::where('restaurant_id',$r->id)->first(); return ['restaurant_id'=>$r->id,'legacy_wp_id'=>$r->legacy_wp_id,'name'=>$r->name,'address'=>implode(', ',array_filter([$r->address_line1?:$r->address,$r->postal_code,$r->city_name])),'previous_status'=>$audit?->previous_status,'matching'=>json_encode($x['matching']??[]),'activity_status'=>$x['activity_status']??'','status'=>$x['status'],'reason'=>$x['reason']??'','sources'=>json_encode($x['sources']??[],JSON_UNESCAPED_UNICODE),'hours_before'=>json_encode($x['hours_before']??[]),'hours_found'=>json_encode($x['hours_after']??[]),'hours_applied'=>($x['hours_after']??null)?'yes':'no','description_before'=>$x['description_before']??'','description_after'=>$x['description_after']??'','description_applied'=>($x['description_after']??null)?'yes':'no','confidence_level'=>$x['confidence_level']??'','matching_confidence'=>$x['matching_confidence']??'','activity_confidence'=>$x['activity_confidence']??'','hours_confidence'=>$x['hours_confidence']??'','description_confidence'=>$x['description_confidence']??'']; }
    private function report(array $rows): void { $dir=base_path($this->option('out')); File::ensureDirectoryExists($dir); $path=$dir.'/batch-'.now()->format('Ymd-His').'.csv'; $out=fopen($path,'w'); fputcsv($out,['restaurant_id','legacy_wp_id','name','address','previous_status','matching','activity_status','status','reason','sources','hours_before','hours_found','hours_applied','description_before','description_after','description_applied','confidence_level','matching_confidence','activity_confidence','hours_confidence','description_confidence']); foreach($rows as $row)fputcsv($out,$row); fclose($out); $this->info('Rapport CSV : '.$path); foreach(collect($rows)->countBy('status')->sortKeys() as $status=>$count)$this->line($status.' : '.$count); }
}
