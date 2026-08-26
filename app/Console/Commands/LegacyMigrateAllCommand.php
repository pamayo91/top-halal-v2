<?php

namespace App\Console\Commands;

use App\Models\{Article,Comment,ContentMedia,EditorialCategory,EditorialTag,MediaAsset,MigrationAnomaly,MigrationCheckpoint,MigrationRun,Page,Restaurant,RestaurantReview,User};
use App\Services\{ContentSanitizer,ContentTransformer,LegacyContentReader,LegacyInlineMediaMigrator,LegacyMediaReader,LegacyRestaurantMigrator,LegacyReviewMigrator,LegacyReviewReader,MediaVariantGenerator};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,File,Hash,Storage};
use Illuminate\Support\Str;

class LegacyMigrateAllCommand extends Command
{
    protected $signature = 'legacy:migrate-all {--dry-run} {--apply} {--batch-size=100} {--resume} {--run=} {--only=}';
    private array $phases = ['taxonomies','restaurants','articles','pages','comments','reviews','users','claims','media'];
    private array $summary = [];
    private ?MigrationRun $run = null;
    private string $directory;
    private bool $apply;
    private int $batch;

    public function handle(): int
    {
        if ((bool)$this->option('dry-run') === (bool)$this->option('apply') || ($this->option('resume') && ! $this->option('apply'))) return $this->error('Choose exactly one of --dry-run/--apply; --resume requires --apply.') ?: self::FAILURE;
        $this->apply=(bool)$this->option('apply'); $this->batch=max(1,min(500,(int)$this->option('batch-size')));
        $only=$this->option('only') ? array_values(array_intersect($this->phases, array_filter(explode(',',(string)$this->option('only'))))) : $this->phases;
        if ($only===[]) return $this->error('No valid phase selected.') ?: self::FAILURE;
        $uuid=(string)($this->option('run') ?: Str::uuid());
        $this->directory='docs/generated/full-migration-'.now()->format('Y-m-d-His').'-'.$uuid;
        File::ensureDirectoryExists(base_path($this->directory));
        if ($this->apply) {
            $this->run=$this->option('resume') ? MigrationRun::where('run_uuid',$uuid)->where('status','!=','completed')->firstOrFail() : MigrationRun::create(['run_uuid'=>$uuid,'status'=>'running','batch_size'=>$this->batch,'only'=>$only,'started_at'=>now()]);
            $this->directory='docs/generated/full-migration-'.$this->run->run_uuid;
            File::ensureDirectoryExists(base_path($this->directory));
        }
        foreach ($only as $phase) $this->{'migrate'.Str::studly($phase)}($phase);
        $this->reconcile($only);
        if ($this->run) $this->run->update(['status'=>'completed','completed_at'=>now()]);
        return self::SUCCESS;
    }

    private function migrateTaxonomies(string $phase): void
    {
        $db=DB::connection('legacy_wp'); $counts=['source'=>0,'examined'=>0,'migrated'=>0,'existing'=>0,'ignored'=>0,'anomalies'=>0,'errors'=>0];
        foreach (['listing-category'=>[\App\Models\Category::class,'categories'],'features'=>[\App\Models\Feature::class,'features'],'location'=>[\App\Models\Location::class,'locations']] as $taxonomy=>[$model,$table]) {
            $db->table('term_taxonomy as x')->join('terms as t','t.term_id','=','x.term_id')->where('x.taxonomy',$taxonomy)->orderBy('t.term_id')->select('t.term_id','t.name','t.slug','x.parent')->chunkById($this->batch,function($rows)use($model,$taxonomy,&$counts){foreach($rows as $term){$counts['source']++;$counts['examined']++;if(!$this->apply){$counts['migrated']++;continue;} $exists=$model::where('legacy_term_id',$term->term_id)->exists();$data=['name'=>trim($term->name)?:'Sans nom','slug'=>Str::slug($term->slug?:$term->name)?:"term-$term->term_id"];if($taxonomy==='location')$data['parent_id']=null;$model::updateOrCreate(['legacy_term_id'=>$term->term_id],$data);$counts[$exists?'existing':'migrated']++;}} ,'t.term_id','term_id');
        }
        if($this->apply) foreach(\App\Models\Location::cursor() as $location){$parent=DB::connection('legacy_wp')->table('term_taxonomy')->where('term_id',$location->legacy_term_id)->value('parent');$location->update(['parent_id'=>$parent?\App\Models\Location::where('legacy_term_id',$parent)->value('id'):null]);}
        $this->finish($phase,$counts,PHP_INT_MAX);
    }

    private function migrateRestaurants(string $phase): void
    {
        $migrator=app(LegacyRestaurantMigrator::class); $this->idBatches($phase,'posts',fn($q)=>$q->where('post_type','listing'),function($id)use($migrator){$record=$migrator->inspect($id,'full');if($this->apply){$slug=$record['target']['restaurant']['slug'];if(Restaurant::where('slug',$slug)->where('legacy_wp_id','!=',$id)->exists()){$record['target']['restaurant']['slug'].='-'.$id;$record['anomalies'][]='duplicate_slug_resolved';}$exists=Restaurant::where('legacy_wp_id',$id)->exists();$migrator->persist($record);return [$exists?'existing':'migrated',$record['anomalies']];}return ['migrated',$record['anomalies']];});
    }

    private function migrateArticles(string $phase): void {$this->migrateContent($phase,'post',Article::class);}
    private function migratePages(string $phase): void {$this->migrateContent($phase,'page',Page::class);}
    private function migrateContent(string $phase,string $type,string $model): void
    {
        $reader=app(LegacyContentReader::class);$transformer=app(ContentTransformer::class);$sanitizer=app(ContentSanitizer::class);
        $this->idBatches($phase,'posts',fn($q)=>$q->where('post_type',$type)->whereIn('post_status',['publish','pending','draft']),function($id)use($reader,$transformer,$sanitizer,$type,$model){$x=$reader->read($type,$id);$post=$x['post'];$t=$transformer->transform($post->post_content);$s=$sanitizer->sanitize($t['html']);$slug=Str::slug($post->post_name?:$post->post_title)?:"$type-$id";$anomalies=array_merge($t['unknown'],$s['removed']);if($post->post_date_gmt==='0000-00-00 00:00:00')$anomalies[]='invalid_legacy_published_date';if($this->apply){if($model::where('slug',$slug)->where('legacy_wp_id','!=',$id)->exists()){$slug.="-$id";$anomalies[]='duplicate_slug_resolved';}$exists=$model::where('legacy_wp_id',$id)->exists();$o=$model::updateOrCreate(['legacy_wp_id'=>$id],['legacy_author_id'=>$post->post_author?:null,'original_title'=>$post->post_title,'title'=>$post->post_title?:'Sans titre','slug'=>$slug,'content_html'=>$s['html'],'status'=>$post->post_status==='publish'?'published':$post->post_status,'legacy_url'=>'/'.ltrim($post->post_name,'/').'/', 'legacy_published_at'=>$post->post_date_gmt==='0000-00-00 00:00:00'?null:$post->post_date_gmt,'legacy_modified_at'=>$post->post_modified_gmt==='0000-00-00 00:00:00'?null:$post->post_modified_gmt]);if($type==='post'){$cats=[];$tags=[];foreach($x['terms'] as $term){$class=$term->taxonomy==='category'?EditorialCategory::class:EditorialTag::class;$id2=$class::updateOrCreate(['legacy_term_id'=>$term->term_id],['name'=>$term->name,'slug'=>Str::slug($term->slug?:$term->name)?:"term-$term->term_id"])->id;if($term->taxonomy==='category')$cats[]=$id2;else $tags[]=$id2;}$o->categories()->sync($cats);$o->tags()->sync($tags);}if($featured=$x['meta']['_thumbnail_id']??null)ContentMedia::updateOrCreate(['content_type'=>$type,'content_id'=>$o->id,'legacy_attachment_id'=>(int)$featured,'role'=>'featured'],[]);return [$exists?'existing':'migrated',$anomalies];}return ['migrated',$anomalies];});
    }

    private function migrateComments(string $phase): void
    {
        $this->idBatches($phase,'comments',fn($q)=>$q->whereIn('comment_approved',['0','1'])->whereIn('comment_type',['','comment']),function($id){$row=DB::connection('legacy_wp')->table('comments')->where('comment_ID',$id)->first();$article=Article::where('legacy_wp_id',$row->comment_post_ID)->first();$page=$article?null:Page::where('legacy_wp_id',$row->comment_post_ID)->first();if(!$article&&!$page&&!$this->apply){$type=DB::connection('legacy_wp')->table('posts')->where('ID',$row->comment_post_ID)->value('post_type');if(in_array($type,['post','page'],true))return ['migrated',[]];}if(!$article&&!$page)return ['ignored',['unmigrated_or_unsupported_post']];if($this->apply){$exists=Comment::where('legacy_wp_comment_id',$id)->exists();Comment::updateOrCreate(['legacy_wp_comment_id'=>$id],['legacy_wp_post_id'=>$row->comment_post_ID,'article_id'=>$article?->id,'page_id'=>$page?->id,'legacy_user_id'=>$row->user_id?:null,'author_name'=>Str::limit(trim(strip_tags($row->comment_author))?:'Anonyme',100,''),'author_email'=>$row->comment_author_email?:null,'content'=>trim(html_entity_decode(strip_tags($row->comment_content),ENT_QUOTES|ENT_HTML5,'UTF-8')),'status'=>$row->comment_approved==='1'?'approved':'pending','approved_at'=>$row->comment_approved==='1'?$row->comment_date_gmt:null,'created_at'=>$row->comment_date_gmt,'updated_at'=>$row->comment_date_gmt]);return [$exists?'existing':'migrated',[]];}return ['migrated',[]];});
        if($this->apply) DB::connection('legacy_wp')->table('comments')->whereIn('comment_approved',['0','1'])->whereIn('comment_type',['','comment'])->where('comment_parent','>',0)->orderBy('comment_ID')->chunkById($this->batch,function($rows){foreach($rows as $row){$child=Comment::where('legacy_wp_comment_id',$row->comment_ID)->first();$parent=Comment::where('legacy_wp_comment_id',$row->comment_parent)->first();if($child&&$parent)$child->update(['parent_id'=>$parent->id]);elseif($child)$this->anomaly('comments',$row->comment_ID,'parent_not_migrated','warning');}} ,'comment_ID');
    }

    private function migrateReviews(string $phase): void
    {
        $reader=app(LegacyReviewReader::class);$migrator=app(LegacyReviewMigrator::class);
        $this->idBatches($phase,'posts',fn($q)=>$q->where('post_type','lp-reviews'),function($id)use($reader,$migrator){$post=$reader->findMany([$id])[0]??null;if(!$post)return ['errors',['review_not_found']];$record=$migrator->inspect($post);if(!$this->apply&&in_array('restaurant_not_migrated_or_invalid',$record['anomalies'],true)){if(DB::connection('legacy_wp')->table('posts')->where('ID',$record['source']['legacy_restaurant_id'])->where('post_type','listing')->exists())$record['anomalies']=array_values(array_diff($record['anomalies'],['restaurant_not_migrated_or_invalid']));}if($record['anomalies']!==[])return ['ignored',$record['anomalies']];if($this->apply){$exists=RestaurantReview::where('legacy_wp_review_id',$id)->exists();$migrator->persist($record);return[$exists?'existing':'migrated',[]];}return['migrated',[]];});
    }

    private function migrateUsers(string $phase): void
    {
        if($this->apply&&!filled(env('LEGACY_MIGRATION_TEMP_PASSWORD')))throw new \RuntimeException('Temporary legacy password is not configured.');
        $this->idBatches($phase,'users',fn($q)=>$q,function($id){$row=DB::connection('legacy_wp')->table('users')->where('ID',$id)->first();if(!$row||$row->user_email==='')return['ignored',['missing_email']];if($this->apply){$user=User::where('legacy_wp_user_id',$id)->first();if(!$user)$user=User::where('email',$row->user_email)->first();$exists=$user!==null;if(!$user)$user=new User();if(!$exists)$user->forceFill(['name'=>trim($row->display_name)?:'Utilisateur','email'=>$row->user_email,'password'=>Hash::make((string)env('LEGACY_MIGRATION_TEMP_PASSWORD')),'role'=>'user','status'=>'active','must_change_password'=>true,'created_at'=>$row->user_registered]);$user->forceFill(['legacy_wp_user_id'=>$id]);$user->save();return[$exists?'existing':'migrated',[]];}return['migrated',[]];});
    }

    private function migrateClaims(string $phase): void
    {
        $counts=['source'=>0,'examined'=>0,'migrated'=>0,'existing'=>0,'ignored'=>0,'anomalies'=>0,'errors'=>0];
        DB::connection('legacy_wp')->table('posts')->where('post_type','lp-claims')->orderBy('ID')->chunkById($this->batch,function($rows)use(&$counts,$phase){foreach($rows as $row){$counts['source']++;$counts['examined']++;$counts['ignored']++;$counts['anomalies']++;$this->anomaly($phase,$row->ID,'no_reliable_claimant_user','warning',['legacy_restaurant_id'=>null,'legacy_status'=>$row->post_status,'legacy_date'=>$row->post_date_gmt]);}} ,'ID','ID');
        $this->finish($phase,$counts,PHP_INT_MAX);
    }

    private function migrateMedia(string $phase): void
    {
        $last=$this->checkpoint($phase);$ids=collect()->merge(DB::table('restaurant_media')->pluck('legacy_attachment_id'))->merge(DB::table('content_media')->pluck('legacy_attachment_id'))->filter(fn($id)=>(int)$id>$last)->unique()->sort()->values();$counts=['source'=>$ids->count(),'examined'=>0,'migrated'=>0,'existing'=>0,'ignored'=>0,'anomalies'=>0,'errors'=>0];$reader=app(LegacyMediaReader::class);$variants=app(MediaVariantGenerator::class);
        foreach($ids->chunk($this->batch) as $chunk)foreach(DB::connection('legacy_wp')->table('posts')->whereIn('ID',$chunk)->where('post_type','attachment')->orderBy('ID')->get() as $attachment){$counts['examined']++;try{$info=$reader->inspect($attachment->guid);if($this->apply){$exists=MediaAsset::where('legacy_attachment_id',$attachment->ID)->exists();$extension=strtolower(pathinfo($info['source'],PATHINFO_EXTENSION))?:'bin';$path="media/originals/{$info['checksum']}.{$extension}";$disk=Storage::disk(config('legacy-media.disk'));if(!$disk->exists($path))$disk->put($path,file_get_contents($info['source']));$asset=MediaAsset::updateOrCreate(['legacy_attachment_id'=>$attachment->ID],['original_path'=>$path,'mime'=>$info['mime'],'width'=>$info['width'],'height'=>$info['height'],'bytes'=>$info['bytes'],'checksum'=>$info['checksum'],'alt_text'=>$attachment->post_excerpt?:null,'status'=>'ready']);ContentMedia::where('legacy_attachment_id',$attachment->ID)->update(['media_asset_id'=>$asset->id]);$variants->generate($asset);$counts[$exists?'existing':'migrated']++;}else $counts['migrated']++;}catch(\Throwable){$counts['ignored']++;$counts['anomalies']++;$this->anomaly($phase,$attachment->ID,'missing_or_invalid_source','warning');}$this->checkpoint($phase,(int)$attachment->ID,$counts);}
        $inline=app(LegacyInlineMediaMigrator::class)->reconcileAll($this->apply);
        foreach(['source','examined','migrated','existing','ignored','anomalies','errors'] as $key)$counts[$key]+=$inline[$key];
        foreach($inline['items'] as $item)if($item['outcome']!=='ready')$this->anomaly($phase,(int)$item['legacy_wp_id'],'inline_'.$item['outcome'].'_position_'.$item['position'],$item['outcome']==='processing_error'?'error':'warning',$item);
        File::put(base_path($this->directory.'/inline-media-reconciliation.json'),json_encode($inline,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->finish($phase,$counts,PHP_INT_MAX);
    }

    private function idBatches(string $phase,string $table,callable $scope,callable $handler): void
    {
        $counts=['source'=>0,'examined'=>0,'migrated'=>0,'existing'=>0,'ignored'=>0,'anomalies'=>0,'errors'=>0];$last=$this->checkpoint($phase);$column=$table==='comments'?'comment_ID':'ID';$query=$scope(DB::connection('legacy_wp')->table($table))->where($column,'>',$last)->orderBy($column);
        $query->chunkById($this->batch,function($rows)use($phase,$handler,&$counts,$column){foreach($rows as $row){$id=(int)$row->{$column};$counts['source']++;$counts['examined']++;try{[$result,$anomalies]=$handler($id);$counts[$result]++;foreach($anomalies as $code){$counts['anomalies']++;$this->anomaly($phase,$id,$code,'warning');}}catch(\Throwable $e){$counts['errors']++;$this->anomaly($phase,$id,'migration_error','error');}$this->checkpoint($phase,$id,$counts);} },$column);
        $this->finish($phase,$counts,PHP_INT_MAX);
    }

    private function checkpoint(string $phase,?int $last=null,?array $counts=null): int {if(!$this->run)return 0;$c=MigrationCheckpoint::firstOrCreate(['migration_run_id'=>$this->run->id,'phase'=>$phase]);if($last!==null)$c->update(['last_legacy_id'=>$last,'status'=>'running','counters'=>$counts]);return (int)$c->last_legacy_id;}
    private function finish(string $phase,array $counts,int $last): void {$this->summary[$phase]=$counts;if($this->run)MigrationCheckpoint::updateOrCreate(['migration_run_id'=>$this->run->id,'phase'=>$phase],['last_legacy_id'=>$last,'status'=>'completed','counters'=>$counts]);File::put(base_path($this->directory.'/'.$phase.'.json'),json_encode($counts,JSON_PRETTY_PRINT));}
    private function anomaly(string $phase,?int $id,string $code,string $severity,array $context=[]):void {if($this->run)MigrationAnomaly::firstOrCreate(['migration_run_id'=>$this->run->id,'phase'=>$phase,'legacy_id'=>$id,'code'=>$code],['severity'=>$severity,'context'=>$context]);}
    private function reconcile(array $only):void {$lines=['# Full migration reconciliation','', 'Run: `'.($this->run?->run_uuid??'dry-run').'`',''];foreach($only as $phase){$c=$this->summary[$phase]??[];$lines[]='## '.$phase;$lines[]='- LEGACY: '.($c['source']??0);$lines[]='- MIGRÉS: '.($c['migrated']??0);$lines[]='- DÉJÀ PRÉSENTS: '.($c['existing']??0);$lines[]='- IGNORÉS VOLONTAIREMENT: '.($c['ignored']??0);$lines[]='- ANOMALIES: '.($c['anomalies']??0);$lines[]='- ERREURS: '.($c['errors']??0);$lines[]='';}$report=implode("\n",$lines);File::put(base_path($this->directory.'/full-migration-reconciliation.md'),$report);File::put(base_path('docs/generated/full-migration-reconciliation.md'),$report);}
}
