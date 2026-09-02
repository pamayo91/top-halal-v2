<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RepairAddressLine1CommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_only_updates_address_line1_and_is_idempotent(): void
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 17128,
            'name' => 'Abdallah Suleiman',
            'slug' => 'abdallah-suleiman',
            'status' => 'published',
            'address' => '54 Boulevard de La libération 13001 Marseille',
            'address_line1' => '54 Boulevard de La libération 13001 Marseille',
            'address_line2' => 'Bâtiment A',
            'postal_code' => '13001',
            'city_name' => 'Marseille',
            'city_code' => '13201',
            'country_code' => 'FR',
            'latitude' => '43.2997549',
            'longitude' => '5.3876106',
        ]);
        $protected = $restaurant->only([
            'address', 'address_line2', 'postal_code', 'city_name', 'city_code', 'country_code',
            'latitude', 'longitude',
        ]);
        $out = 'docs/generated/testing-address-line1-repair.json';

        $this->artisan('data:repair-address-line1', ['--apply' => true, '--expect' => 1, '--out' => $out])->assertSuccessful();
        $restaurant->refresh();
        $this->assertSame('54 Boulevard de La libération', $restaurant->address_line1);
        $this->assertSame($protected, $restaurant->only(array_keys($protected)));

        $this->artisan('data:repair-address-line1', ['--apply' => true, '--expect' => 0, '--out' => $out])->assertSuccessful();
        $report = json_decode(File::get(base_path($out)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $report['stats']['corrected']);
        File::delete(base_path($out));
    }

    public function test_it_keeps_ambiguous_rows_unchanged(): void
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 1, 'name' => 'Mismatch', 'slug' => 'mismatch', 'status' => 'published',
            'address' => '1 Place de la Mairie 13100 Aix-en-Provence',
            'address_line1' => '1 Place de la Mairie 13100 Aix-en-Provence',
            'postal_code' => '13290', 'city_name' => 'Aix-en-Provence',
        ]);
        $out = 'docs/generated/testing-address-line1-ambiguous.json';

        $this->artisan('data:repair-address-line1', ['--out' => $out])->assertSuccessful();
        $restaurant->refresh();
        $this->assertSame('1 Place de la Mairie 13100 Aix-en-Provence', $restaurant->address_line1);
        $report = json_decode(File::get(base_path($out)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['stats']['ambiguous']);
        $this->assertSame([], $report['candidates']);
        File::delete(base_path($out));
    }

    public function test_it_can_remove_an_explicit_historical_suffix_without_changing_structured_data(): void
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 13499, 'name' => 'Le Pôle Café', 'slug' => 'le-pole-cafe', 'status' => 'published',
            'address' => '1 Rue Charles Duchesne 13100 Aix en Provence',
            'address_line1' => '1 Rue Charles Duchesne 13100 Aix en Provence',
            'postal_code' => '13290', 'city_name' => 'Aix-en-Provence', 'city_code' => '13001',
            'latitude' => '43.522', 'longitude' => '5.449',
        ]);
        $protected = $restaurant->only(['address', 'postal_code', 'city_name', 'city_code', 'latitude', 'longitude']);
        $out = 'docs/generated/testing-address-line1-visible-suffix.json';

        $this->artisan('data:repair-address-line1', ['--apply' => true, '--visible-suffix' => true, '--expect' => 1, '--out' => $out])->assertSuccessful();
        $restaurant->refresh();
        $this->assertSame('1 Rue Charles Duchesne', $restaurant->address_line1);
        $this->assertSame($protected, $restaurant->only(array_keys($protected)));
        File::delete(base_path($out));
    }
}
