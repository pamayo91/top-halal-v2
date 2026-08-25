<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantReview extends Model
{
    protected $guarded = [];
    protected $hidden = ['author_email'];
    protected function casts(): array { return ['approved_at' => 'datetime']; }
    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
}
