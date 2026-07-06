<?php

namespace App\Providers;

use App\Modules\Support\ModuleRegistry;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\URL;
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
        // Hinter TLS-Terminierung (nginx/Proxy) erkennt Laravel https nicht
        // immer selbst. Im Produktivbetrieb Links/Assets zwingend als https
        // erzeugen, sonst blockiert der Browser CSS/JS als „Mixed Content".
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Feed the left sidebar with the current navigation state.
        View::composer('layouts.sidebar', NavigationComposer::class);
    }
}
