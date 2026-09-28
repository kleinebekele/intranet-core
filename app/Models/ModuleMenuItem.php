<?php

namespace App\Models;

use App\Modules\Support\Zugriffsstufe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Route;

/**
 * One sub-page of a module in the left menu. Order is owned by the admin.
 */
class ModuleMenuItem extends Model
{
    protected $fillable = ['module_id', 'key', 'label', 'route_name', 'icon', 'group_label', 'position'];

    protected $casts = [
        'position' => 'integer',
        'admins_only' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Rollen, die diesen Unterpunkt sehen dürfen (keine = nur Administratoren),
     * je mit ihrer Zugriffsstufe (`pivot->stufe`, siehe Zugriffsstufe).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'module_menu_item_role', 'module_menu_item_id', 'role_id', 'id', 'role_id')
            ->withPivot('stufe');
    }

    /**
     * Wie weit darf der Benutzer auf diesem Unterpunkt gehen? null = gar nicht.
     *  - Admins: verwalten.
     *  - `admins_only` oder ohne zugewiesene Rollen -> nur Admins (sicherer
     *    Standard; "für alle" drückt man explizit über die Basis-Rolle `user` aus).
     *  - Sonst die höchste Stufe unter den Rollen des Benutzers.
     */
    public function stufeFuer(?User $user): ?Zugriffsstufe
    {
        if ($user?->is_admin) {
            return Zugriffsstufe::Verwalten;
        }
        if ($this->admins_only || $this->roles->isEmpty() || ! $user) {
            return null;
        }

        // Rollen eines deaktivierten Moduls zählen nicht – auch nicht hier,
        // falls sie einem fremden Menüpunkt zugeordnet sind.
        $eigene = $user->roles->pluck('role_id')->diff(Role::inaktiveSchluessel());

        return Zugriffsstufe::hoechste(
            $this->roles->whereIn('role_id', $eigene)->map(fn (Role $role) => $role->pivot->stufe),
        );
    }

    /** Darf der Benutzer diesen Unterpunkt sehen (Navigation UND Zugriff)? */
    public function isVisibleTo(?User $user): bool
    {
        return $this->stufeFuer($user) !== null;
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
