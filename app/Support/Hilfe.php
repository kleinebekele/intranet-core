<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Vermittlungsstelle für die Kontexthilfe.
 *
 * Der Core weiß nichts über ein Wiki. Er stellt nur eine Frage – „gibt es zu
 * dieser Seite eine Hilfe?" – und ein Modul darf sie beantworten. Ist kein
 * Anbieter angemeldet (Wiki nicht installiert), liefert alles `null` und der
 * „?"-Knopf erscheint gar nicht erst.
 *
 * Anmeldung im `boot()` des anbietenden Moduls:
 *
 *   Hilfe::anbieten(fn (string $route, ?User $user) => WikiSeite::urlFuerRoute($route, $user));
 */
class Hilfe
{
    /** @var array<int, callable(string, ?User): ?string> */
    private static array $anbieter = [];

    /**
     * Einen Anbieter anmelden. Er bekommt den Namen der aktuellen Route und
     * den angemeldeten Benutzer und liefert eine URL – oder `null`, wenn er
     * zu dieser Seite nichts beizutragen hat.
     */
    public static function anbieten(callable $aufloeser): void
    {
        static::$anbieter[] = $aufloeser;
    }

    /** Gibt es überhaupt einen Anbieter? (Spart die Auflösung im Layout.) */
    public static function verfuegbar(): bool
    {
        return static::$anbieter !== [];
    }

    /**
     * Die Hilfe-Adresse zur angegebenen (Standard: aktuellen) Route.
     * Der erste Anbieter, der etwas liefert, gewinnt.
     */
    public static function url(?string $routeName = null, ?User $user = null): ?string
    {
        $routeName ??= Route::currentRouteName();

        if ($routeName === null || static::$anbieter === []) {
            return null;
        }

        $user ??= auth()->user();

        foreach (static::$anbieter as $aufloeser) {
            if ($url = $aufloeser($routeName, $user)) {
                return $url;
            }
        }

        return null;
    }

    /** Nur für Tests: alle Anbieter vergessen. */
    public static function vergessen(): void
    {
        static::$anbieter = [];
    }
}
