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
     * @param  string|null  $rahmen  In welchen Rahmen wird diese Vorlage gelegt?
     *                               `null` heißt: sie IST selbst ein Rahmen und
     *                               wird nicht noch einmal eingerahmt.
     * @param  array<string, string>  $beispiele  Eigene Vorschauwerte je Platzhalter.
     *                                            Ergänzt die allgemeinen Beispiele
     *                                            des Editors und gewinnt gegen sie.
     * @param  string  $modul  Wer löst diese Mail aus – fürs Maillog. Der Core
     *                         bringt „Core" mit; Module setzen ihren Namen.
     * @param  string|null  $ausloeser  Der Anlass, wie er im Maillog stehen soll.
     *                                  `null` = der Titel wird verwendet.
     */
    public function __construct(
        public string $schluessel,
        public string $titel,
        public string $beschreibung,
        public array $platzhalter,
        public ?string $betreff,
        public string $html,
        public string $text,
        public ?string $rahmen = self::RAHMEN,
        public array $beispiele = [],
        public string $modul = 'Core',
        public ?string $ausloeser = null,
    ) {}

    /** Ist das selbst ein Rahmen (und keine versendbare Mail)? */
    public function istRahmen(): bool
    {
        return $this->rahmen === null;
    }

    /** Der Auslöser fürs Maillog – eigener Text, sonst der Titel. */
    public function ausloeser(): string
    {
        return ($this->ausloeser !== null && $this->ausloeser !== '')
            ? $this->ausloeser
            : $this->titel;
    }
}
