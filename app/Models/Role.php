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

    protected $fillable = ['role_id', 'name'];

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
     * Alle Benutzer, die diese Rolle besitzen (n:n über user_roles).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id', 'role_id', 'id');
    }
}
