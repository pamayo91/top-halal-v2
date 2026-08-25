<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MigrationRun extends Model { protected $guarded=[]; protected function casts():array{return ['only'=>'array','started_at'=>'datetime','completed_at'=>'datetime'];} public function checkpoints(){return $this->hasMany(MigrationCheckpoint::class);} }
