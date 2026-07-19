<?php

namespace App\Console\Commands;

use App\Models\MailOutbox;
use App\Support\Zustellbarkeit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Liefert wartende Mails aus dem Ausgangskorb aus – höchstens so viele, wie das
 * Stundenlimit des Mailproviders noch zulässt.
 *
 * Läuft per Scheduler jede Minute. Ohne einen Cron, der `schedule:run` aufruft,
 * bleibt der Korb voll und es geht KEINE Mail raus – das ist der Preis der
 * Drosselung und muss auf jedem Server eingerichtet sein.
 */
class MailAusliefern extends Command
{
    protected $signature = 'mail:ausliefern
                            {--anzahl= : Höchstens so viele Mails in diesem Lauf (überschreibt die Restmenge)}';

    protected $description = 'Verschickt wartende Mails aus dem Ausgangskorb im erlaubten Takt';

    public function handle(): int
    {
        $rest = $this->restmenge();

        if ($rest === 0) {
            $this->line('Stundenlimit ausgeschöpft – in diesem Lauf geht nichts raus.');

            return self::SUCCESS;
        }

        if ($vorgabe = $this->option('anzahl')) {
            $rest = min($rest, max(0, (int) $vorgabe));
        }

        $wartende = MailOutbox::abzuarbeiten()->limit($rest)->get();

        if ($wartende->isEmpty()) {
            $this->line('Nichts zu tun – der Ausgangskorb ist leer.');

            return self::SUCCESS;
        }

        $versendet = $gescheitert = 0;

        foreach ($wartende as $eintrag) {
            $this->versenden($eintrag) ? $versendet++ : $gescheitert++;
        }

        $this->info("{$versendet} versendet, {$gescheitert} fehlgeschlagen.");

        if ($offen = MailOutbox::where('status', MailOutbox::WARTEND)->count()) {
            $this->line("Noch {$offen} Mails im Ausgangskorb.");
        }

        return self::SUCCESS;
    }

    /**
     * Wie viele Mails dürfen in dieser Stunde noch raus?
     *
     * Gezählt wird gleitend über die letzten 60 Minuten, nicht nach Uhrzeit-Stunde:
     * sonst könnten um 10:59 und 11:01 zusammen 500 Mails rausgehen und der
     * Provider würde uns trotzdem sperren.
     */
    private function restmenge(): int
    {
        $limit = (int) config('mail.outbox.stundenlimit', 0);

        if ($limit <= 0) {
            return PHP_INT_MAX; // kein Limit gesetzt
        }

        $letzteStunde = MailOutbox::where('status', MailOutbox::VERSENDET)
            ->where('versendet_am', '>=', now()->subHour())
            ->count();

        return max(0, $limit - $letzteStunde);
    }

    /** Eine einzelne Mail rausschicken und das Ergebnis festhalten. */
    private function versenden(MailOutbox $eintrag): bool
    {
        // Zweiter Riegel gegen künstliche Adressen: Zeilen, die vor dieser
        // Prüfung in den Korb gelangt sind, dürfen nicht doch noch rausgehen.
        if (Zustellbarkeit::filtern((array) $eintrag->an) === []) {
            $eintrag->update([
                'status' => MailOutbox::FEHLGESCHLAGEN,
                'versuche' => $eintrag->versuche + 1,
                'fehler' => 'Kein zustellbarer Empfänger (künstliche Adresse).',
            ]);

            $this->warn("#{$eintrag->id} [{$eintrag->betreff}]: kein zustellbarer Empfänger – übersprungen.");

            return false;
        }

        try {
            // Direkt über den Transport, nicht über Mail::send – sonst würde
            // MailInDieOutbox die Mail sofort wieder einkassieren.
            $gesendet = Mail::mailer($eintrag->mailer ?: null)
                ->getSymfonyTransport()
                ->send($eintrag->alsEmail());

            $eintrag->update([
                'status' => MailOutbox::VERSENDET,
                'versendet_am' => now(),
                'message_id' => $gesendet?->getMessageId(),
                'versuche' => $eintrag->versuche + 1,
                'fehler' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $versuche = $eintrag->versuche + 1;
            $endgueltig = $versuche >= MailOutbox::MAX_VERSUCHE;

            $eintrag->update([
                'status' => $endgueltig ? MailOutbox::FEHLGESCHLAGEN : MailOutbox::WARTEND,
                'versuche' => $versuche,
                'fehler' => $e->getMessage(),
            ]);

            Log::warning('Mail konnte nicht ausgeliefert werden.', [
                'outbox_id' => $eintrag->id,
                'betreff' => $eintrag->betreff,
                'versuch' => $versuche,
                'endgueltig' => $endgueltig,
                'fehler' => $e->getMessage(),
            ]);

            $this->warn("#{$eintrag->id} [{$eintrag->betreff}]: {$e->getMessage()}");

            return false;
        }
    }
}
