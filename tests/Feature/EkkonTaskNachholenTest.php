<?php

namespace Tests\Feature;

use App\Ekkon\Models\Notification;
use App\Ekkon\Models\TaskState;
use App\Ekkon\Support\TaskRegistry;
use App\Ekkon\Support\TaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Fixtures\kettentasks\Kette\Nachfolger;
use Tests\Fixtures\kettentasks\Kette\Vorgaenger;
use Tests\TestCase;

/**
 * Logische Kette (folgtAuf): Ein Nachfolger startet erst, wenn sein Vorgänger seit dem eigenen letzten
 * erfolgreichen Lauf durch ist - sonst verschieben und per ekkon:nachholen nachholen (2026-10-01).
 */
class EkkonTaskNachholenTest extends TestCase
{
    use RefreshDatabase;

    private TaskRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ekkon.tasks_enabled' => true]);
        $this->registry = new TaskRegistry;
        $this->registry->addSource(base_path('tests/Fixtures/kettentasks'), 'Tests\\Fixtures\\kettentasks', 'core', TaskRegistry::SYSTEM);
        $this->app->instance(TaskRegistry::class, $this->registry);
        Vorgaenger::$scheitern = false;
        Nachfolger::$laeufe = 0;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function lauf(string $key, string $trigger = 'scheduled')
    {
        return app(TaskRunner::class)->run($this->registry->find($key), $trigger);
    }

    public function test_nachfolger_wird_verschoben_und_nachgeholt(): void
    {
        // Tag 1: beide laufen normal.
        Carbon::setTestNow('2026-10-01 02:00:00');
        $this->assertSame('ok', $this->lauf('Kette/Vorgaenger')->status);
        Carbon::setTestNow('2026-10-01 02:50:00');
        $this->assertSame('ok', $this->lauf('Kette/Nachfolger')->status);

        // Tag 2: Vorgänger scheitert → Nachfolger wird verschoben, nicht gestartet.
        Vorgaenger::$scheitern = true;
        Carbon::setTestNow('2026-10-02 02:00:00');
        $this->assertSame('error', $this->lauf('Kette/Vorgaenger')->status);
        Carbon::setTestNow('2026-10-02 02:50:00');
        $run = $this->lauf('Kette/Nachfolger');
        $this->assertSame('skipped', $run->status);
        $this->assertArrayHasKey('verschoben', $run->output);
        $this->assertSame(1, Nachfolger::$laeufe);
        $state = TaskState::firstWhere('task_key', 'Kette/Nachfolger');
        $this->assertSame('2026-10-02 03:00:00', $state->nachholen_ab->format('Y-m-d H:i:s'));
        $this->assertTrue(Notification::where('meldungsart', TaskRegistry::MELDUNG_VERSCHOBEN)->exists());

        // Vorgänger klappt beim manuellen Neustart; ekkon:nachholen holt den Nachfolger nach.
        Vorgaenger::$scheitern = false;
        Carbon::setTestNow('2026-10-02 02:55:00');
        $this->assertSame('ok', $this->lauf('Kette/Vorgaenger', 'manual')->status);
        Carbon::setTestNow('2026-10-02 03:00:30');
        $this->artisan('ekkon:nachholen')->assertSuccessful();
        $this->assertSame(2, Nachfolger::$laeufe);
        $this->assertNull(TaskState::firstWhere('task_key', 'Kette/Nachfolger')->nachholen_ab);
    }

    public function test_manueller_lauf_prueft_keine_vorbedingung(): void
    {
        Vorgaenger::$scheitern = true;
        $this->lauf('Kette/Vorgaenger');
        $this->assertSame('ok', $this->lauf('Kette/Nachfolger', 'manual')->status);
    }
}
