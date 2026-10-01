<?php

namespace App\Ekkon\Support;

use App\Ekkon\Ekkon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Ekkon\Models\TaskRun;
use App\Ekkon\Services\Benachrichtiger;
use App\Ekkon\Models\TaskState;
use App\Ekkon\Tasks\EkkonTask;
use Throwable;

/**
 * Führt einen Task aus: Überlappungsschutz per Cache-Lock, Lauf-Historie
 * mit Dauer/Status/Nachrichten/Debug/JSON-Ergebnis in ekkon_task_runs.
 *
 * Selbststeuerung: Hat der Task per setInterval() einen nächsten Zeitpunkt
 * bestimmt (ekkon_task_states), werden geplante Läufe davor lautlos
 * übersprungen – run() liefert dann null und es entsteht KEIN Eintrag.
 */
class TaskRunner
{
    /** Debug-Daten nur für die jüngsten N Läufe je Task behalten. */
    private const KEEP_DEBUG_RUNS = 10;

    public function run(EkkonTask $task, string $trigger = 'scheduled'): ?TaskRun
    {
        // Sicherheitsschalter: Auf Umgebungen ohne EKKON_TASKS_ENABLED=true
        // (z. B. lokale Entwicklung) läuft NIE ein Task – auch nicht manuell.
        if (! config('ekkon.tasks_enabled')) {
            return TaskRun::create([
                'task_key' => $task->key(),
                'trigger' => $trigger,
                'status' => 'skipped',
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 0,
                'output' => ['skipped' => 'Ekkon-Tasks sind auf dieser Umgebung deaktiviert (EKKON_TASKS_ENABLED).'],
            ]);
        }

        // Modul in der Modulverwaltung deaktiviert: Dann läuft NICHTS davon – auch
        // nicht „jetzt ausführen". Anders als die Pause ist das keine Entscheidung
        // über den Zeitplan, sondern über das ganze Modul; ein Task, der trotzdem
        // in ein Fremdsystem schreibt, wäre genau die Überraschung, die das
        // Abschalten verhindern soll. Geplant lautlos, von Hand mit Begründung.
        if (! app(TaskRegistry::class)->modulAktiv($task->key())) {
            if ($trigger === 'scheduled') {
                return null;
            }

            return TaskRun::create([
                'task_key' => $task->key(),
                'trigger' => $trigger,
                'status' => 'skipped',
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 0,
                'output' => ['skipped' => 'Das Modul dieses Tasks ist in der Modulverwaltung deaktiviert.'],
            ]);
        }

        // Pausiert (Dashboard) oder schlummernd (setInterval)? Geplante Läufe
        // werden lautlos übersprungen; nur manuelle Läufe dürfen durch.
        $state = TaskState::firstWhere('task_key', $task->key());

        // 'nachholen' = verschobener Lauf (ekkon:nachholen) - verhält sich wie ein geplanter.
        $geplant = in_array($trigger, ['scheduled', 'nachholen'], true);

        if ($geplant && $state !== null && ! $state->enabled) {
            return null;
        }

        if ($trigger === 'scheduled' && $state?->next_run_at?->isFuture()) {
            return null;
        }

        // Vorbedingungen (2026-10-01): Wawi erreichbar, Vorgänger der Kette durch? Sonst nicht
        // starten, sondern verschieben und nachholen. Manuelle Läufe prüfen nichts.
        if ($geplant && ($task->brauchtWawi || $task->folgtAuf !== [])) {
            $grund = $this->vorbedingungFehlt($task);
            if ($grund !== null) {
                return $this->verschieben($task, $trigger, $grund);
            }
        }
        $warVerschoben = $state?->nachholen_seit !== null;

        $lock = Cache::lock('ekkon-task-'.$task->key(), $task->lockSeconds());

        if (! $lock->get()) {
            return TaskRun::create([
                'task_key' => $task->key(),
                'trigger' => $trigger,
                'status' => 'skipped',
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 0,
                'output' => ['skipped' => 'Task läuft bereits (Überlappungsschutz).'],
            ]);
        }

        $run = TaskRun::create([
            'task_key' => $task->key(),
            'trigger' => $trigger,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $task->resetChannels();
        $start = hrtime(true);

        try {
            $output = $task->run();
            $status = 'ok';
        } catch (Throwable $e) {
            $status = 'error';
            $output = [
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'at' => $e->getFile().':'.$e->getLine(),
            ];
            report($e);
        } finally {
            $lock->release();
        }

        $dauerMs = intdiv(hrtime(true) - $start, 1_000_000);

        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'duration_ms' => $dauerMs,
            'output' => $output,
            'messages' => $task->messages() ?: null,
            'debug' => $task->debugData() ?: null,
        ]);

        $this->laufzeitPruefen($task, $dauerMs, $status);

        if ($warVerschoben) {
            $this->nachgeholt($task, $status, $trigger);
        }

        // Vom Task bestimmten nächsten Lauf merken (auch nach manuellen Läufen).
        if ($task->interval() !== null) {
            TaskState::updateOrCreate(
                ['task_key' => $task->key()],
                ['next_run_at' => $task->interval()],
            );
        }

        $this->pruneDebug($task->key());

        return $run;
    }

    /**
     * Hat der Lauf ungewöhnlich lange gedauert? Dann sagt das System Bescheid.
     *
     * ── Warum das nötig wurde (2026-08-05) ──────────────────────────────
     * Aftersales/JourneyUpdate lief über Stunden. Das Dashboard hat die Dauer
     * brav rot eingefärbt – gemerkt hat es trotzdem niemand, weil dort nur
     * hinschaut, wer ohnehin schon einen Verdacht hat. Aufgefallen ist es erst,
     * als die produktive Wawi lahm wurde und der SQL-Dienst neu gestartet
     * werden musste. Eine Überwachung, die nicht von selbst meldet, ist keine.
     *
     * ⚠️ Diese Prüfung darf den Lauf niemals kippen. Der Task ist an dieser
     * Stelle fertig, sein Ergebnis steht schon in der Datenbank – eine
     * scheiternde Benachrichtigung würde daraus nachträglich einen Fehlschlag
     * machen und im schlimmsten Fall den nächsten Lauf gleich mit verhindern.
     */
    private function laufzeitPruefen(EkkonTask $task, int $dauerMs, string $status): void
    {
        $grenzeSekunden = $task->warnungAbSekunden;

        if ($grenzeSekunden <= 0 || $dauerMs < $grenzeSekunden * 1000) {
            return;
        }

        // ── Zuerst stilllegen, dann melden ──────────────────────────────
        //
        // Emanuel 2026-08-05: "Wenn ich Samstags Abend eine Mail bekommen
        // würde, dass da was lange dauert, dann kann es nicht sein, dass der
        // Task das komplette Wochenende irgendwas blockiert."
        //
        // Die Reihenfolge ist Absicht: Das Stilllegen ist die Schutzmassnahme,
        // die Meldung nur die Information darüber. Scheitert der Versand, ist
        // der Task trotzdem gestoppt - andersherum liefe er weiter, während
        // eine schöne Mail unterwegs ist.
        //
        // Bewusst UNABHÄNGIG vom Auslöser: Auch ein von Hand gestarteter Lauf,
        // der aus dem Ruder läuft, ist ein Grund zum Anhalten - der nächste
        // geplante würde es genauso tun.
        $pausiert = false;

        if ($task->automatischPausieren) {
            try {
                TaskState::updateOrCreate(
                    ['task_key' => $task->key()],
                    ['enabled' => false],
                );
                $pausiert = true;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $hinweis = $pausiert
            ? "Der Task wurde deshalb PAUSIERT und läuft nicht mehr von selbst.\n"
                ."Im Dashboard lässt er sich wieder aktivieren - bitte erst, wenn die Ursache "
                ."klar ist.\n\n"
            : "Der Task läuft weiter (automatisches Pausieren ist für ihn abgeschaltet).\n\n";

        try {
            (new Benachrichtiger())->benachrichtige(
                TaskRegistry::MELDUNG_LAUFZEIT,
                ($pausiert ? 'Task pausiert – lief zu lange: ' : 'Task lief ungewöhnlich lange: ').$task->key(),
                'Der Lauf hat '.round($dauerMs / 60000, 1).' Minuten gebraucht (Warnschwelle: '
                    .round($grenzeSekunden / 60, 1)." Minuten, Status: {$status}).\n\n"
                    .$hinweis
                    ."Hintergrund: Ein Lauf, der länger braucht als sein Abstand zum nächsten, "
                    ."staut sich auf - und wenn er die Überlappungssperre überdauert, laufen "
                    ."mehrere Läufe gleichzeitig auf derselben Datenbank.\n\n"
                    .'Die Zeitmessung je Abschnitt steht in den Lauf-Details unter "tempo".',
                [
                    'task' => $task->key(),
                    'dauer_ms' => $dauerMs,
                    'grenze_s' => $grenzeSekunden,
                    'status' => $status,
                    'pausiert' => $pausiert,
                ],
                // Einmal je Task und Stunde: Ein 10-Minuten-Task würde sonst
                // dieselbe Meldung sechsmal pro Stunde absetzen. Stündlich
                // bleibt sichtbar, dass es weitergeht, ohne zu fluten.
                'task-laufzeit:'.$task->key().':'.now()->format('Y-m-d-H'),
                $task->key(),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Was hält einen geplanten Lauf auf? null = nichts. Prüft die Wawi-Verbindung (brauchtWawi) und
     * die Vorgänger der Kette (folgtAuf): jeder muss nach dem letzten erfolgreichen Lauf dieses
     * Tasks selbst erfolgreich gewesen sein. Pausierte oder unbekannte Vorgänger bremsen nicht.
     */
    private function vorbedingungFehlt(EkkonTask $task): ?string
    {
        if ($task->brauchtWawi && Ekkon::mssqlKonfiguriert()) {
            $verbindung = Ekkon::mssqlConnection();
            try {
                DB::connection($verbindung)->select('SELECT 1 AS ok');
            } catch (Throwable $e) {
                DB::purge($verbindung);

                return 'Wawi nicht erreichbar: '.mb_substr($e->getMessage(), 0, 300);
            }
        }

        $registry = app(TaskRegistry::class);
        $eigenOk = TaskRun::query()->where('task_key', $task->key())->where('status', 'ok')->max('started_at');
        foreach ($task->folgtAuf as $vorgaenger) {
            if ($registry->find($vorgaenger) === null || ! $registry->modulAktiv($vorgaenger)) {
                continue;
            }
            $vState = TaskState::firstWhere('task_key', $vorgaenger);
            if ($vState !== null && ! $vState->enabled) {
                continue;
            }
            if ($vState?->nachholen_ab !== null) {
                return "Vorgänger {$vorgaenger} ist selbst verschoben ({$vState->nachholen_grund}).";
            }
            $vOk = TaskRun::query()->where('task_key', $vorgaenger)->where('status', 'ok')->max('finished_at');
            if ($vOk === null) {
                return "Vorgänger {$vorgaenger} war noch nie erfolgreich.";
            }
            if ($eigenOk !== null && (string) $vOk <= (string) $eigenOk) {
                return "Vorgänger {$vorgaenger} war seit dem letzten eigenen Lauf nicht erfolgreich (zuletzt ok {$vOk}).";
            }
        }

        return null;
    }

    /**
     * Lauf nicht starten, sondern in nachholenMinuten erneut versuchen - es sei denn, der nächste
     * reguläre Lauf kommt vorher, dann übernimmt der. Die erste Verschiebung wird gemeldet.
     */
    private function verschieben(EkkonTask $task, string $trigger, string $grund): TaskRun
    {
        $jetzt = now();
        $ab = $jetzt->copy()->addMinutes(max(1, $task->nachholenMinuten))->startOfMinute();
        $regulaer = $task->nextRunDate();
        $state = TaskState::firstOrNew(['task_key' => $task->key()]);
        $erstes = $state->nachholen_seit === null;

        if ($regulaer <= $ab) {
            $state->fill(['nachholen_ab' => null, 'nachholen_seit' => null, 'nachholen_grund' => null])->save();
            $hinweis = 'Kein Nachholen: der nächste reguläre Lauf ('.$regulaer->format('d.m. H:i').') kommt vorher.';
        } else {
            $state->fill([
                'nachholen_ab' => $ab,
                'nachholen_seit' => $state->nachholen_seit ?? $jetzt,
                'nachholen_grund' => mb_substr($grund, 0, 500),
            ])->save();
            $hinweis = 'Neuer Versuch ab '.$ab->format('H:i').' (alle '.$task->nachholenMinuten.' Minuten bis zum nächsten regulären Lauf '.$regulaer->format('d.m. H:i').').';
        }

        $run = TaskRun::create([
            'task_key' => $task->key(),
            'trigger' => $trigger,
            'status' => 'skipped',
            'started_at' => $jetzt,
            'finished_at' => $jetzt,
            'duration_ms' => 0,
            'output' => ['verschoben' => $grund, 'hinweis' => $hinweis],
        ]);

        if ($erstes) {
            try {
                (new Benachrichtiger())->benachrichtige(
                    TaskRegistry::MELDUNG_VERSCHOBEN,
                    'Task verschoben: '.$task->key(),
                    $grund."

".$hinweis."

Sobald er nachgeholt ist, kommt eine zweite Meldung.",
                    ['task' => $task->key(), 'grund' => $grund],
                    'task-verschoben:'.$task->key().':'.$jetzt->format('Y-m-d-H'),
                    $task->key(),
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $run;
    }

    /** Ein verschobener Task ist (egal wie) gelaufen: Nachholen beenden und melden. */
    private function nachgeholt(EkkonTask $task, string $status, string $trigger): void
    {
        try {
            $state = TaskState::firstWhere('task_key', $task->key());
            $seit = $state?->nachholen_seit;
            $state?->fill(['nachholen_ab' => null, 'nachholen_seit' => null, 'nachholen_grund' => null])->save();
            (new Benachrichtiger())->benachrichtige(
                TaskRegistry::MELDUNG_VERSCHOBEN,
                ($status === 'ok' ? 'Task nachgeholt: ' : 'Task nachgeholt, aber mit Fehler: ').$task->key(),
                'Verschoben seit '.($seit?->format('d.m.Y H:i') ?? '?').', jetzt gelaufen ('.($trigger === 'nachholen' ? 'Nachholversuch' : 'regulär').', Status: '.$status.').',
                ['task' => $task->key(), 'status' => $status],
                'task-nachgeholt:'.$task->key().':'.now()->format('Y-m-d-H-i'),
                $task->key(),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Debug-Spalte älterer Läufe leeren – die Zeilen selbst bleiben erhalten. */
    private function pruneDebug(string $taskKey): void
    {
        $keepIds = TaskRun::query()
            ->where('task_key', $taskKey)
            ->whereNotNull('debug')
            ->latest('id')
            ->limit(self::KEEP_DEBUG_RUNS)
            ->pluck('id');

        TaskRun::query()
            ->where('task_key', $taskKey)
            ->whereNotNull('debug')
            ->whereNotIn('id', $keepIds)
            ->update(['debug' => null]);
    }
}
