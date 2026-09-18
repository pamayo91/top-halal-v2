<?php

namespace App\Services;

use App\Models\AccountEmailChange;
use App\Models\User;
use App\Policies\RestaurantPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountEmailChangeService
{
    /** @return array{change: AccountEmailChange, token: string} */
    public function initiate(User $user, string $newEmail): array
    {
        $email = Str::lower(trim($newEmail));

        return DB::transaction(function () use ($user, $email): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (Str::lower($user->email) === $email) {
                throw ValidationException::withMessages(['email' => 'Saisissez une adresse e-mail différente de l’adresse actuelle.']);
            }

            if ($this->emailBelongsToAnotherUser($email, $user->id)) {
                throw ValidationException::withMessages(['email' => 'Cette adresse e-mail est déjà utilisée.']);
            }

            AccountEmailChange::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('invalidated_at')->update(['invalidated_at' => now()]);

            $token = Str::random(64);
            $change = AccountEmailChange::create([
                'user_id' => $user->id,
                'new_email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(24),
            ]);

            return compact('change', 'token');
        });
    }

    /** @return array{old_email: string, new_email: string}|null */
    public function confirm(AccountEmailChange $requestedChange, string $token): ?array
    {
        return DB::transaction(function () use ($requestedChange, $token): ?array {
            $change = AccountEmailChange::query()->lockForUpdate()->find($requestedChange->id);

            if (! $change || $change->used_at !== null || $change->invalidated_at !== null || ! $change->expires_at->isFuture() || ! hash_equals($change->token_hash, hash('sha256', $token))) {
                return null;
            }

            $user = User::query()->lockForUpdate()->find($change->user_id);

            if (! $user || $this->emailBelongsToAnotherUser($change->new_email, $user->id)) {
                $change->forceFill(['invalidated_at' => now()])->save();

                return null;
            }

            $oldEmail = $user->email;
            $user->forceFill(['email' => $change->new_email, 'email_verified_at' => now()])->save();
            $change->forceFill(['used_at' => now()])->save();

            // `contact_email` is the sole active restaurant e-mail. Claim and
            // submission e-mails deliberately remain immutable audit history.
            app(RestaurantPolicy::class)->representedRestaurantsQuery($user)->update(['contact_email' => $change->new_email]);

            return ['old_email' => $oldEmail, 'new_email' => $change->new_email];
        });
    }

    private function emailBelongsToAnotherUser(string $email, int $userId): bool
    {
        return User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->where('id', '!=', $userId)->exists();
    }
}
