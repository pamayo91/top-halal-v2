<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Category extends Model { protected $guarded = []; public function restaurants(): BelongsToMany { return $this->belongsToMany(Restaurant::class, 'restaurant_category'); } public function media(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'media_asset_id'); } }
