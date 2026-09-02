<?php

namespace App\Filament\Resources\RestaurantResource\Pages;

use App\Filament\Pages\CreateAuditedRecord;
use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Services\Location\AddressSuggestionService;
use App\Services\Location\RestaurantLocationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRestaurant extends CreateAuditedRecord
{
    protected static string $resource = RestaurantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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

        /** @var Restaurant $restaurant */
        $restaurant = parent::handleRecordCreation($data);
        app(RestaurantLocationService::class)->applySelectedSuggestion($restaurant, $selection);

        return $restaurant;
    }
}
