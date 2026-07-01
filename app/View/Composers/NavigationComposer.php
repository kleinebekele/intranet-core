<?php

namespace App\View\Composers;

use App\Modules\Support\Navigation;
use Illuminate\View\View;

/**
 * Feeds the left sidebar with the current navigation state on every request.
 * Bound to the "layouts.sidebar" view in AppServiceProvider.
 */
class NavigationComposer
{
    public function __construct(protected Navigation $navigation)
    {
    }

    public function compose(View $view): void
    {
        $view->with('sidebarModules', $this->navigation->modules());
        $view->with('currentModule', $this->navigation->currentModule());
    }
}
