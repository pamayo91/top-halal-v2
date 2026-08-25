<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MigrationCheckpoint extends Model { protected $guarded=[]; protected function casts():array{return ['counters'=>'array'];} }
