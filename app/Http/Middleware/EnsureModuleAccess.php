<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Modules\Support\ModuleRegistry;
use App\Modules\Support\Zugriffsstufe;
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
 *  - Zusätzlich muss die Zugriffsstufe (lesen/bearbeiten/verwalten) für die
 *    Anfrage reichen (siehe Zugriffsstufe::benoetigt). Die Stufe landet im
 *    Request, damit Views per `@darf('bearbeiten')` Knöpfe ausblenden können.
 */
class EnsureModuleAccess
{
    public function __construct(private ModuleRegistry $registry) {}

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
            $request->attributes->set(Zugriffsstufe::ATTRIBUT, Zugriffsstufe::Verwalten);

            return $next($request);
        }

        $key = explode('.', $routeName)[1] ?? '';
        $module = Module::query()->with('menuItems.roles')->where('key', $key)->first();

        if ($module === null || ! $module->is_enabled || $module->admins_only) {
            abort(403);
        }

        $item = $this->responsibleItem($module, $routeName);
        $stufe = $item !== null ? $item->stufeFuer($user) : $module->stufeFuer($user);

        abort_if($stufe === null, 403);

        // Lesen, bearbeiten, verwalten: reicht die Stufe für diese Anfrage?
        $noetig = Zugriffsstufe::benoetigt($request->method(), $routeName, $this->registry->manifest($key));
        abort_unless(
            $stufe->reichtFuer($noetig),
            403,
            "Dafür reichen deine Rechte nicht: nötig ist „{$noetig->label()}“, du hast hier „{$stufe->label()}“.",
        );

        $request->attributes->set(Zugriffsstufe::ATTRIBUT, $stufe);

        return $next($request);
    }

    /**
     * Der Menüpunkt, der für diese Route zuständig ist: exakt, sonst der
     * spezifischste, dessen Route ein Präfix ist – `…orders.index` wie auch
     * `…auftragsimport` decken `…auftragsimport.upload` ab.
     */
    private function responsibleItem(Module $module, string $routeName): ?ModuleMenuItem
    {
        foreach ($module->menuItems as $item) {
            if ($item->route_name === $routeName) {
                return $item;
            }
        }

        $treffer = null;
        $laenge = 0;

        foreach ($module->menuItems as $item) {
            $base = str_ends_with($item->route_name, '.index')
                ? substr($item->route_name, 0, -strlen('.index'))
                : $item->route_name;

            // Nur echte Unterbereiche (module.{key}.{bereich}) decken ihre
            // Unterseiten ab – nicht der Modul-Start (module.{key}[.index]).
            if (substr_count($base, '.') >= 2 && str_starts_with($routeName, $base.'.') && strlen($base) > $laenge) {
                $treffer = $item;
                $laenge = strlen($base);
            }
        }

        return $treffer;
    }
}
