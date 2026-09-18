<?php

namespace Tests\Feature;

use App\Ekkon\Models\TaskRun;
use App\Ekkon\Support\TaskRegistry;
use App\Models\Module;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tasks gehören zu einem Modul: Sie laufen nur, solange es aktiv ist, und
 * stehen überall nach Modul gruppiert.
 */
class EkkonTasksJeModulTest extends TestCase
{
    use RefreshDatabase;

    private TaskRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ekkon.tasks_enabled' => true]);

        $this->app->make(ModuleRegistry::class)->register(
            ModuleManifest::make('probe', 'Probemodul', icon: 'cog')->item('index', 'Start', 'module.probe.index'),
        );
        $this->artisan('modules:sync');

        // Registry wie nach dem register() aller Provider: Core-Tasks + ein Modul.
        $this->registry = new TaskRegistry;
        $this->registry->addSource(app_path('Ekkon/Tasks'), 'App\\Ekkon\\Tasks', 'core', TaskRegistry::SYSTEM);
        $this->registry->addSource(base_path('tests/Fixtures/alttasks'), 'Tests\\Fixtures\\alttasks', 'test/alt', 'probe');
        $this->app->instance(TaskRegistry::class, $this->registry);
    }

    private function modulAus(): void
    {
        Module::where('key', 'probe')->update(['is_enabled' => false]);
        $this->registry->modulStatusVergessen();
    }

    public function test_tasks_kennen_ihr_modul(): void
    {
        $this->assertSame('probe', $this->registry->modulFuer('Probe/Altlast'));
        $this->assertSame(TaskRegistry::SYSTEM, $this->registry->modulFuer('Notifications/SendNotifications'));
    }

    public function test_modul_key_wird_aus_dem_paketnamen_erraten(): void
    {
        $registry = new TaskRegistry;
        $registry->addSource(base_path('tests/Fixtures/alttasks'), 'Tests\\Fixtures\\alttasks', 'do1emu/module-probe');
        $this->assertSame('probe', $registry->modulFuer('Probe/Altlast'));

        $fremd = new TaskRegistry;
        $fremd->addSource(base_path('tests/Fixtures/alttasks'), 'Tests\\Fixtures\\alttasks', 'do1emu/module-unbekannt');
        $this->assertNull($fremd->modulFuer('Probe/Altlast'));
        $this->assertTrue($fremd->modulAktiv('Probe/Altlast'));
    }

    public function test_gruppierung_system_zuerst_deaktivierte_ans_ende(): void
    {
        $namen = fn () => array_column($this->registry->byModule(), 'name');

        $this->assertSame(['System', 'Probemodul'], $namen());
        $this->assertTrue($this->registry->byModule()[1]['aktiv']);

        $this->modulAus();

        $gruppe = $this->registry->byModule()[1];
        $this->assertFalse($gruppe['aktiv']);
        $this->assertArrayHasKey('Probe/Altlast', $gruppe['kategorien']['Probe']);
    }

    public function test_task_eines_deaktivierten_moduls_laeuft_nicht(): void
    {
        $this->modulAus();

        // Geplant: lautlos, kein Eintrag.
        $this->artisan('ekkon:task', ['key' => 'Probe/Altlast'])->assertSuccessful();
        $this->assertSame(0, TaskRun::where('task_key', 'Probe/Altlast')->count());

        // Von Hand: übersprungen, mit Begründung – run() wird nicht erreicht.
        $this->artisan('ekkon:task', ['key' => 'Probe/Altlast', '--trigger' => 'manual']);
        $lauf = TaskRun::where('task_key', 'Probe/Altlast')->sole();
        $this->assertSame('skipped', $lauf->status);
        $this->assertStringContainsString('deaktiviert', $lauf->output['skipped']);
    }

    public function test_system_tasks_laufen_auch_wenn_ekkon_als_modul_aus_ist(): void
    {
        Module::where('key', 'ekkon')->update(['is_enabled' => false]);
        $this->registry->modulStatusVergessen();

        $this->assertTrue($this->registry->modulAktiv('Notifications/SendNotifications'));
    }

    public function test_uebersicht_ist_nach_modul_gruppiert(): void
    {
        $admin = User::factory()->create(); // erster Benutzer wird Admin
        $this->modulAus();

        $this->actingAs($admin)->get(route('module.ekkon.index'))
            ->assertOk()
            ->assertSeeInOrder(['System', 'Probemodul', 'Modul deaktiviert', 'Probe/Altlast']);

        $this->get(route('module.ekkon.task.show', ['Probe', 'Altlast']))
            ->assertOk()
            ->assertSee('Das Modul „Probemodul" ist deaktiviert.', false);

        $this->get(route('module.ekkon.notifications.index'))->assertOk();
    }
}
