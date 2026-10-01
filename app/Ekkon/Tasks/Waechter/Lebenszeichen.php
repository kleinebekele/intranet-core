<?php

namespace App\Ekkon\Tasks\Waechter;

use App\Ekkon\Models\WebhookQuelle;
use App\Ekkon\Services\Benachrichtiger;
use App\Ekkon\Tasks\EkkonTask;

/**
 * Gegenrichtung zum externen Wächter (2026-10-01): Ein Windows-Skript fragt alle paar Minuten
 * GET /webhooks/ekkon/{schluessel}/lebenszeichen ab. Bleibt es länger als die Frist aus (Wächter-
 * Rechner aus, Skript kaputt), meldet dieser Task das einmal - und einmal, wenn es wieder kommt.
 * Nur Quellen, die sich in den letzten 7 Tagen überhaupt gemeldet haben, gelten als Wächter.
 */
class Lebenszeichen extends EkkonTask
{
    public string $category = 'System';

    public string $description = 'Meldet, wenn der externe Wächter (Windows-Skript) sich nicht mehr meldet.';

    /** @var array<string, string> */
    public array $meldungsarten = [
        'waechter-still' => 'Der externe Wächter meldet sich nicht mehr / wieder (System)',
    ];

    public array $einstellungen = [
        'frist_minuten' => ['typ' => 'zahl', 'label' => 'Alarm, wenn der Wächter sich so viele Minuten nicht gemeldet hat', 'standard' => 20],
    ];

    public bool $automatischPausieren = false;

    public function schedule(): string
    {
        return '*/5 * * * *';
    }

    public function run(): array
    {
        $frist = max(5, (int) $this->einstellung('frist_minuten'));
        $grenze = now()->subMinutes($frist);
        $zahlen = ['waechter' => 0, 'still' => 0, 'gemeldet' => 0, 'wieder_da' => 0];

        $quellen = WebhookQuelle::query()->whereNotNull('lebenszeichen_am')->where('lebenszeichen_am', '>=', now()->subDays(7))->get();
        foreach ($quellen as $q) {
            $zahlen['waechter']++;
            $still = $q->lebenszeichen_am < $grenze;
            if ($still) {
                $zahlen['still']++;
                if ($q->waechter_alarm_am === null) {
                    (new Benachrichtiger())->benachrichtige(
                        'waechter-still',
                        'Wächter „'.$q->name.'" meldet sich nicht mehr',
                        'Letztes Lebenszeichen: '.\Illuminate\Support\Carbon::parse($q->lebenszeichen_am)->format('d.m.Y H:i')
                            ." (Frist {$frist} Minuten). Läuft der Wächter-Rechner und seine geplante Aufgabe?",
                        ['quelle' => $q->name],
                        'waechter-still:'.$q->id.':'.now()->format('Y-m-d-H'),
                    );
                    $q->forceFill(['waechter_alarm_am' => now()])->save();
                    $zahlen['gemeldet']++;
                }
            } elseif ($q->waechter_alarm_am !== null) {
                (new Benachrichtiger())->benachrichtige(
                    'waechter-still',
                    'Wächter „'.$q->name.'" meldet sich wieder',
                    'Seit '.\Illuminate\Support\Carbon::parse($q->lebenszeichen_am)->format('d.m.Y H:i').' kommen wieder Lebenszeichen.',
                    ['quelle' => $q->name],
                    'waechter-wieder:'.$q->id.':'.now()->format('Y-m-d-H-i'),
                );
                $q->forceFill(['waechter_alarm_am' => null])->save();
                $zahlen['wieder_da']++;
            }
        }
        $this->msg("{$zahlen['waechter']} Wächter, {$zahlen['still']} still.");

        return $zahlen;
    }
}
