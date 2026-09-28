<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RegressionDeploymentSnapshot extends Model { protected $guarded=[]; protected function casts():array{return ['counts'=>'array'];} }
