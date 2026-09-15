<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Zusatzbereiche von Modulen auf der Seite „Benutzer bearbeiten" (Verwaltung).
 *
 * Ein Modul, das etwas über einen Benutzer weiß oder an ihm tun kann (verknüpftes
 * Konto in einem Fremdsystem, Chip, Guthaben …), hängt sich hier ein und liefert
 * je Benutzer ein fertiges Stück HTML – oder null, wenn es für diesen Benutzer
 * nichts zu zeigen gibt. Formulare darin gehören dem Modul (eigene Routen).
 *
 *   // im boot() des Modul-Providers
 *   Benutzerbereiche::registrieren('nextcloud', fn (User $user) => view('meinmodul::bereich', [...]));
 *
 * Ein Fehler im Bereich (Fremdsystem nicht erreichbar) darf die Benutzerseite
 * nicht mitreißen: er wird geloggt und als Hinweiskasten gezeigt.
 */
class Benutzerbereiche
{
    /** @var array<string, Closure(User): (View|string|null)> */
    private static array $bereiche = [];

    public static function registrieren(string $schluessel, Closure $bereich): void
    {
        self::$bereiche[$schluessel] = $bereich;
    }

    /**
     * Gerenderte Bereiche für einen Benutzer, Schlüssel => HTML.
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
                Log::warning("Benutzerbereich „{$schluessel}\" für Benutzer {$user->id} fehlgeschlagen: ".$e->getMessage());
                $html[$schluessel] = '<div class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-800">'
                    .'Bereich „'.e($schluessel).'" konnte nicht geladen werden: '.e($e->getMessage()).'</div>';
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
