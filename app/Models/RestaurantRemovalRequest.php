<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RestaurantRemovalRequest extends Model { protected $guarded=[]; protected function casts(): array { return ['submitted_at'=>'datetime','reviewed_at'=>'datetime']; } public function restaurant(){return $this->belongsTo(Restaurant::class);} public function user(){return $this->belongsTo(User::class);} public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');} }
