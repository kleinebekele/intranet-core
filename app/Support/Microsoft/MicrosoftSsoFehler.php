<?php

namespace App\Support\Microsoft;

use RuntimeException;

/**
 * Ein Anmeldeversuch über Microsoft ist gescheitert.
 *
 * Die Meldung ist bewusst so formuliert, dass sie dem Benutzer auf der
 * Anmeldeseite gezeigt werden kann – ohne technische Innereien, aber konkret
 * genug, dass der Admin im Protokoll (Verwaltung → Microsoft-SSO) sofort
 * sieht, an welcher Stelle es hakt.
 */
class MicrosoftSsoFehler extends RuntimeException
{
    /**
     * @param  string  $ergebnis  Kurzform für das Protokoll (z. B. fehler, keine_gruppe)
     */
    public function __construct(string $meldung, public readonly string $ergebnis = 'fehler')
    {
        parent::__construct($meldung);
    }
}
