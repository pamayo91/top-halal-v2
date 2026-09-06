<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('city_specialty_seo_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('city_code', 10);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('state', 10)->default('closed');
            $table->string('h1')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->longText('content_top')->nullable();
            $table->longText('content_bottom')->nullable();
            $table->timestamps();

            $table->unique(['city_code', 'category_id']);
            $table->index(['state', 'city_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_specialty_seo_pages');
    }
};
