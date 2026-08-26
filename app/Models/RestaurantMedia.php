<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RestaurantMedia extends Model
{
    protected $guarded = [];

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'legacy_attachment_id', 'legacy_attachment_id');
    }
}
