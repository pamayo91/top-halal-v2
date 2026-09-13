<?php

namespace App\Services;

use App\Exceptions\RestaurantReviewOwnershipException;
use App\Models\{Article, Comment, ContributionVerification, Page, Restaurant, RestaurantReview, User};
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, URL};
use Illuminate\Support\Str;

class ContributionIdentityService
{
    private const SESSION_KEY = 'contribution_identity_proofs';

    /** @return array{verified: bool, verification: ?ContributionVerification} */
    public function submitReview(Request $request, Restaurant $restaurant, array $data): array
    {
        return $this->submit($request, 'review', 'restaurant', $restaurant->id, $data);
    }

    /** @return array{verified: bool, verification: ?ContributionVerification} */
    public function submitComment(Request $request, Article|Page $content, array $data): array
    {
        return $this->submit($request, 'comment', $content instanceof Article ? 'article' : 'page', $content->id, $data);
    }

    /** @return array{contribution_type: string, contribution_id: ?int, destination_url: string, review_ownership_forbidden?: bool} */
    public function verify(Request $request, ContributionVerification $verification, string $token): array
    {
        return DB::transaction(function () use ($request, $verification, $token): array {
            $verification = ContributionVerification::query()->lockForUpdate()->findOrFail($verification->id);

            abort_unless(
                $verification->used_at === null
                && $verification->expires_at->isFuture()
                && hash_equals($verification->token_hash, hash('sha256', $token)),
                404,
            );

            $user = $this->identityFor($verification);
            try {
                $contribution = $this->createContribution($verification, $user);
            } catch (RestaurantReviewOwnershipException) {
                $verification->update([
                    'user_id' => $user->id,
                    'used_at' => now(),
                ]);

                $this->rememberProof($request, $user);

                return [
                    'contribution_type' => 'review',
                    'contribution_id' => null,
                    'destination_url' => $this->destinationUrl($verification),
                    'review_ownership_forbidden' => true,
                ];
            }

            $verification->update([
                'user_id' => $user->id,
                'used_at' => now(),
                'created_contribution_type' => $verification->contribution_type,
                'created_contribution_id' => $contribution->id,
            ]);

            $this->rememberProof($request, $user);

            return [
                'contribution_type' => $verification->contribution_type,
                'contribution_id' => $contribution->id,
                'destination_url' => $this->destinationUrl($verification),
            ];
        });
    }

    /** @return array{verified: bool, verification: ?ContributionVerification} */
    private function submit(Request $request, string $contributionType, string $targetType, int $targetId, array $data): array
    {
        $email = $request->user()?->email ?? Str::lower(trim((string) $data['email']));
        $user = $request->user() ?: User::query()->where('email', $email)->first();

        if ($user && ($request->user()?->is($user) || $this->hasProof($request, $user))) {
            $this->createDirectContribution($contributionType, $targetType, $targetId, $user, $data, $email);

            return ['verified' => true, 'verification' => null];
        }

        $token = Str::random(64);
        $verification = ContributionVerification::create([
            'email' => $email,
            'author_name' => trim($data['name']),
            'contribution_type' => $contributionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => $this->payload($contributionType, $data),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours((int) config('contributions.verification_expire_hours')),
        ]);

        $url = URL::temporarySignedRoute('contributions.verify', $verification->expires_at, ['verification' => $verification, 'token' => $token]);
        app(TransactionalMailService::class)->queue('contribution_email_verification', $email, [
            'site_name' => config('app.name', 'Top Halal'),
            'user_name' => $verification->author_name,
            'verification_url' => $url,
            'contribution_label' => $contributionType === 'review' ? 'avis' : 'commentaire',
        ]);

        return ['verified' => false, 'verification' => $verification];
    }

    private function identityFor(ContributionVerification $verification): User
    {
        $email = Str::lower(trim($verification->email));
        $user = User::query()->where('email', $email)->lockForUpdate()->first();

        if (! $user) {
            try {
                $user = User::create([
                    'name' => $verification->author_name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(64)),
                    'login_enabled' => false,
                    'role' => 'user',
                    'status' => 'active',
                    'must_change_password' => false,
                ]);
            } catch (QueryException) {
                $user = User::query()->where('email', $email)->lockForUpdate()->firstOrFail();
            }
        }

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function createContribution(ContributionVerification $verification, User $user): RestaurantReview|Comment
    {
        return $this->createDirectContribution(
            $verification->contribution_type,
            $verification->target_type,
            $verification->target_id,
            $user,
            ['name' => $verification->author_name, 'email' => $verification->email, ...$verification->payload],
            $verification->email,
        );
    }

    private function createDirectContribution(string $contributionType, string $targetType, int $targetId, User $user, array $data, string $email): RestaurantReview|Comment
    {
        if ($contributionType === 'review') {
            abort_unless($targetType === 'restaurant', 404);
            $restaurant = Restaurant::query()->whereKey($targetId)->where('status', 'published')->firstOrFail();
            if ($user->can('isRestaurantManager', $restaurant)) {
                throw new RestaurantReviewOwnershipException();
            }

            return RestaurantReview::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $user->id,
                'author_name' => trim($data['name']),
                'author_email' => Str::lower(trim($email)),
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'content' => trim(strip_tags($data['content'])),
                'status' => 'pending',
            ]);
        }

        $model = match ($targetType) {
            'article' => Article::class,
            'page' => Page::class,
            default => abort(404),
        };
        $content = $model::query()->whereKey($targetId)->where('status', 'published')->firstOrFail();

        return Comment::create([
            $targetType === 'article' ? 'article_id' : 'page_id' => $content->id,
            'user_id' => $user->id,
            'author_name' => trim($data['name']),
            'author_email' => Str::lower(trim($email)),
            'content' => trim(strip_tags($data['content'])),
            'status' => 'pending',
        ]);
    }

    private function payload(string $type, array $data): array
    {
        return $type === 'review'
            ? ['rating' => $data['rating'], 'title' => $data['title'] ?? null, 'content' => trim(strip_tags($data['content']))]
            : ['content' => trim(strip_tags($data['content']))];
    }

    private function hasProof(Request $request, User $user): bool
    {
        $expiresAt = $request->session()->get(self::SESSION_KEY.'.'.$user->id);

        return is_int($expiresAt) && $expiresAt > now()->timestamp;
    }

    private function rememberProof(Request $request, User $user): void
    {
        $proofs = collect($request->session()->get(self::SESSION_KEY, []))
            ->filter(fn (mixed $expiry): bool => is_int($expiry) && $expiry > now()->timestamp)
            ->all();
        $proofs[(string) $user->id] = now()->addHours((int) config('contributions.identity_proof_hours'))->timestamp;
        $request->session()->put(self::SESSION_KEY, $proofs);
    }

    private function destinationUrl(ContributionVerification $verification): string
    {
        return match ($verification->target_type) {
            'restaurant' => route('restaurants.show', Restaurant::query()->findOrFail($verification->target_id)->slug),
            'article', 'page' => route('editorial.show', ($verification->target_type === 'article' ? Article::query() : Page::query())->findOrFail($verification->target_id)->slug),
        };
    }
}
