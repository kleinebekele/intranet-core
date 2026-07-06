<?php

namespace App\Modules\Support;

/**
 * A single sub-page inside a module (one entry in the module's left menu).
 *
 * Modules do not create this directly – they use {@see ModuleManifest::item()}.
 */
class MenuItem
{
    public function __construct(
        public string $key,
        public string $label,
        public string $routeName,
        public int $position = 0,
        public ?string $icon = null,
        public bool $adminsOnly = false,
    ) {
    }
}
