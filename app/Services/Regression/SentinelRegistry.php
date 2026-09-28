<?php
namespace App\Services\Regression;

use App\Models\{Article,ContentMedia,MediaAsset,Page,RegressionDeploymentSnapshot,Restaurant,RestaurantMedia};
use Illuminate\Support\Facades\{DB,Storage};

class SentinelRegistry
{
    public function counts():array{return collect(['restaurants','media_assets','restaurant_media','articles','pages','categories','features','restaurant_reviews','comments','users','restaurant_claims','redirect_rules'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->count()])->all();}
    public function persist(bool $refresh=false):int{return 0;}
    public function verify(?string $snapshotId=null):array
    {
        $errors=$this->integrityErrors(); $counts=$this->counts(); $snapshot=null;
        if($snapshotId){$snapshot=RegressionDeploymentSnapshot::where('run_id',$snapshotId)->first();if(!$snapshot)$errors[]='Unknown regression deployment snapshot.';else foreach($snapshot->counts as $table=>$before)if(($counts[$table]??0)<$before)$errors[]="Unexpected count decrease during this deployment for {$table}: {$counts[$table]} < {$before}.";}
        return ['errors'=>$errors,'urls'=>$this->urls(),'media_urls'=>$this->mediaUrls(),'counts'=>$counts,'snapshot'=>$snapshot?['run_id'=>$snapshot->run_id,'counts'=>$snapshot->counts]:null];
    }
    private function urls():array
    {
        $urls=['home'=>'/','search'=>'/restaurants','blog'=>'/blog','login'=>'/login','not_found'=>'/__regression_missing_404'];
        if($r=Restaurant::query()->where('status','published')->latest('id')->first())$urls['restaurant.dynamic']='/resto/'.$r->slug;
        if($a=Article::query()->where('status','published')->latest('id')->first())$urls['article.dynamic']='/'.$a->slug;
        if($p=Page::query()->where('status','published')->latest('id')->first())$urls['page.dynamic']='/'.$p->slug;
        return $urls;
    }
    private function mediaUrls():array{return MediaAsset::query()->where('status','ready')->where(fn($q)=>$q->whereExists(fn($x)=>$x->selectRaw(1)->from('restaurant_media')->whereColumn('restaurant_media.media_asset_id','media_assets.id'))->orWhereExists(fn($x)=>$x->selectRaw(1)->from('content_media')->whereColumn('content_media.media_asset_id','media_assets.id')))->limit(12)->get()->filter(fn($a)=>str_starts_with((string)$a->mime,'image/'))->map(fn($a)=>parse_url($a->deliveryUrl(),PHP_URL_PATH))->values()->all();}
    private function integrityErrors():array
    {
        $errors=[];$disk=Storage::disk(config('legacy-media.disk'));
        foreach(MediaAsset::query()->with('variants')->whereExists(fn($q)=>$q->selectRaw(1)->from('restaurant_media')->whereColumn('restaurant_media.media_asset_id','media_assets.id'))->orWhereExists(fn($q)=>$q->selectRaw(1)->from('content_media')->whereColumn('content_media.media_asset_id','media_assets.id'))->get() as $asset){
            if(!filled($asset->original_path)||!$disk->exists($asset->original_path))$errors[]="Referenced media asset #{$asset->id} source file is missing.";
            foreach($asset->variants as $variant)if(!filled($variant->path)||!$disk->exists($variant->path))$errors[]="Referenced media asset #{$asset->id} variant #{$variant->id} file is missing.";
        }
        if(RestaurantMedia::query()->whereNotNull('media_asset_id')->whereDoesntHave('asset')->exists())$errors[]='Restaurant media relation references a missing asset.';
        if(ContentMedia::query()->whereNotNull('media_asset_id')->whereDoesntHave('asset')->exists())$errors[]='Editorial media relation references a missing asset.';
        if(ContentMedia::query()->where('content_type','post')->whereNotIn('content_id',Article::withTrashed()->select('id'))->exists())$errors[]='Editorial media relation references a missing article.';
        if(ContentMedia::query()->where('content_type','page')->whereNotIn('content_id',Page::withTrashed()->select('id'))->exists())$errors[]='Editorial media relation references a missing page.';
        if(DB::table('article_category')->whereNotIn('article_id',Article::withTrashed()->select('id'))->exists()||DB::table('article_tag')->whereNotIn('article_id',Article::withTrashed()->select('id'))->exists())$errors[]='Editorial taxonomy relation is orphaned.';
        return $errors;
    }
}
