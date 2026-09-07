<?php
namespace App\Console\Commands;
use App\Models\{RedirectRule,Restaurant};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
class CleanUpLegacyQuickRestaurantsCommand extends Command {
 protected $signature='restaurants:cleanup-legacy-quick {--apply} {--redirect-obsolete : Add homepage redirects for already trashed obsolete Quick records} {--out=docs/generated/quick-legacy-cleanup.md}';
 protected $description='Retire des parcours publics les anciennes fiches Quick absentes du référentiel officiel.';
 public function handle():int {
  if($this->option('redirect-obsolete')) return $this->redirectObsolete();
  $current=Restaurant::query()->whereNull('legacy_wp_id')->where('slug','like','quick-%')->get();
  $reviewed=collect(array_map('str_getcsv',file(base_path('docs/generated/quick-restaurants-audit.csv'))));$head=$reviewed->shift();$ids=$reviewed->map(fn($r)=>array_combine($head,$r))->filter(fn($r)=>in_array($r['match_status'],['MATCH_EXACT','MATCH_UPDATE','MATCH_PROBABLE']))->pluck('top_halal_restaurant_id')->filter();
  $current=$current->merge(Restaurant::whereIn('id',$ids)->get())->keyBy('id');
  $old=Restaurant::query()->whereNotNull('legacy_wp_id')->whereRaw("LOWER(name) REGEXP '(^| )quick( |$)'")->whereNotIn('id',$current->keys())->get();
  $summary=['legacy_found'=>$old->count(),'current'=>$current->count(),'replaced'=>0,'obsolete'=>0,'deactivated'=>0,'redirects_created'=>0,'anomalies'=>0];$lines=['# Nettoyage anciens Quick',''];
  foreach($old as $legacy){$matches=$current->filter(fn($r)=>$this->n($r->postal_code)===$this->n($legacy->postal_code)&&$this->n($r->city_name)===$this->n($legacy->city_name));$target=$matches->count()===1?$matches->first():null;$kind=$target?'replaced':'obsolete';$summary[$kind]++;
   if($this->option('apply')){if($target){RedirectRule::updateOrCreate(['source_path'=>'/resto/'.$legacy->slug,'match_type'=>'exact','query_pattern'=>null],['destination'=>'/resto/'.$target->slug,'status_code'=>301,'preserve_query'=>false,'priority'=>100,'is_active'=>true,'origin'=>'quick_cleanup','source_rule'=>'Official Quick replacement']);$summary['redirects_created']++;}$legacy->delete();$summary['deactivated']++;}
   $lines[]='- `'.$legacy->id.'` '.$legacy->name.': '.$kind.($target?' → '.$target->name:'').'.';}
  $lines[]='';foreach($summary as $k=>$v)$lines[]='- '.$k.': **'.$v.'**.';File::put(base_path((string)$this->option('out')),implode("\n",$lines)."\n");$this->info(json_encode($summary));return self::SUCCESS;}
 private function n($v):string{return preg_replace('/[^a-z0-9]/','',Str::ascii(Str::lower(trim((string)$v))));}
 private function redirectObsolete():int { $rows=Restaurant::onlyTrashed()->whereNotNull('legacy_wp_id')->whereRaw("LOWER(name) REGEXP '(^| )quick( |$)'")->get();$created=0;foreach($rows as $row){if($this->option('apply')){RedirectRule::updateOrCreate(['source_path'=>'/resto/'.$row->slug,'match_type'=>'exact','query_pattern'=>null],['destination'=>'/quick-hallal','status_code'=>301,'preserve_query'=>false,'priority'=>100,'is_active'=>true,'origin'=>'quick_cleanup','source_rule'=>'Official Quick obsolete fallback']);$created++;}}$this->info(json_encode(['obsolete_redirects'=>$created,'candidates'=>$rows->count()]));return self::SUCCESS;}
}
