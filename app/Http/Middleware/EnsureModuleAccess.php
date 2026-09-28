<?php

namespace App\Http\Middleware;

use App\Modules\Support\Modulzugriff;
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
 *  - Route gehört zu einem Menüpunkt (exakt oder als Unterseite, z. B. deckt
 *    `…orders.index` auch `…orders.store`) -> dessen Rollen entscheiden.
 *  - Route ohne eigenen Menüpunkt (technische Endpunkte) -> erreichbar,
 *    wenn der Benutzer irgendeinen Menüpunkt des Moduls sehen darf;
 *    feinere Prüfungen bleiben Sache des Moduls.
 *  - Zusätzlich muss die Zugriffsstufe (lesen/bearbeiten/verwalten) für die
 *    Anfrage reichen (siehe Zugriffsstufe::benoetigt). Die Stufe landet im
 *    Request, damit Views per `@darf('bearbeiten')` Knöpfe ausblenden können.
 *
 * Die Regeln selbst stehen in Modulzugriff – dieselben prüft `@darfRoute`.
 */
class EnsureModuleAccess
{
    public function __construct(private Modulzugriff $zugriff) {}

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

        $stufe = $this->zugriff->stufeFuer($user, $routeName);

        abort_if($stufe === null, 403);

        // Lesen, bearbeiten, verwalten: reicht die Stufe für diese Anfrage?
        $noetig = $this->zugriff->benoetigt($request->method(), $routeName);
        abort_unless(
            $stufe->reichtFuer($noetig),
            403,
            "Dafür reichen deine Rechte nicht: nötig ist „{$noetig->label()}“, du hast hier „{$stufe->label()}“.",
        );

        $request->attributes->set(Zugriffsstufe::ATTRIBUT, $stufe);

        return $next($request);
    }
}
