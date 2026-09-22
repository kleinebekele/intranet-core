<?php

namespace App\Ekkon\Support;

use App\Models\User;
use Throwable;

/**
 * Vermittlungsstelle für Kontextwissen der KI (Teams-Chat).
 *
 * Der Core kennt kein Wiki und keine Fachdaten. Er stellt nur die Frage „was
 * weißt du zu diesem Text für diese Person?" – Module melden dafür eine Quelle
 * an (Muster wie App\Support\Hilfe). Jede Quelle liefert Textstücke mit
 * Herkunftsangabe; der KiClient hängt sie an den Systemprompt.
 *
 * Anmeldung im `boot()` des Moduls:
 *
 *   Wissensquellen::anmelden('Wiki', fn (string $frage, ?User $benutzer, int $limit): array => [
 *       ['quelle' => 'Wiki › Urlaub', 'text' => '…'],
 *   ]);
 *
 * ⚠️ Die Quelle muss die Sichtbarkeit SELBST prüfen: `$benutzer` ist die Person,
 * die in Teams fragt (über die Microsoft-ID zugeordnet) – oder null, wenn sie
 * kein Intranet-Konto hat. Dann darf nur, was für alle gedacht ist, zurückkommen.
 */
class Wissensquellen
{
    /** @var array<string, callable(string, ?User, int): array<int, array{quelle: string, text: string}>> */
    private static array $quellen = [];

    public static function anmelden(string $name, callable $suche): void
    {
        static::$quellen[$name] = $suche;
    }

    /** @return array<int, string> */
    public static function namen(): array
    {
        return array_keys(static::$quellen);
    }

    public static function verfuegbar(): bool
    {
        return static::$quellen !== [];
    }

    /**
     * Alle Quellen befragen. Eine Quelle, die wirft, fällt still aus (die
     * Antwort kommt dann ohne sie) – ein kaputtes Modul darf den Chat nicht lahmlegen.
     *
     * @return array<int, array{quelle: string, text: string}>
     */
    public static function suchen(string $frage, ?User $benutzer, int $limit = 6): array
    {
        $treffer = [];
        foreach (static::$quellen as $name => $suche) {
            try {
                foreach ((array) $suche($frage, $benutzer, $limit) as $t) {
                    $text = trim((string) ($t['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $treffer[] = ['quelle' => (string) ($t['quelle'] ?? $name), 'text' => $text];
                }
            } catch (Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Wissensquelle '.$name.' fehlgeschlagen', ['fehler' => $e->getMessage()]);
            }
        }

        return $treffer;
    }
}
