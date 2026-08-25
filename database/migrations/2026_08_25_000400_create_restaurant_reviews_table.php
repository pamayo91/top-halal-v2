<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_wp_review_id')->nullable()->unique();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_user_id')->nullable()->index();
            $table->string('author_name', 100);
            $table->string('author_email')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('status', 20)->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('restaurant_reviews'); }
};
