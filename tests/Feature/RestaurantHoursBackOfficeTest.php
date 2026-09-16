<?php

namespace Tests\Feature;

use App\Filament\Resources\RestaurantResource\Pages\EditRestaurant;
use App\Models\{Restaurant, RestaurantOpeningHour, User};
use App\Services\RestaurantHours;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Tests\TestCase;

class RestaurantHoursBackOfficeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_filament_loads_existing_hours_and_saves_closed_and_multiple_slots_to_the_existing_relation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $restaurant = Restaurant::create(['legacy_wp_id' => 950001, 'name' => 'Horaires BO', 'slug' => 'horaires-bo', 'status' => 'draft']);
        $monday = RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'monday', 'slot' => 1, 'opens_at' => '11:00', 'closes_at' => '14:00', 'legacy_key' => 'legacy:monday:1']);
        $mondayEvening = RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'monday', 'slot' => 2, 'opens_at' => '18:00', 'closes_at' => '23:00', 'legacy_key' => 'legacy:monday:2']);

        $this->actingAs($admin)->get("/admin/restaurants/{$restaurant->id}/edit")
            ->assertOk()
            ->assertSee('Horaires')
            ->assertSee('Horaires d’ouverture');

        $hours = app(RestaurantHours::class)->editorState($restaurant->openingHours()->get());
        $this->assertSame('slots', $hours[0]['status']);
        $this->assertSame('11:00', $hours[0]['slots'][0]['opens_at']);
        $this->assertSame('18:00', $hours[0]['slots'][1]['opens_at']);

        $hours[0]['slots'] = [
            ['id' => $monday->id, 'opens_at' => '10:30', 'closes_at' => '14:30'],
            ['id' => $mondayEvening->id, 'opens_at' => '18:30', 'closes_at' => '23:30'],
            ['opens_at' => '23:40', 'closes_at' => '23:50'],
        ];
        $hours[6]['status'] = 'closed';

        $undoRepeaterFake = Repeater::fake();
        Livewire::actingAs($admin)
            ->test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
            ->assertSet('data.hours.0.status', 'slots')
            ->assertSet('data.hours.0.slots.0.opens_at', '11:00')
            ->assertSet('data.hours.0.slots.1.opens_at', '18:00')
            ->fillForm(['hours' => $hours])
            ->assertSet('data.hours.0.slots', $hours[0]['slots'])
            ->call('save')
            ->assertHasNoFormErrors();
        $undoRepeaterFake();

        $stored = $restaurant->fresh()->openingHours()->orderBy('day')->orderBy('slot')->get();
        $this->assertCount(9, $stored);
        $this->assertDatabaseHas('restaurant_opening_hours', ['id' => $monday->id, 'opens_at' => '10:30:00', 'closes_at' => '14:30:00', 'legacy_key' => 'legacy:monday:1']);
        $this->assertDatabaseHas('restaurant_opening_hours', ['id' => $mondayEvening->id, 'opens_at' => '18:30:00', 'closes_at' => '23:30:00', 'legacy_key' => 'legacy:monday:2']);
        $this->assertDatabaseHas('restaurant_opening_hours', ['restaurant_id' => $restaurant->id, 'day' => 'monday', 'slot' => 3, 'opens_at' => '23:40:00', 'closes_at' => '23:50:00', 'legacy_key' => 'admin:monday:3']);
        $this->assertDatabaseHas('restaurant_opening_hours', ['restaurant_id' => $restaurant->id, 'day' => 'sunday', 'slot' => 1, 'is_closed' => true]);
    }

    public function test_hours_editor_rejects_overlapping_slots(): void
    {
        $input = app(RestaurantHours::class)->editorState(collect());
        $input[0]['status'] = 'slots';
        $input[0]['slots'] = [
            ['opens_at' => '10:00', 'closes_at' => '14:00'],
            ['opens_at' => '13:30', 'closes_at' => '18:00'],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RestaurantHours::class)->sync(Restaurant::create(['legacy_wp_id' => 950002, 'name' => 'Validation BO', 'slug' => 'validation-bo', 'status' => 'draft']), $input);
    }
}
