<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commune_references', function (Blueprint $table): void {
            $table->string('city_code', 10)->primary();
            $table->string('city_name');
            $table->string('department_code', 3);
            $table->string('normalized_name')->index();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('commune_references');
    }
};
