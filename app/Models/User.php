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
use Illuminate\Support\Str;
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

    /** Confirm the e-mail without making a contribution identity connectable early. */
    public function prepareForRestaurantSubmissionActivation(): void
    {
        if (! $this->needsRestaurantSubmissionActivation()) {
            return;
        }

        $changes = [];

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
    /**
     * Personal identity that is safe to suggest as the public author of a
     * review. Restaurant-account `name` values have historically sometimes
     * held a commercial name, so restaurant-related identities use the
     * explicit human fields captured by their claim/submission instead.
     */
    public function reviewAuthorName(): ?string
    {
        $claimName = $this->claims()
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            ->latest('submitted_at')
            ->value('full_name');
        if (filled($claimName)) return trim($claimName);

        $submissionName = $this->submittedRestaurants()
            ->where('submitter_role', 'owner')
            ->whereNotNull('owner_full_name')
            ->where('owner_full_name', '!=', '')
            ->latest('submitted_at')
            ->value('owner_full_name');
        if (filled($submissionName)) return trim($submissionName);

        // A plain account has no business relation from which its name could
        // have been inherited, so its chosen account name remains suitable.
        if (! $this->claims()->exists()
            && ! $this->submittedRestaurants()->exists()
            && ! $this->legacyRestaurantAuthorships()->exists()) {
            return filled($this->name) ? trim($this->name) : null;
        }

        return null;
    }

    /**
     * A historical non-manager submission used the restaurant name as a
     * required User.name fallback. That value is not personal identity.
     */
    public function reliablePersonalName(): ?string
    {
        $name = trim((string) $this->name);

        if ($name === '') return null;

        $normalizedName = $this->normalizePersonalName($name);
        $isRestaurantFallback = $this->submittedRestaurants()
            ->with('restaurant:id,name')
            ->get()
            ->contains(fn (RestaurantSubmission $submission): bool => $submission->restaurant !== null
                && $normalizedName === $this->normalizePersonalName($submission->restaurant->name));

        return $isRestaurantFallback ? null : $name;
    }

    private function normalizePersonalName(string $value): string
    {
        return Str::lower(preg_replace('/\\s+/', ' ', trim(Str::ascii($value))) ?? '');
    }
    public function ownerSubmissions() { return $this->hasMany(RestaurantSubmission::class)->where('submitter_role', 'owner'); }
    /** Submissions still manageable because no real owner has an approved claim. */
    public function manageableSubmittedRestaurants()
    {
        return $this->hasMany(RestaurantSubmission::class)
            ->where('status', '!=', 'rejected')
            ->whereDoesntHave('restaurant.claims', fn ($query) => $query->where('status', 'approved'));
    }
    public function legacyRestaurantAuthorships() { return $this->hasMany(LegacyRestaurantAuthorship::class); }
    public function legacyAuthoredRestaurants() { return $this->belongsToMany(Restaurant::class, 'legacy_restaurant_authorships', 'user_id', 'restaurant_id'); }
    public function sendEmailVerificationNotification(): void { $this->notify(new VerifyEmailNotification()); }
    public function sendPasswordResetNotification($token): void { if ($this->canLogIn()) $this->notify(new QueuedResetPasswordNotification($token)); }
    public function canAccessPanel(Panel $panel): bool { return $panel->getId() === 'admin' && $this->role === 'admin' && $this->status === 'active'; }
}
