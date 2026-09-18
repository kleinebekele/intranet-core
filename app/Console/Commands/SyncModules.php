<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\Role;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleMigrations;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Copies every installed module (and its menu items) into the database so
 * the admin can order and toggle them.
 *
 * Run this after installing or updating a module:
 *   php artisan modules:sync
 *
 * Existing `position` and `is_enabled` values are preserved – a re-sync only
 * updates labels/names and adds newly discovered items at the end.
 */
class SyncModules extends Command
{
    protected $signature = 'modules:sync';

    protected $description = 'Sync installed modules and their menu items into the database.';

    public function handle(ModuleRegistry $registry, ModuleMigrations $migrationen): int
    {
        if ($registry->manifests()->isEmpty()) {
            $this->warn('No modules are currently installed.');

            return self::SUCCESS;
        }

        foreach ($registry->manifests() as $manifest) {
            $module = Module::firstOrNew(['key' => $manifest->key]);
            $module->name = $manifest->name;
            $module->icon = $manifest->icon;

            if (! $module->exists) {
                // New module: place it at the end and enable it by default.
                $module->position = $manifest->position ?: ((int) Module::max('position') + 1);
                $module->is_enabled = true;
            }

            $module->save();

            // Solange das Paket da ist, merken, welche Migration zu diesem
            // Modul gehört – beim Deinstallieren ist es dafür zu spät.
            $migrationen->aufzeichnen($manifest);

            $seen = [];

            foreach ($manifest->items as $item) {
                $seen[] = $item->key;

                $menuItem = ModuleMenuItem::firstOrNew([
                    'module_id' => $module->id,
                    'key' => $item->key,
                ]);
                $menuItem->label = $item->label;
                $menuItem->route_name = $item->routeName;
                $menuItem->icon = $item->icon;
                $menuItem->group_label = $item->group;

                if (! $menuItem->exists) {
                    // Use the manifest's 0-based position directly (0 is valid!).
                    $menuItem->position = $item->position;
                    // Manifest-Vorgabe nur beim Anlegen übernehmen – danach gehört
                    // die Sichtbarkeit dem Admin (im Panel umschaltbar).
                    $menuItem->admins_only = $item->adminsOnly;
                }

                $menuItem->save();
            }

            // Drop menu items that the module no longer declares.
            ModuleMenuItem::where('module_id', $module->id)
                ->whereNotIn('key', $seen ?: ['__none__'])
                ->delete();

            $this->rollenAbgleichen($manifest);

            $this->line("  <info>✓</info> {$manifest->key} — {$manifest->name} (".count($manifest->items).' Unterseiten, '.count($manifest->rollen).' Rollen)');
        }

        Role::aktivStandVergessen();

        $this->info('Module-Synchronisierung abgeschlossen.');

        return self::SUCCESS;
    }

    /**
     * Legt die Rollen aus dem Manifest an und ordnet sie dem Modul zu.
     *
     * Eine schon vorhandene Rolle gleichen Schlüssels wird übernommen – ihre
     * Zuweisungen und Menüpunkt-Rechte bleiben, sie bekommt nur den Besitzer.
     * Rollen, die das Modul nicht mehr anmeldet, werden freigegeben statt
     * gelöscht: sie gelten danach als von Hand angelegt.
     */
    private function rollenAbgleichen(ModuleManifest $manifest): void
    {
        $gesehen = [];

        foreach ($manifest->rollen as $rolle) {
            $eintrag = Role::find($rolle->roleId);

            if (in_array($rolle->roleId, ['admin', 'user'], true)) {
                $this->warn("    Rolle {$rolle->roleId} ist eine Basisrolle des Cores – übersprungen.");

                continue;
            }

            if ($eintrag && $eintrag->gehoertZuModul() && $eintrag->modul !== $manifest->key) {
                $this->warn("    Rolle {$rolle->roleId} gehört schon zum Modul {$eintrag->modul} – übersprungen.");

                continue;
            }

            $gesehen[] = $rolle->roleId;

            $eintrag ??= new Role(['role_id' => $rolle->roleId]);
            $eintrag->name = $rolle->name;
            $eintrag->forceFill([
                'modul' => $manifest->key,
                'plattformweit' => $rolle->plattformweit,
            ])->save();
        }

        Role::where('modul', $manifest->key)
            ->whereNotIn('role_id', $gesehen ?: ['__none__'])
            ->update(['modul' => null, 'plattformweit' => false]);
    }
}
