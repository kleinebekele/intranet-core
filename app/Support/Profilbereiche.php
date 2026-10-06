<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Zusatzbereiche von Modulen auf der eigenen Profilseite – das Gegenstück zu
 * {@see Benutzerbereiche} (dort sieht die Verwaltung einen Benutzer, hier die
 * Person sich selbst). Beispiel: ein Postfach-Passwort hinterlegen.
 *
 *   // im boot() des Modul-Providers
 *   Profilbereiche::registrieren('mail-passwort', fn (User $user) => view('meinmodul::profil', [...]));
 *
 * null oder leer = für diese Person nichts zu zeigen. Der Core legt jeden Bereich
 * in eine eigene Karte; ein Fehler im Bereich reißt die Profilseite nicht mit.
 */
class Profilbereiche
{
    /** @var array<string, Closure(User): (View|string|null)> */
    private static array $bereiche = [];

    public static function registrieren(string $schluessel, Closure $bereich): void
    {
        self::$bereiche[$schluessel] = $bereich;
    }

    /**
     * Gerenderte Bereiche für eine Person, Schlüssel => HTML.
     *
     * @return array<string, string>
     */
    public static function fuer(User $user): array
    {
        $html = [];

        foreach (self::$bereiche as $schluessel => $bereich) {
            try {
                $ergebnis = $bereich($user);
                if ($ergebnis instanceof View) {
                    $ergebnis = $ergebnis->render();
                }
                if ($ergebnis === null || trim((string) $ergebnis) === '') {
                    continue;
                }
                $html[$schluessel] = (string) $ergebnis;
            } catch (\Throwable $e) {
                Log::warning("Profilbereich „{$schluessel}\" für Benutzer {$user->id} fehlgeschlagen: ".$e->getMessage());
            }
        }

        return $html;
    }

    /** Nur für Tests. */
    public static function zuruecksetzen(): void
    {
        self::$bereiche = [];
    }
}
