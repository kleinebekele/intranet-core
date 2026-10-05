<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Zusätzliche Symbole von Modulen in der Kopfzeile, links neben der Glocke
 * (z. B. Webmail mit der Zahl ungelesener Mails).
 *
 * Ein Modul hängt sich im boot() seines Providers ein und liefert je Benutzer ein
 * fertiges Stück HTML – oder null, wenn es für diesen Benutzer nichts zu zeigen gibt:
 *
 *   Kopfleiste::registrieren('webmail', fn (User $user) => view('webmail::kopfleiste'));
 *
 * Teure Abfragen (Fremdsysteme) gehören NICHT hierher – die Kopfzeile steht auf jeder
 * Seite. Das Symbol rendert sofort und lädt seine Zahl selbst nach (fetch). Ein Fehler
 * blendet das Symbol aus und wird geloggt, die Seite bleibt heil.
 */
class Kopfleiste
{
    /** @var array<string, Closure(User): (View|string|null)> */
    private static array $symbole = [];

    public static function registrieren(string $schluessel, Closure $symbol): void
    {
        self::$symbole[$schluessel] = $symbol;
    }

    /**
     * Gerenderte Symbole für einen Benutzer, Schlüssel => HTML.
     *
     * @return array<string, string>
     */
    public static function fuer(User $user): array
    {
        $html = [];

        foreach (self::$symbole as $schluessel => $symbol) {
            try {
                $ergebnis = $symbol($user);
                if ($ergebnis instanceof View) {
                    $ergebnis = $ergebnis->render();
                }
                if ($ergebnis !== null && trim((string) $ergebnis) !== '') {
                    $html[$schluessel] = (string) $ergebnis;
                }
            } catch (\Throwable $e) {
                Log::warning("Kopfleisten-Symbol „{$schluessel}\" für Benutzer {$user->id} fehlgeschlagen: ".$e->getMessage());
            }
        }

        return $html;
    }
}
