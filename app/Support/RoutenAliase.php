<?php

namespace App\Support;

use App\Models\RouteSetting;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;

/**
 * Ersetzt Routen-Adressen durch die in der Verwaltung vergebenen (Reiter SEO).
 *
 * Der Kniff: Wir ändern die Adresse der Route SELBST, statt Laravels
 * URL-Erzeugung zu unterwandern. Dadurch greift der Ersatz an beiden Enden
 * zugleich – beim Auflösen einer Anfrage und bei jedem `route()`-Aufruf. Alle
 * Menüpunkte und Modul-Verweise zeigen ohne eine Zeile Änderung auf die neue
 * Adresse.
 *
 * Die alte Adresse bleibt als Weiterleitung bestehen: Ein bereits verschickter
 * Link oder ein Lesezeichen soll nicht ins Leere laufen, nur weil jemand in der
 * Verwaltung einen schöneren Pfad vergeben hat.
 *
 * Läuft im boot() des AppServiceProvider – also NACH dem Registrieren aller
 * Modul-Routen und VOR dem Abgleich der Anfrage. Das gilt auch bei
 * zwischengespeicherten Routen (`route:cache`), weil wir die bereits geladene
 * Sammlung im Speicher umbauen.
 */
class RoutenAliase
{
    /**
     * Ursprüngliche Adressen, bevor wir sie ersetzt haben – nach Routen-Name.
     *
     * Ohne diese Merkliste könnte die Verwaltung nicht mehr anzeigen, wie eine
     * Seite eigentlich heißt: `$route->uri()` liefert nach dem Umschreiben ja
     * die neue Adresse.
     *
     * @var array<string, string>
     */
    private static array $urspruenglich = [];

    public function __construct(private readonly Router $router)
    {
    }

    /** Ursprüngliche Adresse einer Route, falls sie ersetzt wurde. */
    public static function urspruenglicherPfad(string $routeName): ?string
    {
        return self::$urspruenglich[$routeName] ?? null;
    }

    public function anwenden(): void
    {
        $aliase = array_filter(
            RouteSetting::alle(),
            fn (array $e) => filled($e['pfad'])
        );

        // Der Normalfall: keine Aliase vergeben, kein Aufwand.
        if ($aliase === []) {
            return;
        }

        $alt = $this->router->getRoutes();
        $neu = new RouteCollection();
        $weiterleitungen = [];

        foreach ($alt as $route) {
            $name = $route->getName();
            $pfad = $name ? ($aliase[$name]['pfad'] ?? null) : null;

            if ($pfad !== null) {
                $bisher = $route->uri();
                $ziel = trim($pfad, '/');

                if ($bisher !== $ziel) {
                    self::$urspruenglich[$name] = $bisher;
                    $route->setUri($ziel);
                    $weiterleitungen[$bisher] = $ziel;
                }
            }

            $neu->add($route);
        }

        // Die neue Sammlung ersetzt die alte – setRoutes() hängt sie auch im
        // Container neu ein, woraus der URL-Generator seine Adressen zieht.
        $this->router->setRoutes($neu);

        // Erst jetzt die Weiterleitungen: Sie landen in der bereits gesetzten
        // Sammlung. Vorher angelegt würden sie beim Ersetzen verschwinden.
        foreach ($weiterleitungen as $bisher => $ziel) {
            $this->weiterleiten($bisher, $ziel);
        }
    }

    /**
     * Alte Adresse → neue Adresse.
     *
     * Bewusst 302 statt 301: Ein Alias kann in der Verwaltung jederzeit wieder
     * geändert werden, und eine dauerhafte Weiterleitung brennt sich in Browsern
     * so fest, dass sie auch nach dem Zurücknehmen noch greift.
     */
    private function weiterleiten(string $bisher, string $ziel): void
    {
        // Adressen mit Platzhaltern lassen sich nicht sinnvoll weiterleiten –
        // die Verwaltung bietet solche Routen gar nicht erst an.
        if (str_contains($bisher, '{')) {
            return;
        }

        $this->router->redirect($bisher, '/'.$ziel, 302);
    }

    /**
     * Routen, die überhaupt eine sprechende Adresse bekommen können.
     *
     * Nur benannte GET-Seiten ohne Platzhalter: Alles andere ist entweder nicht
     * verlinkbar (POST/PUT/DELETE) oder hat keine feste Adresse (`/{id}/edit`).
     *
     * @return array<int, Route>
     */
    public function verfuegbareRouten(): array
    {
        $routen = [];

        foreach ($this->router->getRoutes() as $route) {
            $name = $route->getName();

            if (! $name
                || ! in_array('GET', $route->methods(), true)
                || str_contains($route->uri(), '{')) {
                continue;
            }

            // Von Laravel und Breeze mitgebrachte Anmelde-/Profilrouten bleiben,
            // wie sie sind: Sie sind fest verdrahtet und niemand verlinkt sie.
            if (str_starts_with($name, 'password.')
                || str_starts_with($name, 'verification.')
                || in_array($name, ['login', 'logout', 'register', 'profile.edit'], true)) {
                continue;
            }

            $routen[$name] = $route;
        }

        ksort($routen);

        return array_values($routen);
    }
}
