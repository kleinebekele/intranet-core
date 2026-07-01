<?php

namespace App\Modules\Support;

use Illuminate\Support\Collection;

/**
 * Central list of every module that is currently installed (i.e. whose
 * package is present and whose service provider has registered itself).
 *
 * This lives as a singleton (see AppServiceProvider) so that every module
 * provider can push its manifest into the same instance during boot.
 */
class ModuleRegistry
{
    /** @var array<string, ModuleManifest> */
    protected array $manifests = [];

    public function register(ModuleManifest $manifest): void
    {
        $this->manifests[$manifest->key] = $manifest;
    }

    /** @return Collection<int, ModuleManifest> */
    public function manifests(): Collection
    {
        return collect(array_values($this->manifests));
    }

    public function manifest(string $key): ?ModuleManifest
    {
        return $this->manifests[$key] ?? null;
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->manifests);
    }

    /**
     * Work out which module (if any) the given route name belongs to.
     *
     * Convention: every module names its routes "module.{key}" or
     * "module.{key}.something". That prefix is how we know we are "inside"
     * a module and should swap the sidebar to the module context.
     */
    public function currentKey(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        foreach (array_keys($this->manifests) as $key) {
            if ($routeName === "module.{$key}" || str_starts_with($routeName, "module.{$key}.")) {
                return $key;
            }
        }

        return null;
    }
}
