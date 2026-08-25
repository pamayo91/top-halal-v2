<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class MediaAsset extends Model {protected $guarded=[]; public function variants(){return $this->hasMany(MediaVariant::class);}}
