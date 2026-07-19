<?php

namespace App\Support;

use App\Models\RouteSetting;
use App\Models\Setting;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Baut den Browser-Titel nach der Konvention:
 *
 *     {Haupttitel} – {Modul} – {Seite}
 *
 * Module müssen dafür nichts tun: Das Modul ergibt sich aus dem Routen-Namen,
 * die Seite aus dem passenden Menüpunkt. Wo kein Menüpunkt existiert (z. B.
 * Unterseiten wie „bearbeiten"), kann die View den Teil selbst setzen:
 *
 *     <x-slot name="titel">Schüler bearbeiten</x-slot>
 */
class Seitentitel
{
    private const TRENNER = ' – ';

    /**
     * @param  string|null  $seite  Ausdrücklicher Seitenname der View; überschreibt
     *                              den aus dem Menü abgeleiteten.
     */
    public static function bauen(?string $seite = null): string
    {
        $routeName = Route::currentRouteName();

        // Ein in der Verwaltung vergebener Titel ersetzt die ganze Konvention –
        // sonst waere er wirkungslos, sobald jemand einen wollte, der anders
        // aufgebaut ist als {Haupttitel} - {Modul} - {Seite}.
        if (filled($fest = RouteSetting::titel($routeName))) {
            return $fest;
        }

        $teile = [
            self::haupttitel(),
            self::modul($routeName),
            $seite ?: self::seite($routeName),
        ];

        // Leere Teile fallen weg – so entsteht auf dem Dashboard kein
        // „Intranet –  – ", sondern schlicht „Intranet".
        $teile = array_values(array_filter($teile, fn ($t) => filled($t)));

        // Heißt die Seite wie das Modul (typisch fuer die Startseite eines
        // Moduls), waere die Wiederholung nur Laerm.
        if (count($teile) === 3 && $teile[1] === $teile[2]) {
            array_pop($teile);
        }

        return implode(self::TRENNER, $teile);
    }

    public static function haupttitel(): string
    {
        return Setting::get('haupttitel', config('app.name', 'Intranet'));
    }

    /** Anzeigename des Moduls, in dem die aktuelle Route liegt. */
    private static function modul(?string $routeName): ?string
    {
        $registry = app(ModuleRegistry::class);
        $key = $registry->currentKey($routeName);

        return $key ? $registry->manifest($key)?->name : null;
    }

    /**
     * Der Menüpunkt, der zur aktuellen Route gehört.
     *
     * Gesucht wird der längste passende Routen-Name: Bei
     * `module.schulzeugnis.schueler.edit` gewinnt der Menüpunkt
     * `module.schulzeugnis.schueler` gegen `module.schulzeugnis`.
     */
    private static function seite(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $treffer = null;
        $laenge = -1;

        foreach (self::menuepunkte() as $punkt) {
            $passt = $routeName === $punkt['route_name']
                || str_starts_with($routeName, $punkt['route_name'].'.');

            if ($passt && strlen($punkt['route_name']) > $laenge) {
                $treffer = $punkt['label'];
                $laenge = strlen($punkt['route_name']);
            }
        }

        return $treffer;
    }

    /**
     * Bewusst ohne Cache: Die Tabelle ist winzig, und ein dauerhafter Cache
     * würde nach jedem `modules:sync` veraltete Seitennamen anzeigen, ohne dass
     * jemand die Ursache fände. Pro Seitenaufruf eine kleine Abfrage.
     *
     * @return array<int, array{route_name:string, label:string}>
     */
    private static function menuepunkte(): array
    {
        if (! Schema::hasTable('module_menu_items')) {
            return [];
        }

        return DB::table('module_menu_items')
            ->select('route_name', 'label')
            ->get()
            ->map(fn ($z) => (array) $z)
            ->all();
    }
}
