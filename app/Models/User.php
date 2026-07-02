<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * Alle Rollen dieses Benutzers (n:n über die Tabelle user_roles).
     *
     * Die Pivot-Schlüssel sind explizit angegeben, weil Rollen mit dem
     * sprechenden String-Schlüssel `role_id` (statt einer Integer-ID) arbeiten.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id', 'id', 'role_id');
    }
}
