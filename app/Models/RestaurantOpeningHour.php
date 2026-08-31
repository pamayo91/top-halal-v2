<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RestaurantOpeningHour extends Model { protected $guarded = []; protected function casts(): array { return ['is_closed' => 'boolean', 'is_open_24_hours' => 'boolean']; } }
