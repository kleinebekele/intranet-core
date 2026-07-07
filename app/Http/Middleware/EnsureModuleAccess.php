<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\ModuleMenuItem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die Sichtbarkeits-Einstellungen der Modul-Verwaltung als ZUGRIFFS-Regel
 * durch (nicht nur fürs Menü). Hängt an der globalen web-Gruppe und greift für
 * alle Routen nach der Konvention `module.{key}.*`:
 *
 *  - Admins dürfen immer alles.
 *  - Modul deaktiviert / nicht synchronisiert / `admins_only` -> 403.
 *  - Route gehört zu einem Menüpunkt (exakt oder als Unterseite einer
 *    Ressource, z. B. deckt `…orders.index` auch `…orders.store`) ->
 *    dessen Rollen entscheiden.
 *  - Route ohne eigenen Menüpunkt (technische Endpunkte) -> erreichbar,
 *    wenn der Benutzer irgendeinen Menüpunkt des Moduls sehen darf;
 *    feinere Prüfungen bleiben Sache des Moduls.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null || ! str_starts_with($routeName, 'module.')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request); // Gäste behandelt die auth-Middleware der Route
        }

        if ($user->is_admin) {
            return $next($request);
        }

        $key = explode('.', $routeName)[1] ?? '';
        $module = Module::query()->with('menuItems.roles')->where('key', $key)->first();

        if ($module === null || ! $module->is_enabled || $module->admins_only) {
            abort(403);
        }

        $item = $this->responsibleItem($module, $routeName);

        if ($item !== null) {
            abort_unless($item->isVisibleTo($user), 403);

            return $next($request);
        }

        abort_unless($module->isVisibleTo($user), 403);

        return $next($request);
    }

    /** Der Menüpunkt, der für diese Route zuständig ist (exakt vor Ressourcen-Präfix). */
    private function responsibleItem(Module $module, string $routeName): ?ModuleMenuItem
    {
        foreach ($module->menuItems as $item) {
            if ($item->route_name === $routeName) {
                return $item;
            }
        }

        foreach ($module->menuItems as $item) {
            if (! str_ends_with($item->route_name, '.index')) {
                continue;
            }

            $base = substr($item->route_name, 0, -strlen('.index'));

            // Nur echte Ressourcen (module.{key}.{resource}) decken ihre
            // Unterseiten ab – nicht der Modul-Start (module.{key}.index).
            if (substr_count($base, '.') >= 2 && str_starts_with($routeName, $base.'.')) {
                return $item;
            }
        }

        return null;
    }
}
