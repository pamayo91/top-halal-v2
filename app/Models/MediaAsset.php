<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['legacy_created_at' => 'datetime', 'legacy_updated_at' => 'datetime'];
    }

    public function variants()
    {
        return $this->hasMany(MediaVariant::class);
    }
}
