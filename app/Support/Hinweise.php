<?php

namespace App\Support;

use App\Models\User;
use Throwable;

/**
 * Vermittlungsstelle für die Glocke in der Kopfzeile.
 *
 * Der Core weiß nichts über Aufgaben, Warteschlangen oder Zustellfehler. Er
 * stellt nur eine Frage – „gibt es gerade etwas, das jemand sehen muss?" –
 * und jedes Modul darf sie beantworten. Die Antwort ist eine Liste von
 * {@see Hinweis}-Objekten; ihre Anzahl steht im roten Punkt an der Glocke.
 *
 * Anmeldung im `boot()` des Moduls:
 *
 *   Hinweise::anbieten(function (User $user): iterable {
 *       if (! $user->isAdmin()) {
 *           return [];
 *       }
 *       $n = Notification::query()->where('status', 'failed')->count();
 *
 *       return $n > 0
 *           ? [new Hinweis("$n Benachrichtigungen nicht zugestellt", route('…'), 'Ekkon', $n)]
 *           : [];
 *   });
 *
 * Der Anbieter läuft bei JEDEM Seitenaufruf – er soll eine Zählabfrage
 * stellen, nicht rechnen. Wirft er (Tabelle fehlt noch, Verbindung weg),
 * fällt nur sein Beitrag aus, nie die Seite.
 */
class Hinweise
{
    /** @var array<int, callable(User): iterable<Hinweis>> */
    private static array $anbieter = [];

    /**
     * Einen Anbieter anmelden. Er bekommt den angemeldeten Benutzer und
     * liefert die für ihn relevanten offenen Hinweise – oder eine leere Liste.
     */
    public static function anbieten(callable $anbieter): void
    {
        static::$anbieter[] = $anbieter;
    }

    /**
     * Alle offenen Hinweise für den Benutzer (Standard: der angemeldete).
     *
     * @return array<int, Hinweis>
     */
    public static function alle(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null || static::$anbieter === []) {
            return [];
        }

        $hinweise = [];

        foreach (static::$anbieter as $anbieter) {
            try {
                foreach ($anbieter($user) as $hinweis) {
                    if ($hinweis instanceof Hinweis) {
                        $hinweise[] = $hinweis;
                    }
                }
            } catch (Throwable) {
                // Ein kaputter Anbieter darf die Kopfzeile nicht mitreißen.
            }
        }

        return $hinweise;
    }

    /**
     * Summe der Einzelfälle – die Zahl im roten Punkt.
     *
     * @param  array<int, Hinweis>  $hinweise
     */
    public static function summe(array $hinweise): int
    {
        return array_sum(array_map(fn (Hinweis $h): int => max(1, $h->anzahl), $hinweise));
    }

    /** Nur für Tests: alle Anbieter vergessen. */
    public static function vergessen(): void
    {
        static::$anbieter = [];
    }
}
