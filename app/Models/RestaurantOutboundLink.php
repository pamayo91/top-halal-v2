<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RestaurantOutboundLink extends Model { protected $guarded = []; protected $hidden = ['destination_url']; protected function casts(): array { return ['is_active' => 'boolean']; } public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); } }
