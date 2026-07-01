<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The very first registered user automatically becomes an administrator,
     * so a fresh installation always has someone who can reach the admin panel.
     *
     * Note: `is_admin` is intentionally NOT mass-assignable (not in the
     * Fillable attribute) so it can never be set through the registration form.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (static::count() === 0) {
                $user->is_admin = true;
            }
        });
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
