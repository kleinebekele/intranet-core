<?php

namespace App\Support;

/**
 * Vermittlungsstelle für die bekannten Modul+Auslöser-Kombinationen.
 *
 * Damit man den Absender einer Mail einstellen kann, BEVOR sie zum ersten Mal
 * verschickt wurde, meldet jedes Modul seine Auslöser hier an. Der Core kennt
 * die Auslöser der Module nicht – er fragt nur nach.
 *
 * Anmeldung im `boot()` des Moduls (der Rückgabewert wird erst ausgewertet,
 * wenn die Konfig-Seite geöffnet wird – kein DB-Zugriff je Anfrage):
 *
 *   Mailausloeser::anbieten(fn () => [
 *       ['modul' => 'Aftersales-Portal', 'ausloeser' => 'Zahlungserinnerung'],
 *       ['modul' => 'Aftersales-Portal', 'ausloeser' => 'Versandbestätigung'],
 *   ]);
 */
class Mailausloeser
{
    /** @var array<int, callable(): iterable<array{modul: string, ausloeser: string}>> */
    private static array $anbieter = [];

    public static function anbieten(callable $aufloeser): void
    {
        static::$anbieter[] = $aufloeser;
    }

    /**
     * Alle angemeldeten Kombinationen, dedupliziert.
     *
     * @return array<string, array{modul: string, ausloeser: string}>
     *         Schlüssel ist „modul|ausloeser", damit Aufrufer leicht mergen können.
     */
    public static function alle(): array
    {
        $kombis = [];

        foreach (static::$anbieter as $aufloeser) {
            foreach ($aufloeser() as $eintrag) {
                $modul = trim((string) ($eintrag['modul'] ?? ''));
                $ausloeser = trim((string) ($eintrag['ausloeser'] ?? ''));

                if ($modul === '' || $ausloeser === '') {
                    continue;
                }

                $kombis[$modul.'|'.$ausloeser] = ['modul' => $modul, 'ausloeser' => $ausloeser];
            }
        }

        return $kombis;
    }

    /** Nur für Tests: alle Anbieter vergessen. */
    public static function vergessen(): void
    {
        static::$anbieter = [];
    }
}
