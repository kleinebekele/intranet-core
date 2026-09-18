<?php

namespace App\Ekkon\Support;

use App\Ekkon\Models\NotificationRoute;
use App\Ekkon\Models\TaskRun;
use App\Ekkon\Models\TaskSetting;
use App\Ekkon\Models\TaskState;

/**
 * Was ein Modul in Ekkon hinterlässt: Pausen-Schalter, Task-Einstellungen,
 * Lauf-Historie und Benachrichtigungs-Routen seiner Tasks.
 *
 * Der ModuleUninstaller fragt hier nach, wenn ein Modul MIT Daten entfernt
 * wird – sonst blieben diese Zeilen für immer liegen, und ein später wieder
 * installiertes Modul fände z. B. seinen Task still pausiert vor.
 *
 * Geht nur, solange das Paket noch installiert ist: Welche Tasks zu einem Modul
 * gehören, weiß allein die Registry.
 */
class ModulSpuren
{
    public function __construct(private TaskRegistry $registry) {}

    /** @return array{tasks: string[], meldungsarten: string[], zeilen: int} */
    public function vorschau(string $modulKey): array
    {
        [$tasks, $arten] = $this->tasksUndMeldungsarten($modulKey);

        return [
            'tasks' => $tasks,
            'meldungsarten' => $arten,
            'zeilen' => $tasks === [] ? 0
                : TaskState::whereIn('task_key', $tasks)->count()
                    + TaskSetting::whereIn('task_key', $tasks)->count()
                    + TaskRun::whereIn('task_key', $tasks)->count()
                    + NotificationRoute::whereIn('meldungsart', $arten ?: ['__keine__'])->count(),
        ];
    }

    /** @return int gelöschte Zeilen */
    public function entfernen(string $modulKey): int
    {
        [$tasks, $arten] = $this->tasksUndMeldungsarten($modulKey);

        if ($tasks === []) {
            return 0;
        }

        return TaskState::whereIn('task_key', $tasks)->delete()
            + TaskSetting::whereIn('task_key', $tasks)->delete()
            + TaskRun::whereIn('task_key', $tasks)->delete()
            + NotificationRoute::whereIn('meldungsart', $arten ?: ['__keine__'])->delete();
    }

    /** @return array{0: string[], 1: string[]} */
    private function tasksUndMeldungsarten(string $modulKey): array
    {
        $tasks = [];
        $arten = [];

        // Meldet ein zweites Paket denselben Task-Key an (Umzug zwischen Modulen,
        // beide noch installiert), gehören die Spuren nicht mehr diesem Modul allein –
        // Finger weg, sonst verlöre der Nachfolger Pause, Einstellungen und Historie.
        $geteilt = array_column($this->registry->kollisionen(), 'key');

        foreach ($this->registry->all() as $key => $task) {
            if ($this->registry->modulFuer($key) === $modulKey && ! in_array($key, $geteilt, true)) {
                $tasks[] = $key;
                $arten = [...$arten, ...array_keys($task->meldungsarten)];
            }
        }

        return [$tasks, array_values(array_unique($arten))];
    }
}
