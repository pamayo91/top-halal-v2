<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailDeliveryLog extends Model { protected $guarded = []; protected function casts(): array { return ['last_attempt_at' => 'datetime']; } }
