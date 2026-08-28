<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\QueuedResetPasswordNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'must_change_password' => 'boolean',
        ];
    }

    public function claims() { return $this->hasMany(RestaurantClaim::class); }
    public function ownedRestaurants() { return $this->belongsToMany(Restaurant::class, 'restaurant_claims', 'user_id', 'restaurant_id')->wherePivot('status', 'approved'); }
    public function sendEmailVerificationNotification(): void { $this->notify(new VerifyEmailNotification()); }
    public function sendPasswordResetNotification($token): void { $this->notify(new QueuedResetPasswordNotification($token)); }
    public function canAccessPanel(Panel $panel): bool { return $panel->getId() === 'admin' && $this->role === 'admin' && $this->status === 'active'; }
}
