<?php

namespace App\Mail\Vorlagen;

/**
 * Die unveränderliche Beschreibung einer Mailvorlage: was sie ist, welche
 * Platzhalter sie kennt und welche Texte gelten, solange niemand sie ändert.
 */
class VorlagenDefinition
{
    /** Schlüssel des Layout-Rahmens, der jede Mail umschließt. */
    public const RAHMEN = '_rahmen';

    /**
     * @param  array<string, string>  $platzhalter  name => Erklärung
     */
    public function __construct(
        public string $schluessel,
        public string $titel,
        public string $beschreibung,
        public array $platzhalter,
        public ?string $betreff,
        public string $html,
        public string $text,
    ) {}
}
