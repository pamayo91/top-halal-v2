<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantWebEnrichment extends Model
{
    public const TERMINAL = ['UPDATED', 'UNCHANGED', 'CLOSED_CONFIRMED_REVIEW', 'CLOSED_POSSIBLE_REVIEW', 'CLOSURE_CONFLICT', 'SOURCE_CONFLICT', 'INSUFFICIENT_DATA'];
    protected $guarded = [];
    protected function casts(): array { return ['sources'=>'array','matching'=>'array','facts'=>'array','description_sources'=>'array','closure_sources'=>'array','hours_before'=>'array','hours_after'=>'array','processed_at'=>'datetime','processing_started_at'=>'datetime']; }
    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
}
