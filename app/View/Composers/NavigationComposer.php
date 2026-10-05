<?php

namespace App\View\Composers;

use App\Models\Module;
use App\Modules\Support\Navigation;
use Illuminate\View\View;

/**
 * Feeds the left sidebar with the current navigation state on every request.
 * Bound to the "layouts.sidebar" view in AppServiceProvider.
 *
 * Hauptpunkte (ModuleManifest::hauptpunkt()) stehen getrennt unter „Startseite";
 * in einem Hauptpunkt bleibt die Startseiten-Leiste stehen (kein Modul-Kontext).
 */
class NavigationComposer
{
    public function __construct(protected Navigation $navigation) {}

    public function compose(View $view): void
    {
        [$hauptpunkte, $module] = $this->navigation->modules()
            ->partition(fn (Module $m) => $this->navigation->istHauptpunkt($m->key));

        $current = $this->navigation->currentModule();
        $aktiverHauptpunkt = $current && $this->navigation->istHauptpunkt($current->key) ? $current->key : null;

        $view->with('sidebarHauptpunkte', $hauptpunkte->values());
        $view->with('sidebarModules', $module->values());
        $view->with('aktiverHauptpunkt', $aktiverHauptpunkt);
        $view->with('currentModule', $aktiverHauptpunkt ? null : $current);
    }
}
