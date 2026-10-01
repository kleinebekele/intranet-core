<?php

namespace Tests\Feature;

use App\Ekkon\Models\Notification;
use App\Ekkon\Models\TaskRun;
use App\Ekkon\Models\WebhookQuelle;
use App\Ekkon\Support\TaskRegistry;
use App\Ekkon\Support\TaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Externer Wächter: Lebenszeichen-Abfrage und Gegenrichtung (2026-10-01). */
class EkkonLebenszeichenTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function quelle(): WebhookQuelle
    {
        return WebhookQuelle::create(['name' => 'Wächter Test', 'schluessel' => WebhookQuelle::neuerSchluessel(), 'aktiv' => true]);
    }

    public function test_lebenszeichen_meldet_scheduler_alter(): void
    {
        $q = $this->quelle();
        TaskRun::create(['task_key' => 'Demo/Ping', 'trigger' => 'scheduled', 'status' => 'ok', 'started_at' => now()->subMinutes(3), 'finished_at' => now()->subMinutes(3)]);

        $this->getJson($q->lebenszeichenUrl())->assertOk()->assertJson(['ok' => true, 'scheduler_alter_min' => 3]);
        $this->assertNotNull($q->fresh()->lebenszeichen_am);

        Carbon::setTestNow(now()->addMinutes(20));
        $this->getJson($q->lebenszeichenUrl())->assertOk()->assertJson(['ok' => false]);
        $this->get('/webhooks/ekkon/falsch/lebenszeichen')->assertNotFound();
    }

    public function test_schweigender_waechter_wird_einmal_gemeldet(): void
    {
        config(['ekkon.tasks_enabled' => true]);
        $q = $this->quelle();
        $q->forceFill(['lebenszeichen_am' => now()->subMinutes(30)])->save();
        $task = app(TaskRegistry::class)->find('Waechter/Lebenszeichen');

        app(TaskRunner::class)->run($task, 'manual');
        app(TaskRunner::class)->run($task, 'manual');
        $this->assertSame(1, Notification::where('meldungsart', 'waechter-still')->count());

        $q->forceFill(['lebenszeichen_am' => now()])->save();
        app(TaskRunner::class)->run($task, 'manual');
        $this->assertSame(2, Notification::where('meldungsart', 'waechter-still')->count());
        $this->assertNull($q->fresh()->waechter_alarm_am);
    }
}
