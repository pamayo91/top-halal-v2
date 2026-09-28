<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::create('regression_deployment_snapshots',function(Blueprint $t):void{$t->id();$t->uuid('run_id')->unique();$t->string('git_revision',80)->nullable();$t->json('counts');$t->timestamp('captured_at');$t->timestamps();});} public function down():void{Schema::dropIfExists('regression_deployment_snapshots');} };
