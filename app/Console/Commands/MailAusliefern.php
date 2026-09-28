<?php

namespace App\Console\Commands;

use App\Models\MailKonto;
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

    /**
     * In diesem Lauf schon eingehängte SMTP-Konten. Jedes Konto wird nur EINMAL
     * registriert, damit alle seine Mails über dieselbe Verbindung gehen –
     * `registrieren()` baut den Mailer neu, und eine neue Verbindung je Mail
     * führte beim Newsletter zu „421 too many connections".
     *
     * @var array<string, true>
     */
    private array $eingehaengt = [];

    /**
     * Mailer, deren Server in diesem Lauf mit 421 abgewiesen hat. Weitere Mails
     * über ihn würden nur ebenfalls scheitern und Versuche verbrauchen – sie
     * bleiben unangetastet für den nächsten Lauf.
     *
     * @var array<string, true>
     */
    private array $abgewiesen = [];

    public function handle(): int
    {
        $this->eingehaengt = $this->abgewiesen = [];

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

        $versendet = $gescheitert = $zurueckgestellt = 0;

        foreach ($wartende as $eintrag) {
            match ($this->versenden($eintrag)) {
                true => $versendet++,
                false => $gescheitert++,
                null => $zurueckgestellt++,
            };
        }

        $this->info("{$versendet} versendet, {$gescheitert} fehlgeschlagen."
            .($zurueckgestellt ? " {$zurueckgestellt} zurückgestellt (Server hat abgewiesen)." : ''));

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

    /**
     * Eine einzelne Mail rausschicken und das Ergebnis festhalten.
     *
     * @return bool|null true = versendet, false = gescheitert,
     *                   null = nicht probiert (Server hat in diesem Lauf schon abgewiesen)
     */
    private function versenden(MailOutbox $eintrag): ?bool
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

        // Ein SMTP-Konto aus der Verwaltung? Dann seinen Mailer erst einhängen.
        // Fehlt das Konto inzwischen, bleibt die Mail liegen statt still über den
        // Standard-Absender rauszugehen – das wäre eine falsche Absenderadresse.
        $mailer = $eintrag->mailer ?: null;
        if ($mailer !== null && str_starts_with($mailer, MailKonto::PRAEFIX)) {
            $konto = MailKonto::ausMailerName($mailer);

            if (! $konto || ! $konto->aktiv) {
                $eintrag->update([
                    'status' => MailOutbox::FEHLGESCHLAGEN,
                    'versuche' => $eintrag->versuche + 1,
                    'fehler' => "SMTP-Absender {$mailer} ist gelöscht oder abgeschaltet.",
                ]);

                $this->warn("#{$eintrag->id} [{$eintrag->betreff}]: SMTP-Absender {$mailer} fehlt – nicht verschickt.");

                return false;
            }

            if (! isset($this->eingehaengt[$mailer])) {
                $konto->registrieren();
                $this->eingehaengt[$mailer] = true;
            }
        }

        if (isset($this->abgewiesen[$mailer ?? ''])) {
            return null;
        }

        try {
            // Direkt über den Transport, nicht über Mail::send – sonst würde
            // MailInDieOutbox die Mail sofort wieder einkassieren.
            $gesendet = Mail::mailer($mailer)
                ->getSymfonyTransport()
                ->send($eintrag->alsEmail());

            $eintrag->update([
                'status' => MailOutbox::VERSENDET,
                'versendet_am' => now(),
                'message_id' => $gesendet?->getMessageId(),
                'versuche' => $eintrag->versuche + 1,
                'naechster_versuch_am' => null,
                'fehler' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $versuche = $eintrag->versuche + 1;
            $endgueltig = $versuche >= MailOutbox::MAX_VERSUCHE;
            $warten = MailOutbox::WARTEZEITEN[$versuche - 1] ?? MailOutbox::WARTEZEITEN[array_key_last(MailOutbox::WARTEZEITEN)];

            // 421 = Server nimmt gerade gar nichts an (Sperre, Überlast). Das
            // betrifft nicht diese Mail, sondern die Verbindung – also für den
            // Rest des Laufs die Finger von diesem Mailer lassen.
            if ((int) $e->getCode() === 421) {
                $this->abgewiesen[$mailer ?? ''] = true;
                Mail::purge($mailer);
            }

            $eintrag->update([
                'status' => $endgueltig ? MailOutbox::FEHLGESCHLAGEN : MailOutbox::WARTEND,
                'versuche' => $versuche,
                'naechster_versuch_am' => $endgueltig ? null : now()->addMinutes($warten),
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
