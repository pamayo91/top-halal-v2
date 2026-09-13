<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\QueuedResetPasswordNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

#[Fillable(['name', 'email', 'password', 'login_enabled', 'role', 'status', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'login_enabled' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function claims() { return $this->hasMany(RestaurantClaim::class); }
    public function reviews() { return $this->hasMany(RestaurantReview::class); }
    public function comments() { return $this->hasMany(Comment::class); }
    public function canLogIn(): bool { return $this->login_enabled; }

    /**
     * A restaurant submission can complete an account only when it is an
     * active account that has not yet been made usable for a normal login.
     *
     * A disabled account is an administrative state, not an invitation to
     * reactivate it from a public submission.
     */
    public function needsRestaurantSubmissionActivation(): bool
    {
        return $this->status === 'active'
            && (! $this->login_enabled || $this->must_change_password);
    }

    /** Make a verified contribution identity eligible to choose its password. */
    public function prepareForRestaurantSubmissionActivation(): void
    {
        if (! $this->needsRestaurantSubmissionActivation()) {
            return;
        }

        $changes = [];

        if (! $this->login_enabled) {
            $changes['login_enabled'] = true;
            $changes['must_change_password'] = true;
        }

        if (! $this->hasVerifiedEmail()) {
            $changes['email_verified_at'] = now();
        }

        if ($changes !== []) {
            $this->forceFill($changes)->save();
        }
    }
    /**
     * A verified restaurateur has an actual approved ownership claim.
     *
     * An exact legacy authorship relation can qualify a back-office profile as
     * "Restaurateur", but intentionally never grants ownership or the
     * abbreviated subsequent-claim workflow.
     */
    public function isVerifiedRestaurateur(): bool
    {
        return $this->status === 'active'
            && ! $this->must_change_password
            && $this->ownedRestaurants()->exists();
    }
    public function ownedRestaurants() { return $this->belongsToMany(Restaurant::class, 'restaurant_claims', 'user_id', 'restaurant_id')->wherePivot('status', 'approved'); }
    public function submittedRestaurants() { return $this->hasMany(RestaurantSubmission::class); }
    public function ownerSubmissions() { return $this->hasMany(RestaurantSubmission::class)->where('submitter_role', 'owner'); }
    /** Submissions still manageable because no real owner has an approved claim. */
    public function manageableSubmittedRestaurants()
    {
        return $this->hasMany(RestaurantSubmission::class)
            ->whereDoesntHave('restaurant.claims', fn ($query) => $query->where('status', 'approved'));
    }
    public function legacyRestaurantAuthorships() { return $this->hasMany(LegacyRestaurantAuthorship::class); }
    public function legacyAuthoredRestaurants() { return $this->belongsToMany(Restaurant::class, 'legacy_restaurant_authorships', 'user_id', 'restaurant_id'); }
    public function sendEmailVerificationNotification(): void { $this->notify(new VerifyEmailNotification()); }
    public function sendPasswordResetNotification($token): void { if ($this->canLogIn()) $this->notify(new QueuedResetPasswordNotification($token)); }
    public function canAccessPanel(Panel $panel): bool { return $panel->getId() === 'admin' && $this->role === 'admin' && $this->status === 'active'; }
}
