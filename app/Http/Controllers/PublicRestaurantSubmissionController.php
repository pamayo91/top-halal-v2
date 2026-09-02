<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicRestaurantSubmissionRequest;
use App\Models\{Category, Feature, Restaurant, RestaurantMedia, RestaurantSubmission};
use App\Services\Location\{AddressSuggestionService, DuplicateRestaurantDetector, RestaurantLocationService};
use App\Services\MediaIngestor;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicRestaurantSubmissionController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function create(): View
    {
        return view('public.restaurant-submission.create', [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'features' => Feature::query()->orderBy('name')->get(['id', 'name']),
            'days' => ['monday' => 'Lundi', 'tuesday' => 'Mardi', 'wednesday' => 'Mercredi', 'thursday' => 'Jeudi', 'friday' => 'Vendredi', 'saturday' => 'Samedi', 'sunday' => 'Dimanche'],
        ]);
    }

    public function addressAutocomplete(Request $request, AddressSuggestionService $suggestions): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json([
            'data' => collect($suggestions->suggest($data['q']))
                ->map(function (array $item) use ($suggestions): array {
                    $structured = $suggestions->publicStructured($item['feature']);
                    return [
                        'token' => $item['token'],
                        'label' => $item['label'],
                        'address' => $structured,
                    ];
                })
                ->all(),
        ]);
    }

    public function duplicates(Request $request, DuplicateRestaurantDetector $duplicates): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:41,52'],
            'longitude' => ['nullable', 'numeric', 'between:-5.5,10'],
        ]);

        return response()->json(['data' => $duplicates->publicCandidates($data)->map(function (Restaurant $restaurant): array {
            return [
                'name' => $restaurant->name,
                'city' => $restaurant->city_name,
                'url' => route('restaurants.show', $restaurant->slug),
                'claim_url' => route('claims.create', $restaurant),
            ];
        })->all()]);
    }

    public function store(StorePublicRestaurantSubmissionRequest $request, AddressSuggestionService $suggestions, RestaurantLocationService $locations, MediaIngestor $media): RedirectResponse
    {
        $data = $request->validated();
        $location = $this->locationData($request, $suggestions, $data);
        $hours = $this->hours($data['hours']);

        $restaurant = DB::transaction(function () use ($data, $location, $hours, $locations, $media, $request): Restaurant {
            $restaurant = Restaurant::create([
                'name' => trim($data['name']),
                'slug' => $this->submissionSlug($data['name']),
                'status' => 'pending',
                'has_halal_meat' => (bool) ($data['halal_meat'] ?? false),
                'has_halal_chicken' => (bool) ($data['halal_chicken'] ?? false),
                'description' => filled($data['description'] ?? null) ? trim(strip_tags($data['description'])) : null,
                'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
                'contact_email' => Str::lower(trim($data['email'])),
                'address' => $this->displayAddress($location),
            ]);

            $locations->applySelectedSuggestion(
                $restaurant,
                $location,
                $request->boolean('map_moved') ? (float) $data['latitude'] : null,
                $request->boolean('map_moved') ? (float) $data['longitude'] : null,
            );
            $restaurant->categories()->sync($data['categories'] ?? []);
            $restaurant->features()->sync($data['features'] ?? []);

            foreach ($hours as $hour) $restaurant->openingHours()->create($hour);

            $uploads = [$request->file('cover_photo')];
            foreach ((array) $request->file('gallery_photos', []) as $upload) $uploads[] = $upload;
            foreach ($uploads as $sortOrder => $upload) {
                $asset = $media->ingest($upload, $restaurant->name);
                RestaurantMedia::create([
                    'restaurant_id' => $restaurant->id,
                    'media_asset_id' => $asset->id,
                    'sort_order' => $sortOrder,
                    'status' => 'pending',
                ]);
            }

            foreach (['website_url' => 'Site web', 'instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'tiktok_url' => 'TikTok'] as $field => $label) {
                if (! filled($data[$field] ?? null)) continue;
                $restaurant->outboundLinks()->create([
                    'token' => Str::random(40),
                    'label' => $label,
                    'destination_url' => $data[$field],
                    // A moderator must explicitly enable a proposed outbound destination.
                    'is_active' => false,
                ]);
            }

            RestaurantSubmission::create([
                'restaurant_id' => $restaurant->id,
                'submitter_email' => Str::lower(trim($data['email'])),
                'submitter_role' => $data['submitter_role'],
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                'submitted_at' => now(),
            ]);

            return $restaurant;
        });

        return redirect()->route('restaurant-submissions.thanks')->with('submitted_restaurant', $restaurant->name);
    }

    public function thanks(): View
    {
        abort_unless(session()->has('submitted_restaurant'), 404);

        return view('public.restaurant-submission.thanks', ['restaurantName' => session('submitted_restaurant')]);
    }

    private function locationData(StorePublicRestaurantSubmissionRequest $request, AddressSuggestionService $suggestions, array $data): array
    {
        $token = $data['address_suggestion_token'] ?? null;
        if (filled($token)) {
            $location = $suggestions->structuredFromToken($token);
            if ($location === null) {
                throw ValidationException::withMessages(['address_line1' => 'Cette suggestion a expiré. Recherchez l’adresse à nouveau.']);
            }
            return $location;
        }

        throw ValidationException::withMessages(['address_suggestion_token' => 'Sélectionnez une adresse proposée par la Géoplateforme.']);
    }

    private function displayAddress(array $location): string
    {
        return implode(', ', array_filter([
            $location['address_line1'] ?? null,
            $location['address_line2'] ?? null,
            trim(($location['postal_code'] ?? '').' '.($location['city_name'] ?? '')) ?: null,
            ($location['country_code'] ?? null) === 'FR' ? 'France' : null,
        ]));
    }

    private function submissionSlug(string $name): string
    {
        return (Str::slug($name) ?: 'restaurant').'-'.Str::lower(Str::random(8));
    }

    /** @return array<int, array<string, mixed>> */
    private function hours(array $input): array
    {
        $hours = [];
        foreach (self::DAYS as $day) {
            $entry = $input[$day];
            if ($entry['status'] === 'closed') {
                $hours[] = ['day' => $day, 'slot' => 1, 'is_closed' => true, 'is_open_24_hours' => false, 'legacy_key' => 'public:'.$day.':1'];
                continue;
            }
            if ($entry['status'] === 'all_day') {
                $hours[] = ['day' => $day, 'slot' => 1, 'opens_at' => '00:00', 'closes_at' => '23:59', 'is_closed' => false, 'is_open_24_hours' => true, 'legacy_key' => 'public:'.$day.':1'];
                continue;
            }
            $hours[] = ['day' => $day, 'slot' => 1, 'opens_at' => $entry['first_open'], 'closes_at' => $entry['first_close'], 'is_closed' => false, 'is_open_24_hours' => false, 'legacy_key' => 'public:'.$day.':1'];
            if (filled($entry['second_open'] ?? null)) $hours[] = ['day' => $day, 'slot' => 2, 'opens_at' => $entry['second_open'], 'closes_at' => $entry['second_close'], 'is_closed' => false, 'is_open_24_hours' => false, 'legacy_key' => 'public:'.$day.':2'];
        }

        return $hours;
    }
}
