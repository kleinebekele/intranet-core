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
 */
class Navigation
{
    public function __construct(protected ModuleRegistry $registry)
    {
    }

    /** @return Collection<int, Module> Enabled + installed modules, in admin order. */
    public function modules(): Collection
    {
        return Module::query()
            ->with('menuItems')
            ->where('is_enabled', true)
            ->whereIn('key', $this->registry->keys())
            ->orderBy('position')
            ->get();
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

        return Module::query()
            ->with('menuItems')
            ->where('key', $key)
            ->first();
    }
}
