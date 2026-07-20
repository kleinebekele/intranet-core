<?php

namespace App\Modules\Support;

use App\Models\Module;
use Illuminate\Support\Collection;

/**
 * Builds the data for the left sidebar.
 *
 * Two contexts:
 *  - "home"    -> a list of all enabled modules (their landing pages)
 *  - "module"  -> the current module's name + its sub-pages
 *
 * A module is only shown when it is BOTH enabled in the database AND
 * actually installed (its package is present in the registry). That way a
 * removed package silently disappears instead of producing dead links.
 *
 * Aus demselben Grund fliegt auch ein Modul raus, von dem der Benutzer
 * KEINEN Unterpunkt sehen darf – etwa weil es (noch) gar keine Seiten
 * mitbringt. Es hätte in Sidebar und Dashboard nur ein totes Ziel (`#`).
 * Die Modulverwaltung fragt die Module direkt ab und zeigt es weiterhin.
 */
class Navigation
{
    public function __construct(protected ModuleRegistry $registry) {}

    /** @return Collection<int, Module> Enabled + installed modules the current user may see, in admin order. */
    public function modules(): Collection
    {
        $user = auth()->user();

        return Module::query()
            ->with(['menuItems.roles', 'roles'])
            ->where('is_enabled', true)
            ->whereIn('key', $this->registry->keys())
            ->orderBy('position')
            ->get()
            ->filter(fn (Module $module) => $module->isVisibleTo($user))
            ->each(fn (Module $module) => $module->setRelation(
                'menuItems',
                $module->menuItems->filter(fn ($item) => $item->isVisibleTo($user))->values()
            ))
            // Nach dem Filtern: ohne sichtbaren Unterpunkt gibt es nichts zu verlinken.
            ->reject(fn (Module $module) => $module->menuItems->isEmpty())
            ->values();
    }

    public function currentModuleKey(): ?string
    {
        return $this->registry->currentKey(request()->route()?->getName());
    }

    /** The module we are currently viewing, with its ordered menu items. */
    public function currentModule(): ?Module
    {
        $key = $this->currentModuleKey();

        if (! $key) {
            return null;
        }

        $module = Module::query()
            ->with(['menuItems.roles', 'roles'])
            ->where('key', $key)
            ->first();

        if ($module) {
            $user = auth()->user();
            $module->setRelation(
                'menuItems',
                $module->menuItems->filter(fn ($item) => $item->isVisibleTo($user))->values()
            );
        }

        return $module;
    }
}
