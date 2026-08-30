<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_web_enrichments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->index();
            $table->string('status', 40)->default('PENDING')->index();
            $table->string('reason', 255)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->json('sources')->nullable();
            $table->string('hours_source')->nullable();
            $table->json('description_sources')->nullable();
            $table->json('closure_sources')->nullable();
            $table->json('hours_before')->nullable();
            $table->json('hours_after')->nullable();
            $table->text('description_before')->nullable();
            $table->text('description_after')->nullable();
            $table->text('technical_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void { Schema::dropIfExists('restaurant_web_enrichments'); }
};
