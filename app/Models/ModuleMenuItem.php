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
    protected $fillable = ['module_id', 'key', 'label', 'route_name', 'icon', 'position'];

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

    /**
     * Wie gut passt dieser Menüpunkt auf die gerade aufgerufene Route?
     * 0 = kein Treffer; ein höherer Wert bedeutet „spezifischer".
     *
     * Ein „Sammel"-Punkt (…{resource}.index) bleibt auch auf seinen
     * Unterseiten markiert (…{resource}.show / .edit / …), damit der Nutzer
     * beim Öffnen z. B. einer einzelnen Saison weiterhin sieht, wo er ist.
     * Der Modul-Start (module.{key}.index) ist davon ausgenommen – sonst
     * würde er auf jeder Modulseite leuchten. Bei konkurrierenden Treffern
     * gewinnt der spezifischste (siehe Module::activeMenuItem), damit etwa
     * auf der OGS-Seite nicht zusätzlich „Ausgabe" markiert wird.
     */
    public function activeScore(): int
    {
        $current = request()->route()?->getName();

        if (! $current) {
            return 0;
        }

        if ($current === $this->route_name) {
            return PHP_INT_MAX;
        }

        if (str_ends_with($this->route_name, '.index')) {
            $base = substr($this->route_name, 0, -strlen('.index'));

            // Nur echte Ressourcen (module.{key}.{resource}) auf ihre Unterseiten
            // erweitern – nicht den Modul-Start (module.{key}).
            if (substr_count($base, '.') >= 2 && str_starts_with($current, $base.'.')) {
                return strlen($base);
            }
        }

        return 0;
    }
}
