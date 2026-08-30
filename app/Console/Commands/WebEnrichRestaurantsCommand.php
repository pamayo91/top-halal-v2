<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\RestaurantWebEnrichment;
use App\Services\WebEnrichment\RestaurantWebEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WebEnrichRestaurantsCommand extends Command
{
    protected $signature = 'restaurants:web-enrich {--limit=50 : Maximum restaurants for this batch} {--dry-run : Analyse without database writes} {--retry-errors : Retry ERROR and stale PROCESSING rows only} {--out=docs/generated/web-enrichment : CSV report directory}';
    protected $description = 'Safely enrich missing restaurant hours and eligible descriptions from a configured web provider.';

    public function handle(RestaurantWebEnricher $enricher): int
    {
        $limit=max(1, (int) $this->option('limit')); $dry=(bool) $this->option('dry-run'); $retry=(bool) $this->option('retry-errors');
        $query=Restaurant::query()->whereNull('deleted_at')->orderBy('restaurants.id');
        if ($retry) $query->whereHas('webEnrichment', fn ($q) => $q->where('status','ERROR')->orWhere(fn($x)=>$x->where('status','PROCESSING')->where('processing_started_at','<',now()->subMinutes(30))));
        else $query->where(fn ($q) => $q->whereDoesntHave('webEnrichment')->orWhereHas('webEnrichment', fn($x)=>$x->whereIn('status',['PENDING'])));
        $restaurants=$query->limit($limit)->get(); $stats=array_fill_keys(['analysed','hours','descriptions','unchanged','closed_confirmed','closed_possible','closure_conflict','source_conflict','insufficient','errors'],0); $rows=[];
        foreach ($restaurants as $restaurant) {
            if (!$dry && !$this->claim($restaurant, $retry)) continue;
            $result=$enricher->process($restaurant, $dry); $stats['analysed']++;
            if ($result['hours_after'] ?? null) $stats['hours']++; if ($result['description_after'] ?? null) $stats['descriptions']++;
            $key=['UPDATED'=>'updated','UNCHANGED'=>'unchanged','CLOSED_CONFIRMED_REVIEW'=>'closed_confirmed','CLOSED_POSSIBLE_REVIEW'=>'closed_possible','CLOSURE_CONFLICT'=>'closure_conflict','SOURCE_CONFLICT'=>'source_conflict','INSUFFICIENT_DATA'=>'insufficient','ERROR'=>'errors'][$result['status']] ?? 'errors'; if ($key !== 'updated') $stats[$key]++;
            $rows[]=['restaurant_id'=>$restaurant->id,'legacy_wp_id'=>$restaurant->legacy_wp_id,'name'=>$restaurant->name,'address'=>implode(', ',array_filter([$restaurant->address_line1 ?: $restaurant->address,$restaurant->postal_code,$restaurant->city_name])),'status'=>$result['status'],'reason'=>$result['reason'] ?? '','sources'=>json_encode($result['sources'] ?? [],JSON_UNESCAPED_UNICODE),'hours_before'=>json_encode($result['hours_before'] ?? []),'hours_after'=>json_encode($result['hours_after'] ?? []),'description_before'=>$result['description_before'] ?? '','description_after'=>$result['description_after'] ?? ''];
        }
        $this->writeCsv($rows); foreach (['analysed'=>'Restaurants analysés','hours'=>'Horaires ajoutés','descriptions'=>'Descriptions ajoutées/remplacées','unchanged'=>'Restaurants inchangés','closed_confirmed'=>'Fermetures confirmées à revoir','closed_possible'=>'Fermetures possibles','closure_conflict'=>'Conflits de fermeture','source_conflict'=>'Conflits de sources','insufficient'=>'Données insuffisantes','errors'=>'Erreurs'] as $key=>$label) $this->line($label.' : '.$stats[$key]);
        return self::SUCCESS;
    }
    private function claim(Restaurant $restaurant, bool $retry): bool { $audit=RestaurantWebEnrichment::firstOrCreate(['restaurant_id'=>$restaurant->id],['legacy_wp_id'=>$restaurant->legacy_wp_id]); $allowed=$retry?['ERROR','PROCESSING']:['PENDING']; return RestaurantWebEnrichment::whereKey($audit->id)->whereIn('status',$allowed)->update(['status'=>'PROCESSING','processing_started_at'=>now(),'attempts'=>$audit->attempts+1]) === 1; }
    private function writeCsv(array $rows): void { $directory=base_path($this->option('out')); File::ensureDirectoryExists($directory); $path=$directory.'/batch-'.now()->format('Ymd-His').'.csv'; $stream=fopen($path,'w'); fputcsv($stream,['restaurant_id','legacy_wp_id','name','address','status','reason','sources','hours_before','hours_after','description_before','description_after']); foreach($rows as $row) fputcsv($stream,$row); fclose($stream); $this->line('Rapport CSV : '.$path); }
}
