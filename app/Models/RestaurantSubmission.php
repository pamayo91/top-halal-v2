<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'email_verified_at' => 'datetime', 'email_verification_expires_at' => 'datetime', 'activation_expires_at' => 'datetime', 'rejected_at' => 'datetime', 'duplicate_details' => 'array'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
