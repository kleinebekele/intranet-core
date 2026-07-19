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
#[Hidden(['password', 'remember_token', 'totp_secret'])]
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
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'gesperrt_am' => 'datetime',
        ];
    }

    /**
     * Gesperrte Benutzer kommen nicht mehr an ihr Konto: Die Anmeldung wird
     * abgelehnt (siehe LoginRequest), das Konto bleibt aber mitsamt seiner
     * Geschichte bestehen.
     */
    public function istGesperrt(): bool
    {
        return $this->gesperrt_am !== null;
    }

    public function sperren(?string $grund = null): void
    {
        if ($this->istGesperrt()) {
            return; // Sperrzeitpunkt und -grund nicht überschreiben
        }

        $this->forceFill(['gesperrt_am' => now(), 'gesperrt_grund' => $grund])->save();
    }

    public function entsperren(): void
    {
        $this->forceFill(['gesperrt_am' => null, 'gesperrt_grund' => null])->save();
    }

    /**
     * Hat dieser Benutzer TOTP (Authenticator-App) fertig eingerichtet?
     * Nur dann ersetzt TOTP den Standard-Mail-Code bei der 2FA-Anmeldung.
     */
    public function hasTotp(): bool
    {
        return $this->totp_secret !== null && $this->totp_confirmed_at !== null;
    }

    /**
     * Braucht dieser Benutzer beim Login einen zweiten Faktor?
     * Eigene Entscheidung (Profil) — oder FORCE_2FA erzwingt es für alle.
     */
    public function needsTwoFactor(): bool
    {
        return $this->two_factor_enabled || config('intranet.two_factor_forced');
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

        static::created(function (User $user): void {
            // Grundregel: jeder Benutzer erhält automatisch die Rolle "user"
            // (gilt auch für importierte Benutzer, da diese über dieses Model
            // angelegt werden). Guard, falls die Rolle noch nicht existiert.
            if (Role::whereKey('user')->exists()) {
                $user->roles()->syncWithoutDetaching(['user']);
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

    /**
     * Die Eltern / Vormunde dieses Benutzers (n:m über users_parents).
     * Dieser Benutzer ist das Kind (user_id), die Beziehung zeigt auf parent_id.
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_parents', 'user_id', 'parent_id');
    }

    /**
     * Die Kinder / Mündel dieses Benutzers (Gegenrichtung von parents()).
     * Dieser Benutzer ist das Elternteil (parent_id).
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_parents', 'parent_id', 'user_id');
    }
}
