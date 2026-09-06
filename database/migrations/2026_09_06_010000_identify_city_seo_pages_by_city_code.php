<?php

use App\Models\CitySeoPage;
use App\Services\AdministrativeGeography;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('city_seo_pages', function (Blueprint $table): void {
            $table->string('city_code', 10)->nullable()->after('city_name');
        });

        $geography = app(AdministrativeGeography::class);
        $codesByName = [];

        DB::table('restaurants')
            ->whereNotNull('city_name')
            ->where('city_name', '!=', '')
            ->whereNotNull('city_code')
            ->where('city_code', '!=', '')
            ->select('city_name', 'city_code')
            ->distinct()
            ->orderBy('city_name')
            ->orderBy('city_code')
            ->get()
            ->each(function (object $restaurant) use (&$codesByName, $geography): void {
                $code = $geography->canonicalCityCode((string) $restaurant->city_code);
                if ($code !== null) {
                    $codesByName[$restaurant->city_name][$code] = true;
                }
            });

        CitySeoPage::query()->orderBy('id')->get()->each(function (CitySeoPage $page) use ($codesByName): void {
            $codes = array_keys($codesByName[$page->city_name] ?? []);
            if (count($codes) === 1) {
                $page->update(['city_code' => $codes[0]]);
            }
        });

        Schema::table('city_seo_pages', function (Blueprint $table): void {
            $table->dropUnique('city_seo_pages_city_name_unique');
            $table->unique('city_code');
        });
    }

    public function down(): void
    {
        Schema::table('city_seo_pages', function (Blueprint $table): void {
            $table->dropUnique('city_seo_pages_city_code_unique');
            $table->dropColumn('city_code');
        });
    }
};
