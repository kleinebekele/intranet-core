<?php

namespace App\Ekkon\Console;

use App\Ekkon\Models\TaskState;
use App\Ekkon\Support\TaskRegistry;
use App\Ekkon\Support\TaskRunner;
use Illuminate\Console\Command;

/**
 * Fällige verschobene Läufe starten (2026-10-01). Ein Task, dessen Vorbedingung fehlte (Wawi nicht
 * erreichbar, Vorgänger der Kette nicht durch), trägt in ekkon_task_states ein nachholen_ab. Dieser
 * Befehl läuft jede Minute und startet ihn ab dann - auch außerhalb seiner Cron-Zeit. Fehlt die
 * Vorbedingung weiter, verschiebt der Runner erneut.
 */
class NachholenCommand extends Command
{
    protected $signature = 'ekkon:nachholen';

    protected $description = 'Verschobene Ekkon-Tasks nachholen (Wawi wieder da / Vorgänger durch).';

    public function handle(TaskRegistry $registry, TaskRunner $runner): int
    {
        if (! config('ekkon.tasks_enabled')) {
            return self::SUCCESS;
        }

        $faellig = TaskState::query()->whereNotNull('nachholen_ab')->where('nachholen_ab', '<=', now())->orderBy('nachholen_ab')->get();
        foreach ($faellig as $state) {
            $task = $registry->find((string) $state->task_key);
            if ($task === null || ! $registry->modulAktiv($task->key())) {
                $state->fill(['nachholen_ab' => null, 'nachholen_seit' => null, 'nachholen_grund' => null])->save();

                continue;
            }
            $run = $runner->run($task, 'nachholen');
            if ($run === null) {
                // Pausiert o. Ä.: der Runner hat nichts gestartet - Nachholen beenden, sonst jede Minute wieder.
                $state->refresh()->fill(['nachholen_ab' => null, 'nachholen_seit' => null, 'nachholen_grund' => null])->save();
            }
            $this->line($task->key().': '.($run?->status ?? 'nicht gestartet'));
        }

        return self::SUCCESS;
    }
}
