<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * Der Primärschlüssel ist ein sprechender String (z. B. 'teacher'),
     * kein automatisch hochzählendes Integer.
     */
    protected $primaryKey = 'role_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * `quelle` ist mass-assignable, weil Abgleiche (Module) ihre Rollen per
     * firstOrCreate/updateOrCreate anlegen; im Panel wird sie nie gesetzt.
     */
    protected $fillable = ['role_id', 'name', 'quelle'];

    /**
     * `is_system` ist bewusst NICHT mass-assignable – System-Rollen werden
     * nur per Migration gesetzt und können im Panel nicht verändert werden.
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** System-Rollen (z. B. admin, user) sind fest und dürfen nicht gelöscht werden. */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /**
     * Wird die Rolle von einem Abgleich (Modul) gepflegt? Dann ist die
     * Mitgliederpflege im Panel gesperrt – der nächste Lauf würde sie zurückdrehen.
     */
    public function istVerwaltet(): bool
    {
        return $this->quelle !== null && $this->quelle !== '';
    }

    /**
     * Alle Benutzer, die diese Rolle besitzen (n:n über user_roles).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id', 'role_id', 'id');
    }
}
