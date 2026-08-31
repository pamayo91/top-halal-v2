<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regression_sentinels', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('route_path')->nullable();
            $table->json('baseline');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regression_sentinels');
    }
};
