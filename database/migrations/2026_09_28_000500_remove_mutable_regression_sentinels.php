<?php
use App\Models\RegressionSentinel; use Illuminate\Database\Migrations\Migration;
return new class extends Migration { public function up():void{RegressionSentinel::query()->delete();} public function down():void{} };
