<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RestaurantMedia extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $media): void {
            if ($media->media_asset_id === null && $media->legacy_attachment_id !== null) {
                $media->media_asset_id = MediaAsset::where('legacy_attachment_id', $media->legacy_attachment_id)->value('id');
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
