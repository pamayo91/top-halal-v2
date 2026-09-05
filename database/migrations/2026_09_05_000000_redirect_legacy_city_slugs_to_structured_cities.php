<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('locations') || ! Schema::hasTable('restaurant_location') || ! Schema::hasTable('redirect_rules')) {
            return;
        }

        $citySlugs = DB::table('restaurants')
            ->where('status', 'published')
            ->whereNotNull('city_name')
            ->where('city_name', '!=', '')
            ->distinct()
            ->pluck('city_name')
            ->mapWithKeys(fn (string $city): array => [Str::slug($city) => true]);

        $legacyCities = DB::table('locations as location')
            ->join('restaurant_location as pivot', 'pivot.location_id', '=', 'location.id')
            ->join('restaurants as restaurant', 'restaurant.id', '=', 'pivot.restaurant_id')
            ->where('restaurant.status', 'published')
            ->whereNotNull('restaurant.city_name')
            ->where('restaurant.city_name', '!=', '')
            ->select('location.slug as legacy_slug', 'restaurant.city_name')
            ->get()
            ->groupBy('legacy_slug');

        foreach ($legacyCities as $legacySlug => $rows) {
            if ($citySlugs->has($legacySlug)) {
                continue;
            }

            if (DB::table('redirect_rules')->where('source_path', '/restos/'.$legacySlug)->where('match_type', 'exact')->whereNull('query_pattern')->exists()) {
                continue;
            }

            $targets = $rows->pluck('city_name')->map(fn (string $city): string => Str::slug($city))->unique()->values();
            $destination = $targets->count() === 1 ? '/restos/'.$targets->first() : '/';
            $reason = $targets->count() === 1
                ? 'Legacy Geography city slug replaced by structured restaurant city.'
                : 'Legacy Geography slug has no single structured city target; approved fallback to homepage.';

            DB::table('redirect_rules')->updateOrInsert(
                ['source_path' => '/restos/'.$legacySlug, 'match_type' => 'exact', 'query_pattern' => null],
                ['destination' => $destination, 'status_code' => 301, 'preserve_query' => false, 'priority' => 50, 'is_active' => true, 'origin' => 'city_page_cutover', 'source_rule' => $reason, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('redirect_rules')->where('origin', 'city_page_cutover')->delete();
    }
};
