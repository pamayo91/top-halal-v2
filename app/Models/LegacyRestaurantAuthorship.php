<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preserves a legacy listing author; it grants no ownership or permission. */
class LegacyRestaurantAuthorship extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
}
