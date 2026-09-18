<?php

namespace Tests\Feature;

use App\Ekkon\Models\TaskState;
use App\Ekkon\Support\TaskRegistry;
use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use App\Modules\Support\ModuleUninstaller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ein Modul geht in einem anderen auf (ekkon-linear → verwaltung): Migrationen
 * und Rollen ziehen mit, und das Entfernen des alten Moduls fasst nichts an,
 * was inzwischen dem neuen gehört.
 */
class ModulUmzugTest extends TestCase
{
    use RefreshDatabase;

    private function modul(string $key, bool $mitMigration, array $rollen = []): ModuleManifest
    {
        $manifest = ModuleManifest::make($key, ucfirst($key))->item('index', 'Start', "module.{$key}.index");
        // Beide Module bringen dieselbe Migrationsdatei mit (tests/Fixtures/testmodul).
        $manifest->basePath = $mitMigration ? base_path('tests/Fixtures/testmodul') : base_path('tests/Fixtures/leer');
        foreach ($rollen as $id => $name) {
            $manifest->rolle($id, $name);
        }

        return $manifest;
    }

    private function installiert(ModuleManifest ...$manifeste): void
    {
        $this->app->forgetInstance(ModuleRegistry::class);
        $this->app->singleton(ModuleRegistry::class);
        foreach ($manifeste as $manifest) {
            $this->app->make(ModuleRegistry::class)->register($manifest);
        }
        $this->app->forgetInstance(ModuleUninstaller::class);
        Role::aktivStandVergessen();
    }

    private function tabelleAnlegen(): void
    {
        Schema::create('tm_dinge', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        DB::table('migrations')->insert(['migration' => '2026_01_01_000000_create_tm_dinge_table', 'batch' => 99]);
    }

    public function test_migration_und_rolle_ziehen_zum_neuen_modul_um(): void
    {
        $this->tabelleAnlegen();

        // Vorher: alles gehört „alt".
        $this->installiert($this->modul('alt', true, ['chip-admin' => 'Chip-Admin']));
        $this->artisan('modules:sync');
        $user = User::factory()->create();
        $user->roles()->attach('chip-admin');
        $this->assertDatabaseHas('module_migrations', ['module_key' => 'alt']);

        // Umzug: „neu" bringt Migration und Rolle mit, „alt" ist nur noch Hülle.
        $this->installiert($this->modul('alt', false), $this->modul('neu', true, ['chip-admin' => 'Chip-Admin']));
        $this->artisan('modules:sync');

        $this->assertDatabaseMissing('module_migrations', ['module_key' => 'alt']);
        $this->assertDatabaseHas('module_migrations', ['module_key' => 'neu']);
        $this->assertSame('neu', Role::find('chip-admin')->modul);

        // „alt" MIT Daten entfernen: Tabelle, Rolle und Mitglied bleiben.
        $this->installiert($this->modul('neu', true, ['chip-admin' => 'Chip-Admin']));
        $this->app->make(ModuleUninstaller::class)->entfernen('alt', mitDaten: true);

        $this->assertTrue(Schema::hasTable('tm_dinge'));
        $this->assertSame('neu', Role::find('chip-admin')->modul);
        $this->assertTrue($user->fresh()->hatRolle('chip-admin'));
    }

    public function test_auch_vor_dem_sync_ruehrt_das_alte_modul_die_tabelle_nicht_an(): void
    {
        $this->tabelleAnlegen();
        $this->installiert($this->modul('alt', true));
        $this->artisan('modules:sync');

        // Beide Pakete nebeneinander, Vermerk steht noch bei „alt".
        $this->installiert($this->modul('alt', true), $this->modul('neu', true));

        $vorschau = $this->app->make(ModuleUninstaller::class)->vorschau('alt');
        $this->assertSame([], $vorschau['migrationen']);
    }

    public function test_rolle_bleibt_beim_alten_modul_solange_es_sie_selbst_anmeldet(): void
    {
        $this->installiert(
            $this->modul('alt', false, ['chip-admin' => 'Chip-Admin']),
            $this->modul('neu', false, ['chip-admin' => 'Gekapert']),
        );
        $this->artisan('modules:sync');

        $this->assertSame('alt', Role::find('chip-admin')->modul);
    }

    public function test_entfernen_gibt_rollen_frei_oder_loescht_sie_mit_daten(): void
    {
        $manifest = $this->modul('alt', false, ['alt-koch' => 'Koch']);
        $manifest->rolle('alt-lehrer', 'Lehrer', plattformweit: true);
        $this->installiert($manifest);
        $this->artisan('modules:sync');
        $user = User::factory()->create();
        $user->roles()->attach(['alt-koch', 'alt-lehrer']);

        // Schonend: freigegeben, Mitglied bleibt.
        $bericht = $this->app->make(ModuleUninstaller::class)->entfernen('alt');
        $this->assertSame(2, $bericht['rollen_freigegeben']);
        $this->assertNull(Role::find('alt-koch')->modul);
        $this->assertTrue($user->fresh()->hatRolle('alt-koch'));

        // Neu installiert → übernimmt sie wieder; dann MIT Daten entfernen.
        $this->artisan('modules:sync');
        $this->assertSame('alt', Role::find('alt-koch')->modul);

        $bericht = $this->app->make(ModuleUninstaller::class)->entfernen('alt', mitDaten: true);
        $this->assertSame(1, $bericht['rollen_geloescht']);
        $this->assertNull(Role::find('alt-koch'));
        // Plattformweite Rolle bleibt – an ihr hängen auch andere Module.
        $this->assertNotNull(Role::find('alt-lehrer'));
        $this->assertTrue($user->fresh()->hatRolle('alt-lehrer'));
    }

    public function test_mit_daten_raeumt_auch_die_ekkon_spuren_der_tasks_ab(): void
    {
        $this->installiert($this->modul('alt', false));
        $this->artisan('modules:sync');

        $registry = new TaskRegistry;
        $registry->addSource(base_path('tests/Fixtures/alttasks'), 'Tests\\Fixtures\\alttasks', 'test/alt', 'alt');
        $this->app->instance(TaskRegistry::class, $registry);

        TaskState::create(['task_key' => 'Probe/Altlast', 'enabled' => false]);
        TaskState::create(['task_key' => 'Fremd/Task', 'enabled' => false]);

        $this->app->make(ModuleUninstaller::class)->entfernen('alt');
        $this->assertDatabaseHas('ekkon_task_states', ['task_key' => 'Probe/Altlast']);

        $this->artisan('modules:sync');
        $bericht = $this->app->make(ModuleUninstaller::class)->entfernen('alt', mitDaten: true);

        $this->assertSame(1, $bericht['ekkon_zeilen']);
        $this->assertDatabaseMissing('ekkon_task_states', ['task_key' => 'Probe/Altlast']);
        $this->assertDatabaseHas('ekkon_task_states', ['task_key' => 'Fremd/Task']);
    }
}
