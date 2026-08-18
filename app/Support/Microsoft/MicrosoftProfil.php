<?php

namespace App\Support\Microsoft;

/**
 * Was Microsoft über den gerade Angemeldeten verrät – schon aufgeräumt:
 * die unveränderliche Objekt-ID, eine E-Mail-Adresse in Kleinbuchstaben,
 * der Anzeigename und die Gruppen-IDs, soweit sie zu ermitteln waren.
 */
class MicrosoftProfil
{
    /**
     * @param  array<int, string>  $gruppen  Objekt-IDs der Gruppen (kann leer sein)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly array $gruppen,
        public readonly bool $gruppenBekannt,
    ) {}

    /** Ist dieses Konto in mindestens einer der angegebenen Gruppen? */
    public function inEinerGruppe(array $erlaubte): bool
    {
        foreach ($this->gruppen as $gruppe) {
            foreach ($erlaubte as $erlaubt) {
                if (strcasecmp($gruppe, $erlaubt) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
