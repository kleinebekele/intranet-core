<?php

namespace App\Modules\Support;

use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Wie weit darf ein Benutzer auf einer Modulroute gehen? Eine Stelle für die
 * Middleware (EnsureModuleAccess) und für Views (`@darfRoute('…')`), damit ein
 * Knopf genau dann erscheint, wenn der Klick auch durchginge.
 *
 * Module samt Menüpunkten und Rollen werden je Anfrage einmal geladen (im
 * Request abgelegt), auch wenn eine Liste hundert Knöpfe prüft.
 */
class Modulzugriff
{
    private const ZWISCHENSPEICHER = 'modulzugriff.module';

    public function __construct(private ModuleRegistry $registry) {}

    /**
     * Die Stufe des Benutzers für diese Route; null = kein Zugang (Modul aus,
     * nur Admins, keine passende Rolle). Admins: verwalten.
     */
    public function stufeFuer(?User $user, string $routeName): ?Zugriffsstufe
    {
        if ($user?->is_admin) {
            return Zugriffsstufe::Verwalten;
        }
        if (! $user) {
            return null;
        }

        $module = $this->modul($routeName);
        if ($module === null || ! $module->is_enabled || $module->admins_only) {
            return null;
        }

        $item = $this->zustaendigerMenuepunkt($module, $routeName);

        return $item !== null ? $item->stufeFuer($user) : $module->stufeFuer($user);
    }

    /** Welche Stufe braucht diese Route mit dieser Anfrageart? */
    public function benoetigt(string $methode, string $routeName): Zugriffsstufe
    {
        return Zugriffsstufe::benoetigt($methode, $routeName, $this->registry->manifest($this->schluessel($routeName)));
    }

    /**
     * Ginge ein Aufruf dieser Route für den Benutzer durch? Die Anfrageart
     * kommt aus der Routendefinition. Routen außerhalb von `module.*` prüft
     * dieses System nicht → true.
     */
    public function darfRoute(string $routeName, ?User $user = null): bool
    {
        if (! str_starts_with($routeName, 'module.')) {
            return true;
        }

        $user ??= auth()->user();
        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return false;
        }

        $methode = collect($route->methods())->first(fn ($m) => $m !== 'HEAD') ?? 'GET';
        $stufe = $this->stufeFuer($user, $routeName);

        return $stufe !== null && $stufe->reichtFuer($this->benoetigt($methode, $routeName));
    }

    /**
     * Der Menüpunkt, der für diese Route zuständig ist: exakt, sonst der
     * spezifischste, dessen Route ein Präfix ist – `…orders.index` wie auch
     * `…auftragsimport` decken `…auftragsimport.upload` ab.
     */
    public function zustaendigerMenuepunkt(Module $module, string $routeName): ?ModuleMenuItem
    {
        foreach ($module->menuItems as $item) {
            if ($item->route_name === $routeName) {
                return $item;
            }
        }

        $treffer = null;
        $laenge = 0;

        foreach ($module->menuItems as $item) {
            $base = str_ends_with($item->route_name, '.index')
                ? substr($item->route_name, 0, -strlen('.index'))
                : $item->route_name;

            // Nur echte Unterbereiche (module.{key}.{bereich}) decken ihre
            // Unterseiten ab – nicht der Modul-Start (module.{key}[.index]).
            if (substr_count($base, '.') >= 2 && str_starts_with($routeName, $base.'.') && strlen($base) > $laenge) {
                $treffer = $item;
                $laenge = strlen($base);
            }
        }

        return $treffer;
    }

    /** Modul samt Menüpunkten und Rollen – je Anfrage einmal geladen. */
    private function modul(string $routeName): ?Module
    {
        $key = $this->schluessel($routeName);
        $request = request();
        $geladen = $request->attributes->get(self::ZWISCHENSPEICHER, []);

        if (! array_key_exists($key, $geladen)) {
            $geladen[$key] = Module::query()->with('menuItems.roles')->where('key', $key)->first();
            $request->attributes->set(self::ZWISCHENSPEICHER, $geladen);
        }

        return $geladen[$key];
    }

    private function schluessel(string $routeName): string
    {
        return explode('.', $routeName)[1] ?? '';
    }
}
