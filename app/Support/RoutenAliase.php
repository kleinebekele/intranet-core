<?php

namespace App\Support;

use App\Models\RouteSetting;
use Illuminate\Routing\AbstractRouteCollection;
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
        // Alle Einträge, nicht nur die mit Adresse: Eine Unterseite kann auch
        // ohne eigene Adresse einen Eintrag haben – nämlich das Häkchen, mit dem
        // sie sich vom Bereich abkoppelt.
        $eintraege = RouteSetting::alle();

        $aliase = array_filter($eintraege, fn (array $e) => filled($e['pfad']));

        // Der Normalfall: keine Adressen vergeben, kein Aufwand. Ein Häkchen
        // allein bewirkt nichts – ohne Stammpfad gibt es nichts zu erben.
        if ($aliase === []) {
            return;
        }

        $alt = $this->router->getRoutes();
        $neu = new RouteCollection();
        $weiterleitungen = [];

        // Erst rechnen, dann schreiben: Die Zuordnung muss auf den
        // URSPRÜNGLICHEN Adressen beruhen. Würden wir im selben Durchlauf schon
        // umschreiben, hinge das Ergebnis davon ab, in welcher Reihenfolge der
        // Router die Routen ausspuckt.
        $adressen = $this->neueAdressen($alt, $eintraege);

        foreach ($alt as $route) {
            $name = $route->getName();
            $bisher = $route->uri();
            $ziel = $adressen[trim($bisher, '/')] ?? null;

            if ($ziel !== null && $bisher !== $ziel) {
                if ($name) {
                    self::$urspruenglich[$name] = $bisher;
                }

                $route->setUri($ziel);
                $weiterleitungen[$bisher] = $ziel;
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
     * Fertige Adresse des Bereichs, unter dem eine Seite liegt – sonst null.
     *
     * Die Verwaltung braucht das an zwei Stellen: um vor dem Eingabefeld den
     * Vorsatz anzuzeigen (`ekkon/`) und um beim Speichern zu prüfen, ob die
     * ZUSAMMENGESETZTE Adresse mit einer anderen Seite kollidiert.
     */
    public function stammPfadFuer(string $routeName): ?string
    {
        $ziel = null;
        $kandidaten = [];

        foreach ($this->router->getRoutes() as $route) {
            $name = $route->getName();
            $bisher = trim(($name ? self::urspruenglicherPfad($name) : null) ?? $route->uri(), '/');

            if ($name === $routeName) {
                $ziel = $bisher;

                continue;
            }

            if (! str_contains($bisher, '{')) {
                $kandidaten[$bisher] = trim($route->uri(), '/');
            }
        }

        if ($ziel === null) {
            return null;
        }

        return $this->stammFuer($ziel, $kandidaten)[1];
    }

    /**
     * Alte Adresse → neue Adresse, für jede Route.
     *
     * Der Eintrag einer Unterseite ist RELATIV zu ihrem Bereich: Steht bei
     * `module.ekkon.index` die Adresse `ekkon` und bei `…notifications` das Wort
     * `benachrichtigungen`, ergibt das `/ekkon/benachrichtigungen`. Wird der
     * Bereich später auf `ekkon3` geändert, wandert die Unterseite mit, ohne
     * dass jemand sie anfassen muss. Wer stattdessen eine Adresse an fester
     * Stelle will, hakt „absoluter Pfad" an.
     *
     * Deshalb müssen Bereiche VOR ihren Unterseiten berechnet werden – ein
     * Kind braucht das fertige Ergebnis seines Elternteils. Die Sortierung nach
     * Länge der ursprünglichen Adresse stellt das sicher: Ein Bereich ist immer
     * kürzer als alles, was unter ihm liegt.
     *
     * @param  array<string, array{pfad:?string, absoluter_pfad:bool}>  $eintraege
     * @return array<string, string>  alte Adresse → neue Adresse
     */
    private function neueAdressen(AbstractRouteCollection $routen, array $eintraege): array
    {
        $offen = [];

        foreach ($routen as $route) {
            $offen[] = [trim($route->uri(), '/'), $route->getName()];
        }

        usort($offen, fn ($a, $b) => strlen($a[0]) <=> strlen($b[0]));

        $ergebnis = [];

        // Nur was hier landet, kann Stamm für andere sein: die bereits fertig
        // berechneten Adressen ohne Platzhalter. `/kategorien/{id}` ist kein
        // Bereich, sondern eine einzelne Seite.
        $staemme = [];

        foreach ($offen as [$bisher, $name]) {
            $eigener = $name ? ($eintraege[$name]['pfad'] ?? null) : null;
            $absolut = $name ? (bool) ($eintraege[$name]['absoluter_pfad'] ?? false) : false;

            [$stammAlt, $stammNeu] = $this->stammFuer($bisher, $staemme);

            $ziel = match (true) {
                // Eigener Eintrag: an den Bereich gehängt – oder, wenn
                // ausdrücklich absolut gewünscht, für sich stehend.
                filled($eigener) => $stammNeu !== null && ! $absolut
                    ? $stammNeu.'/'.trim($eigener, '/')
                    : trim($eigener, '/'),

                // Kein eigener Eintrag: dem Bereich folgen, sofern es einen gibt
                // und die Seite nicht ausdrücklich an fester Stelle steht.
                $stammNeu !== null && ! $absolut => $stammNeu.'/'.substr($bisher, strlen($stammAlt) + 1),

                default => $bisher,
            };

            // Mehrere Routen teilen sich dieselbe Adresse – `GET /admin/roles`
            // zeigt die Übersicht, `POST /admin/roles` legt an. Sie müssen
            // gemeinsam wandern, sonst zeigt das Formular ins Leere. Nur die
            // GET-Route trägt aber einen Namen und damit den Eintrag: Eine
            // unveränderte Route darf eine bereits umbenannte deshalb NICHT
            // überschreiben, sonst entscheidet die Reihenfolge des Routers.
            if ($ziel !== $bisher || ! isset($ergebnis[$bisher])) {
                $ergebnis[$bisher] = $ziel;
            }

            if (! str_contains($bisher, '{') && ($ziel !== $bisher || ! isset($staemme[$bisher]))) {
                $staemme[$bisher] = $ziel;
            }
        }

        return $ergebnis;
    }

    /**
     * Nächstgelegener Bereich einer Adresse: [alte Adresse, neue Adresse].
     *
     * Der Schrägstrich im Vergleich ist wichtig: `kategorien-import` liegt
     * NICHT unter `kategorien`, auch wenn die Zeichenkette so anfängt.
     *
     * @param  array<string, string>  $staemme
     * @return array{0: ?string, 1: ?string}
     */
    private function stammFuer(string $uri, array $staemme): array
    {
        $treffer = [null, null];

        foreach ($staemme as $alt => $neu) {
            // Der längste Treffer gewinnt – bei verschachtelten Bereichen soll
            // der nächstgelegene zählen, nicht der oberste.
            if (str_starts_with($uri, $alt.'/')
                && ($treffer[0] === null || strlen($alt) > strlen($treffer[0]))) {
                $treffer = [$alt, $neu];
            }
        }

        return $treffer;
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

            // `route:cache` vergibt unbenannten Routen selbst einen Namen der
            // Form `generated::xNq3…`. Das sind keine Seiten, die jemand
            // verlinkt – und auf einem Server mit Routen-Cache stünden sonst
            // dutzende davon in der Verwaltung.
            if (str_starts_with($name, 'generated::')) {
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
