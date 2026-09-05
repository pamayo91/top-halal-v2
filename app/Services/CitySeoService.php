<?php
namespace App\Services;
use App\Models\{CitySeoPage,Restaurant,Setting};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
class CitySeoService {
 private const KEY='city-seo-stats-v1';
 public function threshold():int{return max(1,(int)(Setting::where('key','city_seo_minimum_restaurants')->value('value')['value']??5));}
 public function forget():void{Cache::forget(self::KEY);app(CityPageResolver::class)->forget();}
 public function cities():Collection{return Cache::rememberForever(self::KEY,function(){ $configs=CitySeoPage::all()->keyBy('city_name');$threshold=$this->threshold();return Restaurant::where('status','published')->whereNotNull('city_name')->where('city_name','!=','')->selectRaw('city_name,count(*) restaurants_count')->groupBy('city_name')->orderByDesc('restaurants_count')->orderBy('city_name')->get()->map(function($row)use($configs,$threshold){$config=$configs->get($row->city_name);$state=$config?->state??'auto';$open=$state==='forced_open'||($state!=='forced_closed'&&$row->restaurants_count>=$threshold);return (object)['city_name'=>$row->city_name,'slug'=>Str::slug($row->city_name),'restaurants_count'=>(int)$row->restaurants_count,'config'=>$config,'open'=>$open];});});}
 public function city(string $name):?object{return $this->cities()->firstWhere('city_name',$name);}
 public function isOpen(string $name):bool{return (bool)($this->city($name)?->open);}
}
