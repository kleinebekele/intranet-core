<?php

namespace App\Modules\Support;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base class every module's service provider extends.
 *
 * It removes all boilerplate: a module only has to describe itself in
 * {@see manifest()}, and this class wires up its routes, views and
 * migrations automatically by convention.
 *
 * Expected package layout (see MODULES.md):
 *   src/XxxServiceProvider.php   <- extends this class
 *   routes/web.php
 *   resources/views/
 *   database/migrations/
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    private ?ModuleManifest $cachedManifest = null;

    /**
     * Describe the module: its key, name, icon and sub-pages.
     */
    abstract public function manifest(): ModuleManifest;

    public function register(): void
    {
        // Module (package) providers register BEFORE the core AppServiceProvider,
        // so make sure the shared registry exists before we use it. singletonIf
        // binds it only once – whoever runs first wins, everyone shares it.
        $this->app->singletonIf(ModuleRegistry::class);

        // Announce this module to the core so the sidebar, admin panel and
        // the `modules:sync` command know it exists.
        $this->app->make(ModuleRegistry::class)->register($this->resolvedManifest());
    }

    public function boot(): void
    {
        $key = $this->resolvedManifest()->key;
        $base = $this->moduleBasePath();

        if (is_file($routes = "{$base}/routes/web.php")) {
            $this->loadRoutesFrom($routes);
        }

        // Views become available under the module key, e.g. view('news::index').
        if (is_dir($views = "{$base}/resources/views")) {
            $this->loadViewsFrom($views, $key);
        }

        if (is_dir($migrations = "{$base}/database/migrations")) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_dir($lang = "{$base}/lang")) {
            $this->loadTranslationsFrom($lang, $key);
        }
    }

    protected function resolvedManifest(): ModuleManifest
    {
        if ($this->cachedManifest === null) {
            $this->cachedManifest = $this->manifest();
            // Das Modul beschreibt sich fachlich, den Ort kennt nur der Provider.
            $this->cachedManifest->basePath ??= $this->moduleBasePath();
        }

        return $this->cachedManifest;
    }

    /**
     * The package root: the provider lives in src/, so the root is two
     * directories up from this file.
     */
    protected function moduleBasePath(): string
    {
        return dirname((new ReflectionClass($this))->getFileName(), 2);
    }
}
