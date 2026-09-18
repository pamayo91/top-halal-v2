<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Services\Location\AddressSuggestionService;
use App\Services\Location\RestaurantLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagedRestaurantUpdater
{
    public function __construct(
        private AddressSuggestionService $suggestions,
        private RestaurantLocationService $locations,
        private RestaurantHours $hours,
        private RestaurantMediaManager $media,
    ) {}

    /** Update only product fields available to an authorised restaurant manager. */
    public function update(Restaurant $restaurant, array $data, Request $request): Restaurant
    {
        $selection = null;
        if ($request->boolean('location_changed')) {
            $selection = $this->suggestions->structuredFromToken((string) ($data['address_suggestion_token'] ?? ''));
            if ($selection === null) throw ValidationException::withMessages(['address_suggestion_token' => 'Cette suggestion a expiré. Recherchez l’adresse à nouveau.']);
        }

        return DB::transaction(function () use ($restaurant, $data, $request, $selection): Restaurant {
            $changes = ['name' => trim($data['name'])];
            if (array_key_exists('description', $data)) $changes['description'] = filled($data['description']) ? trim(strip_tags($data['description'])) : null;
            if (array_key_exists('phone', $data)) $changes['phone'] = filled($data['phone']) ? trim($data['phone']) : null;
            if (array_key_exists('halal_meat', $data)) $changes['has_halal_meat'] = (bool) $data['halal_meat'];
            if (array_key_exists('halal_chicken', $data)) $changes['has_halal_chicken'] = (bool) $data['halal_chicken'];
            $restaurant->fill($changes)->save();

            if (array_key_exists('categories', $data)) $restaurant->categories()->sync($data['categories'] ?? []);
            if (array_key_exists('features', $data)) $restaurant->features()->sync($data['features'] ?? []);
            if (array_key_exists('hours', $data)) $this->hours->sync($restaurant, $data['hours']);

            if ($selection !== null) {
                $this->locations->applySelectedSuggestion(
                    $restaurant,
                    $selection,
                    $request->boolean('map_moved') ? (float) $data['latitude'] : null,
                    $request->boolean('map_moved') ? (float) $data['longitude'] : null,
                    'owner_map',
                );
            } elseif ($request->boolean('map_moved')) {
                $this->locations->update($restaurant, [
                    'latitude' => (float) $data['latitude'],
                    'longitude' => (float) $data['longitude'],
                    'location_update_source' => 'owner_map',
                ]);
            }

            $this->media->detachMany($restaurant, $data['remove_media_ids'] ?? []);
            $this->media->attachUploads($restaurant, array_values(array_filter((array) $request->file('new_photos', []))));
            $removedMediaIds = collect($data['remove_media_ids'] ?? [])->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all();
            $this->media->syncOrder($restaurant, array_values(array_filter($data['media_order'] ?? [], fn ($id) => ! in_array((int) $id, $removedMediaIds, true))));
            if (collect(['website_url', 'instagram_url', 'facebook_url', 'tiktok_url'])->contains(fn ($field) => array_key_exists($field, $data))) {
                $this->syncOutboundLinks($restaurant, $data);
            }

            return $restaurant->fresh(['categories', 'features', 'openingHours', 'media.asset', 'outboundLinks']);
        });
    }

    private function syncOutboundLinks(Restaurant $restaurant, array $data): void
    {
        foreach (['website_url' => 'Site web', 'instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'tiktok_url' => 'TikTok'] as $field => $label) {
            if (! array_key_exists($field, $data)) continue;
            $link = $restaurant->outboundLinks()->where('label', $label)->first();
            $url = filled($data[$field] ?? null) ? trim($data[$field]) : null;
            if ($url === null) { $link?->delete(); continue; }
            if ($link) { $link->update(['destination_url' => $url]); continue; }
            $restaurant->outboundLinks()->create(['token' => Str::random(40), 'label' => $label, 'destination_url' => $url, 'is_active' => false]);
        }
    }
}
