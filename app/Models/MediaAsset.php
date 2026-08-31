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

    public function deliveryUrl(?int $width = null): string
    {
        $parameters = [
            'asset' => $this,
            'version' => $this->checksum,
        ];

        if ($width !== null) {
            $parameters['width'] = $width;
        }

        return route('media.show', $parameters);
    }
}
