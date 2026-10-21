<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Takt des Laravel-Schedulers – Sperre und Protokoll in einem.
 *
 * Hintergrund (RAV-Server, 2026-10-21): Eine cron-Runde dauerte dort 67 statt
 * 60 Sekunden, die Startzeit wanderte jede Minute weiter nach hinten. Etwa alle
 * neun Minuten fiel dadurch eine Minute aus, und cron holte sie in der nächsten
 * Runde nach – jeder Minuten-Job startete dann zweimal in derselben Sekunde.
 *
 * Deshalb belegt jeder `schedule:run` seine Minute in `scheduler_takt`
 * (Primärschlüssel = Minute). Ein zweiter Start derselben Minute bekommt den
 * Schlüssel nicht, zählt `doppelt` hoch und beendet sich. Aus derselben Tabelle
 * liest die Glocke, ob Cron unregelmäßig läuft oder ganz steht.
 */
class SchedulerTakt
{
    /** Zeitraum der Auswertung für Glocke und Übersicht, in Minuten. */
    public const FENSTER = 60;

    /** Ab so vielen fehlenden Minuten im Fenster gilt der Takt als unregelmäßig (eine verzeiht z. B. einen Neustart). */
    public const FEHLEND_AB = 2;

    /** So lange ohne Start gilt der Scheduler als stehend. */
    public const STILL_AB_MINUTEN = 5;

    /** Aufbewahrung des Protokolls. */
    public const TAGE = 2;

    /**
     * Die laufende Minute für diesen `schedule:run` belegen.
     *
     * false = diese Minute hat schon ein anderer Start – der Aufrufer soll sich
     * beenden. Jeder andere Fehler (Tabelle fehlt noch, weil der Deploy die
     * Migration erst gleich ausführt; Datenbank weg) gibt true: lieber ein
     * Doppellauf wie bisher als ein Scheduler, der gar nicht mehr läuft.
     */
    public static function belegen(?CarbonInterface $jetzt = null): bool
    {
        $jetzt ??= now();
        $minute = $jetzt->format('Y-m-d H:i');

        try {
            DB::table('scheduler_takt')->insert([
                'minute' => $minute,
                'gestartet_am' => $jetzt,
                'sekunde' => (int) $jetzt->format('s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            DB::table('scheduler_takt')->where('minute', $minute)->increment('doppelt');

            return false;
        } catch (Throwable) {
            return true;
        }

        if ((int) $jetzt->format('i') === 0) {
            try {
                DB::table('scheduler_takt')
                    ->where('minute', '<', $jetzt->copy()->subDays(self::TAGE)->format('Y-m-d H:i'))
                    ->delete();
            } catch (Throwable) {
                // Aufräumen ist Nebensache – der Lauf geht vor.
            }
        }

        return true;
    }

    /**
     * Wie lief Cron in den letzten {@see FENSTER} abgeschlossenen Minuten?
     *
     * @return array{starts: int, erwartet: int, fehlend: int, doppelt: int, sekunde_max: int|null,
     *               letzter: string|null, still_minuten: int|null, unregelmaessig: bool, steht: bool}
     */
    public static function auswertung(?CarbonInterface $jetzt = null): array
    {
        $jetzt ??= now();
        $bis = $jetzt->copy()->startOfMinute(); // die laufende Minute zählt noch nicht
        $von = $bis->copy()->subMinutes(self::FENSTER);
        $fmt = fn (CarbonInterface $t): string => $t->format('Y-m-d H:i');

        $fenster = DB::table('scheduler_takt')
            ->where('minute', '>=', $fmt($von))
            ->where('minute', '<', $fmt($bis))
            ->selectRaw('count(*) as starts, coalesce(sum(doppelt), 0) as doppelt, max(sekunde) as sekunde_max, min(minute) as erste')
            ->first();

        $starts = (int) $fenster->starts;

        // Erwartet wird jede Minute des Fensters – außer der Takt beginnt erst
        // darin (frische Installation, Tabelle neu): dann ab dem ersten Start.
        $erwartet = 0;
        if ($starts > 0) {
            $davor = DB::table('scheduler_takt')->where('minute', '<', $fmt($von))->exists();
            $erwartet = $davor
                ? self::FENSTER
                : (int) Carbon::createFromFormat('Y-m-d H:i', $fenster->erste, $jetzt->getTimezone())->diffInMinutes($bis);
        }
        $fehlend = max(0, $erwartet - $starts);

        $letzter = DB::table('scheduler_takt')->max('minute');
        $stillMinuten = null;
        $steht = false;
        if ($letzter !== null) {
            $stillMinuten = (int) Carbon::createFromFormat('Y-m-d H:i', $letzter, $jetzt->getTimezone())
                ->diffInMinutes($jetzt->copy()->startOfMinute());
            // Nur melden, wenn der Scheduler hier überhaupt läuft (Takt aus den
            // letzten 24 h) – eine Entwicklungsumgebung ohne Cron bleibt still.
            $steht = $stillMinuten > self::STILL_AB_MINUTEN && $stillMinuten < 24 * 60;
        }

        $doppelt = (int) $fenster->doppelt;

        return [
            'starts' => $starts,
            'erwartet' => $erwartet,
            'fehlend' => $fehlend,
            'doppelt' => $doppelt,
            'sekunde_max' => $fenster->sekunde_max === null ? null : (int) $fenster->sekunde_max,
            'letzter' => $letzter,
            'still_minuten' => $stillMinuten,
            'unregelmaessig' => ! $steht && ($doppelt > 0 || $fehlend >= self::FEHLEND_AB),
            'steht' => $steht,
        ];
    }

    /**
     * Hinweise für die Glocke (nur Admins).
     *
     * @return array<int, Hinweis>
     */
    public static function hinweise(string $url): array
    {
        $a = self::auswertung();
        $quelle = 'System · Cron';

        if ($a['steht']) {
            return [new Hinweis(
                'Cron startet den Scheduler nicht mehr (letzter Start vor '.$a['still_minuten'].' Min.) – keine Tasks, keine Mails',
                $url,
                $quelle,
            )];
        }

        if ($a['unregelmaessig']) {
            return [new Hinweis(
                'Cron läuft unregelmäßig: '.$a['doppelt'].' Doppelstart(s), '.$a['fehlend'].' ausgefallene Minute(n) in der letzten Stunde',
                $url,
                $quelle,
            )];
        }

        return [];
    }
}
