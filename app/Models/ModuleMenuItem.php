<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Route;

/**
 * One sub-page of a module in the left menu. Order is owned by the admin.
 */
class ModuleMenuItem extends Model
{
    protected $fillable = ['module_id', 'key', 'label', 'route_name', 'position'];

    protected $casts = [
        'position' => 'integer',
        'admins_only' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Rollen, die diesen Unterpunkt sehen dürfen (leer = alle). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'module_menu_item_role', 'module_menu_item_id', 'role_id', 'id', 'role_id');
    }

    /**
     * Darf der Benutzer diesen Unterpunkt sehen?
     *  - Admins sehen immer alles.
     *  - `admins_only` -> nur Admins.
     *  - Ohne zugewiesene Rollen ist der Punkt für alle sichtbar.
     *  - Sonst genügt eine übereinstimmende Rolle.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user?->is_admin) {
            return true;
        }
        if ($this->admins_only) {
            return false;
        }
        if ($this->roles->isEmpty()) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $user->roles->pluck('role_id')
            ->intersect($this->roles->pluck('role_id'))
            ->isNotEmpty();
    }

    public function url(): ?string
    {
        return Route::has($this->route_name) ? route($this->route_name) : null;
    }

    public function isActive(): bool
    {
        return request()->routeIs($this->route_name);
    }
}
