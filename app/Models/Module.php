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

    /**
     * Rollen-Zuordnung auf Modul-Ebene (veraltet): Sichtbarkeit wird seit der
     * Default-Deny-Umstellung allein über die Unterpunkte gesteuert. Die
     * Beziehung bleibt nur erhalten, damit Altdaten nicht verwaisen.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'module_role', 'module_id', 'role_id', 'id', 'role_id');
    }

    /**
     * Darf der Benutzer dieses Modul sehen (Navigation UND Zugriff)?
     *  - Admins sehen immer alles.
     *  - `admins_only` -> nur Admins (harte Sperre, Rollen egal).
     *  - Sonst: sichtbar, wenn der Benutzer mindestens EINEN Unterpunkt
     *    sehen darf (die Rollen hängen an den Unterpunkten).
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user?->is_admin) {
            return true;
        }
        if ($this->admins_only) {
            return false;
        }

        return $this->menuItems->contains(
            fn (ModuleMenuItem $item) => $item->isVisibleTo($user),
        );
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
