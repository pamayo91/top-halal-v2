<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicRestaurantSubmissionRequest;
use App\Models\{Category, Feature, Restaurant, RestaurantClaim, RestaurantMedia, RestaurantSubmission, User};
use App\Services\Location\{AddressSuggestionService, DuplicateRestaurantDetector, RestaurantLocationService};
use App\Services\{MediaIngestor, RestaurantHours, RestaurantSlugService, RestaurantSubmissionMailer};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Hash, URL};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicRestaurantSubmissionController extends Controller
{
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
                'claim_url' => $restaurant->isClaimable() ? route('claims.create', $restaurant) : null,
            ];
        })->all()]);
    }

    public function store(StorePublicRestaurantSubmissionRequest $request, AddressSuggestionService $suggestions, RestaurantLocationService $locations, MediaIngestor $media, RestaurantSubmissionMailer $mailer, DuplicateRestaurantDetector $duplicates): RedirectResponse
    {
        $data = $request->validated();
        $location = $this->locationData($request, $suggestions, $data);
        $hours = app(RestaurantHours::class)->publicSubmissionRows($data['hours']);
        $duplicateAssessment = $duplicates->assess([
            ...$location,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'latitude' => $request->boolean('map_moved') ? $data['latitude'] : $location['latitude'],
            'longitude' => $request->boolean('map_moved') ? $data['longitude'] : $location['longitude'],
        ]);

        if ($duplicateAssessment->certain->isNotEmpty()) {
            $candidate = $duplicateAssessment->certain->first()['restaurant'];
            return $this->duplicateRejected($request, $candidate);
        }
        $duplicateDetails = $duplicateAssessment->potential->take(10)->map(fn (array $match): array => [
            'candidate_id' => $match['restaurant']->id,
            'name' => $match['restaurant']->name,
            'status' => $match['restaurant']->trashed() ? 'trashed' : $match['restaurant']->status,
            'reason' => $match['reason'],
        ])->values()->all();

        $token = Str::random(64);
        $restaurant = DB::transaction(function () use ($data, $location, $hours, $locations, $media, $request, $token, $duplicateDetails): Restaurant {
            $restaurant = Restaurant::create([
                'name' => trim($data['name']),
                'slug' => app(RestaurantSlugService::class)->generate($data['name'], $location['city_name'] ?? null, $location['postal_code'] ?? null),
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
                'user_id' => $request->user()?->id,
                'submitter_email' => Str::lower(trim($data['email'])),
                'submitter_role' => $data['submitter_role'],
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                'submitted_at' => now(),
                'owner_full_name' => $data['owner_full_name'] ?? null,
                'owner_company' => $data['owner_company'] ?? null,
                'owner_siret' => $data['owner_siret'] ?? null,
                'owner_certified' => $request->boolean('owner_certified'),
                'status' => 'pending_email_verification',
                'duplicate_signal' => $duplicateDetails === [] ? null : 'potential',
                'duplicate_details' => $duplicateDetails === [] ? null : $duplicateDetails,
                'email_verification_token' => hash('sha256', $token),
                'email_verification_expires_at' => now()->addHours(24),
            ]);

            return $restaurant;
        });

        $submission = $restaurant->submission()->with('restaurant')->firstOrFail();
        $mailer->verification($submission, URL::temporarySignedRoute('restaurant-submissions.verify', now()->addHours(24), ['submission' => $submission, 'token' => $token]));

        return redirect()->route('restaurant-submissions.thanks')->with('submitted_restaurant', $restaurant->name);
    }

    private function duplicateRejected(Request $request, Restaurant $candidate): RedirectResponse
    {
        $public = ! $candidate->trashed() && $candidate->status === 'published';
        return redirect()->back()->withInput()->withErrors([
            'name' => 'Ce restaurant semble déjà présent dans l’annuaire. Vérifiez la fiche existante avant de proposer une nouvelle adresse.',
        ])->with('duplicate_restaurant', [
            'name' => $candidate->name,
            'url' => $public ? route('restaurants.show', $candidate->slug) : null,
            'claim_url' => $public && $candidate->isClaimable() ? route('claims.create', $candidate) : null,
        ]);
    }

    public function thanks(): View
    {
        abort_unless(session()->has('submitted_restaurant'), 404);

        return view('public.restaurant-submission.thanks', ['restaurantName' => session('submitted_restaurant')]);
    }

    public function verify(RestaurantSubmission $submission, string $token, RestaurantSubmissionMailer $mailer): View
    {
        if (! hash_equals((string) $submission->email_verification_token, hash('sha256', $token))) abort(404);
        if ($submission->status !== 'pending_email_verification') {
            return $this->linkStatus('Cette demande a déjà été traitée.', 'Votre adresse e-mail était déjà confirmée.', null, route('restaurants.index'), 'Voir les restaurants');
        }
        if (! $submission->email_verification_expires_at?->isFuture()) {
            return $this->linkStatus('Ce lien a expiré.', 'Vous pouvez recevoir un nouveau lien pour continuer.', route('restaurant-submissions.verify.resend', [$submission, $token]));
        }
        $confirmed = DB::transaction(function () use ($submission, $token): ?array {
            $submission = RestaurantSubmission::query()->with('restaurant')->lockForUpdate()->findOrFail($submission->id);
            if ($submission->status !== 'pending_email_verification') return null;
            abort_unless($submission->email_verification_expires_at?->isFuture() && hash_equals((string) $submission->email_verification_token, hash('sha256', $token)), 404);
            $email = Str::lower(trim($submission->submitter_email));
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            if (! $user) {
                $user = User::create([
                    'name' => $submission->owner_full_name ?: $submission->restaurant->name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'login_enabled' => true,
                    'role' => 'user',
                    'status' => 'active',
                    'must_change_password' => true,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $needsActivation = $user->needsRestaurantSubmissionActivation();

            if ($needsActivation) {
                $user->prepareForRestaurantSubmissionActivation();
            }

            $activationToken = $needsActivation ? Str::random(64) : null;
            $submission->update([
                'user_id' => $user->id,
                'status' => 'pending_admin_review',
                'email_verified_at' => now(),
                'activation_token' => $activationToken ? hash('sha256', $activationToken) : null,
                'activation_expires_at' => $activationToken ? now()->addDays(7) : null,
            ]);

            if ($submission->submitter_role === 'owner') {
                RestaurantClaim::query()->firstOrCreate(
                    ['restaurant_id' => $submission->restaurant_id, 'user_id' => $user->id],
                    [
                        'email' => $email,
                        'full_name' => $submission->owner_full_name ?: $user->name,
                        'company' => $submission->owner_company,
                        'siret' => $submission->owner_siret,
                        'certified' => $submission->owner_certified,
                        'source' => 'new_submission',
                        'status' => 'pending_publication',
                        'email_verified_at' => now(),
                        'submitted_at' => now(),
                    ],
                );
            }

            return ['submission' => $submission->fresh('restaurant'), 'activation_token' => $activationToken, 'needs_activation' => $needsActivation];
        });

        if ($confirmed) {
            $activationUrl = $confirmed['activation_token']
                ? route('restaurant-submissions.activate', ['submission' => $confirmed['submission'], 'token' => $confirmed['activation_token']])
                : null;
            $mailer->confirmed($confirmed['submission'], $activationUrl);
            $mailer->notifyTeamForReview($confirmed['submission']);
        }

        return view('public.restaurant-submission.email-verified', ['alreadyConfirmed' => $confirmed === null]);
    }

    public function resendVerification(RestaurantSubmission $submission, string $token, RestaurantSubmissionMailer $mailer): RedirectResponse
    {
        [$submission, $newToken] = DB::transaction(function () use ($submission, $token): array {
            $submission = RestaurantSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($submission->status === 'pending_email_verification' && ! $submission->email_verification_expires_at?->isFuture() && hash_equals((string) $submission->email_verification_token, hash('sha256', $token)), 404);
            $newToken = Str::random(64);
            $submission->update(['email_verification_token' => hash('sha256', $newToken), 'email_verification_expires_at' => now()->addHours(24)]);
            return [$submission->fresh('restaurant'), $newToken];
        });
        $mailer->verification($submission, URL::temporarySignedRoute('restaurant-submissions.verify', $submission->email_verification_expires_at, ['submission' => $submission, 'token' => $newToken]));
        return back()->with('status', 'Un nouveau lien vient d’être envoyé.');
    }

    private function linkStatus(string $title, string $message, ?string $resendUrl = null, ?string $exitUrl = null, ?string $exitLabel = null): View
    {
        return view('public.expiring-link-status', compact('title', 'message', 'resendUrl', 'exitUrl', 'exitLabel') + ['eyebrow' => 'Vérification e-mail']);
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

}
