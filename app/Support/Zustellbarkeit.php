<?php

namespace App\Support;

use App\Console\Commands\MailAusliefern;
use App\Listeners\MailInDieOutbox;

/**
 * Welche Mailadressen dürfen den Server niemals verlassen?
 *
 * Hintergrund: Nicht jeder Benutzer hat eine echte Mailadresse. Schüler etwa
 * bekommen beim Import aus Linear eine künstliche (`schueler-4711@schueler.intern`),
 * damit sie sich anmelden und in der Kantine bestellen können. `.intern` gibt es
 * als Endung nicht – ein Versand dorthin kann niemanden erreichen, würde aber
 * beim Mailprovider als Fehlzustellung auflaufen und im schlimmsten Fall den Ruf
 * der Absenderadresse beschädigen.
 *
 * Deshalb wird nicht darauf vertraut, dass jede Stelle im System daran denkt.
 * Geprüft wird an den zwei Engstellen, durch die jede Mail muss:
 * {@see MailInDieOutbox} beim Einliefern und
 * {@see MailAusliefern} beim Versenden.
 */
class Zustellbarkeit
{
    /** Kann an diese Adresse überhaupt zugestellt werden? */
    public static function zustellbar(string $adresse): bool
    {
        $adresse = strtolower(trim($adresse));

        foreach ((array) config('mail.unzustellbare_endungen', ['.intern']) as $endung) {
            $endung = strtolower(trim((string) $endung));

            if ($endung !== '' && str_ends_with($adresse, $endung)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string[]  $adressen
     * @return string[] nur die zustellbaren
     */
    public static function filtern(array $adressen): array
    {
        return array_values(array_filter($adressen, static fn (string $a) => self::zustellbar($a)));
    }
}
