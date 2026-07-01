<?php

namespace App\Providers;

use App\Modules\Support\ModuleRegistry;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared registry that every module provider pushes its manifest into.
        // singletonIf so that a module provider (which registers earlier) can
        // create it first without us later replacing it with an empty one.
        $this->app->singletonIf(ModuleRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Feed the left sidebar with the current navigation state.
        View::composer('layouts.sidebar', NavigationComposer::class);
    }
}
