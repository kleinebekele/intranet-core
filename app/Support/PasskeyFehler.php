<?php

namespace App\Support;

use RuntimeException;

/** Eine Passkey-Antwort des Browsers hat die Prüfung nicht bestanden. */
class PasskeyFehler extends RuntimeException
{
    /**
     * Der Passkey ist in Ordnung, passt aber nicht: hier nicht (mehr)
     * hinterlegt oder zu einem anderen Konto. Dann bieten wir nach der
     * Passwort-Anmeldung an, auf diesem Gerät einen neuen anzulegen.
     */
    public const PASST_NICHT = 1;

    public function passtNicht(): bool
    {
        return $this->getCode() === self::PASST_NICHT;
    }
}
