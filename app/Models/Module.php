<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * A registered module. Rows are created/updated by `php artisan modules:sync`.
 * `position` and `is_enabled` are owned by the admin and never overwritten
 * by a re-sync.
 */
class Module extends Model
{
    protected $fillable = ['key', 'name', 'icon', 'position', 'is_enabled'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'admins_only' => 'boolean',
        'position' => 'integer',
    ];

    public function menuItems(): HasMany
    {
        return $this->hasMany(ModuleMenuItem::class)->orderBy('position');
    }

    /** Rollen, die dieses Modul in der Navigation sehen dürfen (leer = alle). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'module_role', 'module_id', 'role_id', 'id', 'role_id');
    }

    /**
     * Darf der Benutzer dieses Modul sehen?
     *  - Admins sehen immer alles.
     *  - `admins_only` -> nur Admins.
     *  - Ohne zugewiesene Rollen ist das Modul für alle sichtbar.
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

    /**
     * Der aktuell aktive Menüpunkt: der spezifischste Treffer auf die laufende
     * Route (siehe ModuleMenuItem::activeScore). Arbeitet auf der bereits
     * geladenen menuItems-Beziehung, damit die Sidebar keine Extra-Abfragen
     * auslöst. null, wenn kein Punkt passt.
     */
    public function activeMenuItem(): ?ModuleMenuItem
    {
        $best = null;
        $bestScore = 0;

        foreach ($this->menuItems as $item) {
            $score = $item->activeScore();
            if ($score > $bestScore) {
                $best = $item;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Where clicking the module in the main sidebar should take you:
     * its first sub-page (the module's landing page).
     */
    public function homeUrl(): ?string
    {
        $first = $this->menuItems->first();

        return $first && Route::has($first->route_name)
            ? route($first->route_name)
            : null;
    }
}
