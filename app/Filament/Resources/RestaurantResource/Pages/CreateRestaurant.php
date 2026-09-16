<?php

namespace App\Filament\Resources\RestaurantResource\Pages;

use App\Filament\Pages\CreateAuditedRecord;
use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Services\Location\AddressSuggestionService;
use App\Services\Location\RestaurantLocationService;
use App\Services\RestaurantHours;
use App\Services\RestaurantSlugService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRestaurant extends CreateAuditedRecord
{
    protected static string $resource = RestaurantResource::class;
    protected array $hours = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->hours = $data['hours'] ?? [];
        app(RestaurantHours::class)->validatedEditorRows($this->hours);
        unset($data['hours']);
        unset($data['location_update_source']);
        $data['legacy_wp_id'] = random_int(1000000000, 2000000000);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $token = (string) ($data['address_suggestion'] ?? '');
        unset($data['address_suggestion']);
        $selection = app(AddressSuggestionService::class)->structuredFromToken($token);
        if ($selection === null) throw ValidationException::withMessages(['data.address_suggestion' => 'Cette suggestion a expiré. Recherchez l’adresse à nouveau.']);

        if (($data['slug_automated'] ?? false) === true) {
            $data['slug'] = app(RestaurantSlugService::class)->generate($data['name'], $selection['city_name'] ?? null, $selection['postal_code'] ?? null);
        }
        unset($data['slug_automated']);

        return DB::transaction(function () use ($data, $selection): Model {
            /** @var Restaurant $restaurant */
            $restaurant = parent::handleRecordCreation($data);
            app(RestaurantLocationService::class)->applySelectedSuggestion($restaurant, $selection);
            app(RestaurantHours::class)->sync($restaurant, $this->hours);

            return $restaurant;
        });
    }
}
