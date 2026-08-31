<?php

namespace App\Modules\Support;

/**
 * A single sub-page inside a module (one entry in the module's left menu).
 *
 * Modules do not create this directly – they use {@see ModuleManifest::item()}.
 */
class MenuItem
{
    /**
     * @param  \Closure|null  $visibleWhen  Optionaler Laufzeit-Filter: liefert er
     *   false, wird der Punkt ausgeblendet (zusätzlich zur Rollen-Prüfung). Für
     *   Zustände, die NICHT über Rollen abbildbar sind – etwa ein Saison-Schalter
     *   „Bewertungen erlauben". Wird je Anfrage in der Navigation ausgewertet,
     *   nicht beim Boot; das Modul muss die Abfrage also selbst günstig halten.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $routeName,
        public int $position = 0,
        public ?string $icon = null,
        public bool $adminsOnly = false,
        public ?string $group = null,
        public ?\Closure $visibleWhen = null,
    ) {}
}
