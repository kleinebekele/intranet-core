<?php

namespace App\Ekkon\Console;

use App\Ekkon\Services\TeamsLauscher;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dauerdienst: holt alle paar Sekunden neue Teams-Nachrichten an das Bot-Konto.
 *
 * Läuft unter systemd (Restart=always) – siehe app/Ekkon/CLAUDE.md. Nach einem
 * Deploy muss der Dienst neu gestartet werden, sonst läuft alter Code weiter.
 * Deshalb beendet er sich von selbst nach --max-laufzeit Sekunden (systemd
 * startet ihn neu); so greift ein Deploy spätestens nach dieser Zeit.
 *
 * Fehler (Microsoft nicht erreichbar, Token abgelaufen) werden geloggt, die
 * Schleife läuft weiter – mit wachsender Pause, damit ein Ausfall nicht jede
 * Sekunde gegen die Wand hämmert.
 */
class TeamsLauschenCommand extends Command
{
    protected $signature = 'teams:lauschen
        {--intervall=5 : Sekunden zwischen zwei Runden}
        {--einmal : nur eine Runde, dann Ende (zum Testen)}
        {--max-laufzeit=3600 : nach so vielen Sekunden beenden (systemd startet neu)}';

    protected $description = 'Holt fortlaufend neue Teams-Nachrichten an das verbundene Microsoft-Konto ab (Dauerdienst)';

    private bool $beenden = false;

    public function handle(TeamsLauscher $lauscher): int
    {
        $intervall = max(1, (int) $this->option('intervall'));
        $maxLaufzeit = max(10, (int) $this->option('max-laufzeit'));
        $start = time();
        $pause = $intervall;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, function () { $this->beenden = true; });
            }
        }

        $this->info('Teams-Lauscher gestartet (alle '.$intervall.' s, max. '.$maxLaufzeit.' s).');

        while (! $this->beenden) {
            try {
                $r = $lauscher->runde();
                if ($r['neu'] > 0 || $r['fehler'] !== []) {
                    $this->line(now()->format('H:i:s').' '.$r['neu'].' neu, '.$r['chats'].' Chats'.($r['fehler'] !== [] ? ' – '.implode(' | ', $r['fehler']) : ''));
                }
                $pause = $intervall;
            } catch (Throwable $e) {
                $this->error(now()->format('H:i:s').' '.$e->getMessage());
                // Rückzug bei Dauerfehler: 5 s, 10 s, 20 s … bis 5 Minuten.
                $pause = min($pause * 2, 300);
            }

            if ($this->option('einmal') || time() - $start >= $maxLaufzeit) {
                break;
            }

            // In kleinen Schritten schlafen, damit ein Stopp-Signal schnell greift.
            for ($i = 0; $i < $pause && ! $this->beenden; $i++) {
                sleep(1);
            }
        }

        $this->info('Teams-Lauscher beendet.');

        return self::SUCCESS;
    }
}
