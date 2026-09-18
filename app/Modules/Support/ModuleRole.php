<?php

namespace App\Modules\Support;

/**
 * Eine Rolle, die ein Modul mitbringt.
 *
 * Modules do not create this directly – they use {@see ModuleManifest::rolle()}.
 */
class ModuleRole
{
    /**
     * @param  bool  $plattformweit  Die Rolle wird auch außerhalb des Moduls
     *   gebraucht (z. B. „Lehrer") und steht deshalb bei jedem Modul zur Auswahl.
     */
    public function __construct(
        public string $roleId,
        public string $name,
        public bool $plattformweit = false,
    ) {}
}
