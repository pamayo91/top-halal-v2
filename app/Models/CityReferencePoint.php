<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A locally stored official reference point for one canonical INSEE commune. */
class CityReferencePoint extends Model
{
    protected $primaryKey = 'city_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'synced_at' => 'datetime',
        ];
    }
}
