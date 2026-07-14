<?php

namespace App\Modules\Support;

/**
 * The "business card" of a module: it tells the core everything it needs to
 * show the module in the navigation and admin panel.
 *
 * A module builds its manifest fluently inside its service provider:
 *
 *   return ModuleManifest::make('news', 'Neuigkeiten', icon: 'newspaper')
 *       ->item('index', 'Übersicht', 'module.news')
 *       ->item('create', 'Beitrag anlegen', 'module.news.create');
 */
class ModuleManifest
{
    /** @param  MenuItem[]  $items */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $icon = null,
        public int $position = 0,
        public array $items = [],
    ) {
    }

    public static function make(string $key, string $name, ?string $icon = null, int $position = 0): static
    {
        return new static($key, $name, $icon, $position);
    }

    /**
     * Register a sub-page (menu item) of this module.
     *
     * @param  string       $key         Stable identifier, unique within the module.
     * @param  string       $label       Text shown in the left menu.
     * @param  string       $routeName   Laravel route name this item links to.
     * @param  string|null  $icon        Icon-Name (siehe x-module-icon); null = neutraler Punkt.
     * @param  int          $position    Default order (admin can override later).
     * @param  bool         $adminsOnly  Nur für Admins sichtbar (beim Anlegen via modules:sync gesetzt).
     * @param  string|null  $group       Überschrift, unter der dieser Punkt im Menü
     *                                   aufklappbar gebündelt wird; null = eigene Zeile.
     *                                   Rein optisch – der Punkt bleibt ein eigener
     *                                   Eintrag mit eigenen Rollen und eigener
     *                                   Zugriffsregel.
     */
    public function item(string $key, string $label, string $routeName, ?string $icon = null, int $position = 0, bool $adminsOnly = false, ?string $group = null): static
    {
        $this->items[] = new MenuItem(
            key: $key,
            label: $label,
            routeName: $routeName,
            position: $position ?: count($this->items),
            icon: $icon,
            adminsOnly: $adminsOnly,
            group: $group,
        );

        return $this;
    }
}
