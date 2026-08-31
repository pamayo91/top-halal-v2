<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegressionSentinel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['baseline' => 'array'];
    }
}
