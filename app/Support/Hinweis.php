<?php

namespace App\Support;

/**
 * Ein offener Hinweis für die Glocke in der Kopfzeile – ein Zustand, der
 * jemanden braucht, der hinschaut (z. B. „3 Benachrichtigungen nicht
 * zugestellt"). Der Core zeigt ihn nur an; was dahintersteckt und wann er
 * verschwindet, weiß allein das Modul, das ihn liefert.
 */
final class Hinweis
{
    public function __construct(
        /** Kurz und konkret, eine Zeile: „3 Benachrichtigungen nicht zugestellt". */
        public readonly string $titel,
        /** Wohin der Klick führt – die Seite, auf der man den Zustand beheben kann. */
        public readonly string $url,
        /** Woher der Hinweis kommt (Modul-/Bereichsname), erscheint klein darunter. */
        public readonly string $quelle = '',
        /** Wie viele Einzelfälle hinter dem Hinweis stecken – zählt in den roten Punkt. */
        public readonly int $anzahl = 1,
    ) {}
}
