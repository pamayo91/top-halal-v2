<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Location extends Model { protected $guarded = []; public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); } public function restaurants(): BelongsToMany { return $this->belongsToMany(Restaurant::class, 'restaurant_location'); } }
