<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_wp_id')->unique();
            $table->unsignedBigInteger('legacy_author_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->index();
            $table->boolean('is_claimed')->default(false)->index();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable()->index();
            $table->string('city_name')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamp('legacy_published_at')->nullable();
            $table->timestamp('legacy_modified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_term_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_term_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_term_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->timestamps();
        });

        Schema::create('restaurant_category', function (Blueprint $table): void {
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['restaurant_id', 'category_id']);
        });

        Schema::create('restaurant_feature', function (Blueprint $table): void {
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->primary(['restaurant_id', 'feature_id']);
        });

        Schema::create('restaurant_location', function (Blueprint $table): void {
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->primary(['restaurant_id', 'location_id']);
        });

        Schema::create('restaurant_opening_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('day', 16)->nullable();
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->string('legacy_key')->nullable();
            $table->text('legacy_value')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'legacy_key']);
        });

        Schema::create('restaurant_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_attachment_id');
            $table->string('legacy_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['restaurant_id', 'legacy_attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_media');
        Schema::dropIfExists('restaurant_opening_hours');
        Schema::dropIfExists('restaurant_location');
        Schema::dropIfExists('restaurant_feature');
        Schema::dropIfExists('restaurant_category');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('features');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('restaurants');
    }
};
