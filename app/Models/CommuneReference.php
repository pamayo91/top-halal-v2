<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A versioned, local copy of the official French commune directory. */
class CommuneReference extends Model
{
    protected $primaryKey = 'city_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
