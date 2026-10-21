<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SchedulerTakt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cron startet `schedule:run` gelegentlich zweimal in derselben Sekunde, wenn er
 * eine verpasste Minute nachholt (RAV, 2026-10-21). Der Core lässt je Minute nur
 * einen Start durch und meldet einen unregelmäßigen oder stehenden Takt an die Glocke.
 */
class SchedulerTaktTest extends TestCase
{
    use RefreshDatabase;

    private function takt(Carbon $ab, int $minuten, array $doppelt = [], array $fehlt = []): void
    {
        for ($i = 0; $i < $minuten; $i++) {
            $t = $ab->copy()->addMinutes($i)->setSecond(1);
            if (in_array($i, $fehlt, true)) {
                continue;
            }
            SchedulerTakt::belegen($t);
            if (in_array($i, $doppelt, true)) {
                SchedulerTakt::belegen($t);
            }
        }
    }

    public function test_je_minute_kommt_nur_ein_start_durch(): void
    {
        $t = Carbon::parse('2026-10-21 12:01:15');

        $this->assertTrue(SchedulerTakt::belegen($t));
        $this->assertFalse(SchedulerTakt::belegen($t));
        $this->assertFalse(SchedulerTakt::belegen($t->copy()->setSecond(40)));
        $this->assertTrue(SchedulerTakt::belegen($t->copy()->addMinute()));

        $zeile = DB::table('scheduler_takt')->where('minute', '2026-10-21 12:01')->first();
        $this->assertSame(2, (int) $zeile->doppelt);
        $this->assertSame(15, (int) $zeile->sekunde);
    }

    public function test_gleichmaessiger_takt_ist_in_ordnung(): void
    {
        $jetzt = Carbon::parse('2026-10-21 14:00:30');
        Carbon::setTestNow($jetzt);
        $this->takt($jetzt->copy()->subMinutes(90)->startOfMinute(), 90);

        $a = SchedulerTakt::auswertung($jetzt);

        $this->assertSame(60, $a['starts']);
        $this->assertSame(60, $a['erwartet']);
        $this->assertSame(0, $a['fehlend']);
        $this->assertFalse($a['unregelmaessig']);
        $this->assertFalse($a['steht']);
        $this->assertSame([], SchedulerTakt::hinweise('/x'));
    }

    public function test_nachholen_ergibt_doppelstart_und_ausfall(): void
    {
        $jetzt = Carbon::parse('2026-10-21 14:00:30');
        Carbon::setTestNow($jetzt);
        // Minute 70 fällt aus, Minute 71 startet doppelt – das Muster vom RAV-Server.
        $this->takt($jetzt->copy()->subMinutes(90)->startOfMinute(), 90, doppelt: [71], fehlt: [70]);

        $a = SchedulerTakt::auswertung($jetzt);

        $this->assertSame(1, $a['doppelt']);
        $this->assertSame(1, $a['fehlend']);
        $this->assertTrue($a['unregelmaessig']);

        $hinweise = SchedulerTakt::hinweise('/x');
        $this->assertCount(1, $hinweise);
        $this->assertStringContainsString('1 Doppelstart(s), 1 ausgefallene Minute(n)', $hinweise[0]->titel);
    }

    public function test_einzelne_ausgefallene_minute_bleibt_still(): void
    {
        $jetzt = Carbon::parse('2026-10-21 14:00:30');
        Carbon::setTestNow($jetzt);
        $this->takt($jetzt->copy()->subMinutes(90)->startOfMinute(), 90, fehlt: [50]);

        $this->assertFalse(SchedulerTakt::auswertung($jetzt)['unregelmaessig']);
    }

    public function test_neuer_takt_erwartet_nur_minuten_ab_erstem_start(): void
    {
        $jetzt = Carbon::parse('2026-10-21 14:00:30');
        Carbon::setTestNow($jetzt);
        $this->takt($jetzt->copy()->subMinutes(10)->startOfMinute(), 10);

        $a = SchedulerTakt::auswertung($jetzt);

        $this->assertSame(10, $a['erwartet']);
        $this->assertSame(0, $a['fehlend']);
        $this->assertFalse($a['unregelmaessig']);
    }

    public function test_stehender_scheduler_wird_gemeldet_aber_nicht_ohne_jeden_takt(): void
    {
        $jetzt = Carbon::parse('2026-10-21 14:00:30');
        Carbon::setTestNow($jetzt);

        $this->assertFalse(SchedulerTakt::auswertung($jetzt)['steht']);

        $this->takt($jetzt->copy()->subMinutes(30)->startOfMinute(), 20);
        $a = SchedulerTakt::auswertung($jetzt);

        $this->assertTrue($a['steht']);
        $this->assertSame(11, $a['still_minuten']); // letzter Start 13:49
        $this->assertStringContainsString('nicht mehr', SchedulerTakt::hinweise('/x')[0]->titel);
    }

    public function test_altes_protokoll_wird_zur_vollen_stunde_entfernt(): void
    {
        SchedulerTakt::belegen(Carbon::parse('2026-10-18 09:13:02'));
        SchedulerTakt::belegen(Carbon::parse('2026-10-21 12:00:05'));

        $this->assertSame(['2026-10-21 12:00'], DB::table('scheduler_takt')->pluck('minute')->all());
    }

    public function test_glocke_und_taskseite_zeigen_den_takt_fuer_admins(): void
    {
        $jetzt = Carbon::now()->setSecond(30);
        Carbon::setTestNow($jetzt);
        $this->takt($jetzt->copy()->subMinutes(90)->startOfMinute(), 90, doppelt: [71], fehlt: [70]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cron läuft unregelmäßig');

        $this->actingAs($admin)
            ->get(route('module.ekkon.index'))
            ->assertOk()
            ->assertSee('id="cron-takt"', false)
            ->assertSee('1 Doppelstart(s) abgewiesen');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertDontSee('Cron läuft unregelmäßig');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
