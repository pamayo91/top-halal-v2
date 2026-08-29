<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('restaurants', function (Blueprint $t): void {
        $t->string('address_line1')->nullable()->after('address'); $t->string('address_line2')->nullable()->after('address_line1'); $t->string('country_code', 2)->nullable()->after('city_name'); $t->string('city_code', 10)->nullable()->index()->after('city_name');
        $t->string('geocoding_provider', 40)->nullable(); $t->string('geocoding_source_id')->nullable(); $t->string('geocoding_precision', 30)->nullable(); $t->string('geocoding_status', 30)->default('MISSING')->index(); $t->decimal('geocoding_score', 8, 6)->nullable(); $t->unsignedInteger('geocoding_distance_m')->nullable(); $t->string('geocoding_review_reason')->nullable(); $t->timestamp('geocoded_at')->nullable(); $t->timestamp('manually_verified_at')->nullable();
        $t->index(['geocoding_status', 'latitude', 'longitude'], 'restaurants_geo_status_coordinates_index');
    }); }
    public function down(): void { Schema::table('restaurants', function (Blueprint $t): void { $t->dropIndex('restaurants_geo_status_coordinates_index'); $t->dropIndex(['city_code']); $t->dropIndex(['geocoding_status']); $t->dropColumn(['address_line1','address_line2','country_code','city_code','geocoding_provider','geocoding_source_id','geocoding_precision','geocoding_status','geocoding_score','geocoding_distance_m','geocoding_review_reason','geocoded_at','manually_verified_at']); }); }
};
