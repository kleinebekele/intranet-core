<?php

namespace Tests\Feature;

use App\Ekkon\Models\TaskRun;
use App\Ekkon\Support\TaskRegistry;
use App\Ekkon\Tasks\EkkonTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ekkon ist fester Bestandteil des Cores. Fachmodule, die noch die alten
 * Namen (`Intranet\Modules\Ekkon\…`) benutzen, laufen unverändert weiter.
 */
class EkkonImCoreTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTRY_ALT = 'Intranet\\Modules\\Ekkon\\Support\\TaskRegistry';

    public function test_tabellen_routen_und_eigene_tasks_sind_da(): void
    {
        $this->assertTrue(Schema::hasTable('ekkon_task_runs'));
        $this->assertTrue(Schema::hasTable('ekkon_notifications'));
        $this->assertTrue(Schema::hasTable('ekkon_webhook_eingaenge'));

        $this->assertTrue(Route::has('module.ekkon.index'));
        $this->assertTrue(Route::has('ekkon.webhook.empfangen'));

        // Task-Keys wie im alten Paket – Pausen, Einstellungen und Routen hängen daran.
        $this->assertNotNull($this->app->make(TaskRegistry::class)->find('Notifications/SendNotifications'));
    }

    public function test_alte_klassennamen_zeigen_auf_den_core(): void
    {
        $this->assertTrue(class_exists('Intranet\\Modules\\Ekkon\\Tasks\\EkkonTask'));
        $this->assertSame(
            EkkonTask::class,
            (new \ReflectionClass('Intranet\\Modules\\Ekkon\\Tasks\\EkkonTask'))->getName(),
        );
        $this->assertTrue(class_exists('Intranet\\Modules\\Ekkon\\Models\\WebhookEingang'));
    }

    public function test_alter_und_neuer_registry_name_sind_dieselbe_instanz(): void
    {
        $this->assertSame(
            $this->app->make(TaskRegistry::class),
            $this->app->make(self::REGISTRY_ALT),
        );
    }

    public function test_singleton_if_unter_beiden_namen_ersetzt_die_registry_nicht(): void
    {
        $registry = $this->app->make(TaskRegistry::class);

        // So melden sich Module an – das eine neu, das andere noch alt.
        $this->app->singletonIf(TaskRegistry::class);
        $this->app->singletonIf(self::REGISTRY_ALT);

        $this->assertSame($registry, $this->app->make(TaskRegistry::class));
        $this->assertSame($registry, $this->app->make(self::REGISTRY_ALT));
    }

    public function test_task_mit_altem_elternnamen_wird_gefunden_und_laeuft(): void
    {
        config(['ekkon.tasks_enabled' => true]);

        // Frische Registry, wie ein Fachmodul sie im register() befüllt.
        $registry = new TaskRegistry;
        $registry->addSource(base_path('tests/Fixtures/alttasks'), 'Tests\\Fixtures\\alttasks', 'test/alt');
        $this->app->instance(TaskRegistry::class, $registry);

        $this->assertNotNull($registry->find('Probe/Altlast'));

        $this->artisan('ekkon:task', ['key' => 'Probe/Altlast', '--trigger' => 'manual'])->assertSuccessful();

        $lauf = TaskRun::where('task_key', 'Probe/Altlast')->first();
        $this->assertNotNull($lauf);
        $this->assertSame('ok', $lauf->status);
    }

    public function test_ekkon_laesst_sich_nicht_deinstallieren(): void
    {
        $this->artisan('modules:sync');
        // Vermerk aus der Paket-Zeit: darf nach dem Sync niemandem mehr gehören.
        $this->assertDatabaseMissing('module_migrations', ['module_key' => 'ekkon']);

        $this->artisan('modules:uninstall ekkon --mit-daten')
            ->expectsConfirmation('Modul „ekkon" entfernen UND seine Tabellen samt Inhalt löschen?', 'yes')
            ->assertFailed();

        $this->assertDatabaseHas('modules', ['key' => 'ekkon']);
        $this->assertTrue(Schema::hasTable('ekkon_task_runs'));
    }

    public function test_aufgaben_seite_oeffnet_fuer_admins(): void
    {
        $admin = User::factory()->create(); // erster Benutzer wird Admin
        $this->artisan('modules:sync');

        $this->actingAs($admin)->get(route('module.ekkon.index'))->assertOk()->assertSee('Notifications');
    }
}
