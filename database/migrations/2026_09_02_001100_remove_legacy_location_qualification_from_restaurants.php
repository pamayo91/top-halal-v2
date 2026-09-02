<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropIndex('restaurants_geo_status_coordinates_index');
            $table->dropIndex(['geocoding_status']);
            $table->dropIndex(['address_confidence']);
            $table->dropIndex(['location_precision']);
            $table->dropIndex(['proximity_status']);
            $table->dropColumn([
                'location_review_reason', 'location_precision', 'address_confidence', 'geocoded_at',
                'geocoding_review_reason', 'geocoding_score', 'geocoding_status', 'geocoding_precision',
                'geocoding_source_id', 'geocoding_provider', 'geocoding_distance_m', 'proximity_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('geocoding_provider', 40)->nullable();
            $table->string('geocoding_source_id')->nullable();
            $table->string('geocoding_precision', 30)->nullable();
            $table->string('geocoding_status', 30)->default('MISSING')->index();
            $table->decimal('geocoding_score', 8, 6)->nullable();
            $table->unsignedInteger('geocoding_distance_m')->nullable();
            $table->string('geocoding_review_reason')->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->string('address_confidence', 30)->default('MISSING')->index();
            $table->string('location_precision', 30)->default('UNKNOWN')->index();
            $table->string('proximity_status', 30)->default('EXCLUDED')->index();
            $table->string('location_review_reason')->nullable();
            $table->index(['geocoding_status', 'latitude', 'longitude'], 'restaurants_geo_status_coordinates_index');
        });
    }
};
