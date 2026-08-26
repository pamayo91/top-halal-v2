<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Feature extends Model { protected $guarded = []; public function restaurants(): BelongsToMany { return $this->belongsToMany(Restaurant::class, 'restaurant_feature'); } }
