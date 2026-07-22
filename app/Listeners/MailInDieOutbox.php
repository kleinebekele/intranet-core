<?php

namespace App\Listeners;

use App\Models\MailOutbox;
use App\Support\Zustellbarkeit;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Fängt JEDE ausgehende E-Mail ab und legt sie in den Ausgangskorb.
 *
 * Der Trick steckt im Rückgabewert: Laravel bricht den Versand ab, wenn ein
 * Listener auf `MessageSending` `false` zurückgibt (siehe Mailer::shouldSendMessage).
 * Dadurch braucht kein Modul etwas zu tun oder zu wissen – Core, Kantine, Ekkon
 * und alles Künftige laufen automatisch über die Drosselung.
 *
 * Ausgeliefert wird später von \App\Console\Commands\MailAusliefern, und zwar
 * direkt über den Symfony-Transport. Der löst dieses Event nicht aus, es kann
 * also keine Endlosschleife entstehen.
 */
class MailInDieOutbox
{
    public function handle(MessageSending $event): bool
    {
        $email = $event->message;

        // Künstliche Adressen (z. B. `…@schueler.intern`) dürfen den Server nie
        // verlassen. Diese Prüfung steht bewusst VOR dem Notausgang unten: Sie
        // muss auch dann greifen, wenn der Ausgangskorb abgeschaltet ist.
        if (! $this->empfaengerBereinigen($email)) {
            Log::info('Mail nicht verschickt – kein zustellbarer Empfänger.', [
                'betreff' => $email->getSubject(),
            ]);

            return false;
        }

        // Den Auslöser-Header VOR dem Notausgang auslesen und entfernen: Er ist
        // nur intern gedacht und hätte in der ausgehenden Mail nichts verloren –
        // auch dann nicht, wenn der Ausgangskorb abgeschaltet ist und die Mail
        // gleich direkt rausgeht.
        $headerQuelle = $this->quelleAusHeaderZiehen($email);

        // Notausgang: Ist der Ausgangskorb abgeschaltet, geht alles wie bisher
        // sofort raus. Wichtig fuer lokale Entwicklung ohne laufenden Scheduler.
        if (! config('mail.outbox.aktiv', true)) {
            return true;
        }
        // Eine Mailable-/Notification-Klasse ist am aussagekräftigsten; erst wo
        // es keine gibt (Mail::html aus einem Modul), greift der Header.
        $quelle = $event->data['__laravel_mailable']
            ?? $event->data['__laravel_notification']
            ?? $headerQuelle;

        try {
            MailOutbox::create([
                'status' => MailOutbox::WARTEND,
                'prioritaet' => $this->prioritaet($quelle),
                'mailer' => $event->data['__laravel_mailer'] ?? null,
                'betreff' => $email->getSubject(),
                'an' => array_map(fn (Address $a) => $a->getAddress(), $email->getTo()),
                'quelle' => $quelle,
                'nachricht' => MailOutbox::verpacken($email),
            ]);
        } catch (\Throwable $e) {
            // Der Ausgangskorb darf den Versand nicht verschlucken: Lässt sich die
            // Zeile nicht schreiben, geht die Mail lieber ungedrosselt sofort raus,
            // als still verloren zu gehen.
            Log::error('Mail-Ausgangskorb nicht beschreibbar – Mail geht direkt raus.', [
                'betreff' => $email->getSubject(),
                'fehler' => $e->getMessage(),
            ]);

            return true;
        }

        return false; // bricht den sofortigen Versand ab
    }

    /**
     * Wirft unzustellbare Empfänger aus der Mail.
     *
     * Eine Rundmail an eine Klasse soll nicht daran scheitern, dass ein Schüler
     * nur eine künstliche Adresse hat – die anderen bekommen sie trotzdem.
     * Bleibt am Ende niemand übrig, gibt es nichts zu verschicken.
     *
     * @return bool ob noch ein zustellbarer Empfänger übrig ist
     */
    private function empfaengerBereinigen(Email $email): bool
    {
        $uebrig = 0;

        foreach (['To', 'Cc', 'Bcc'] as $feld) {
            $adressen = $email->{'get'.$feld}();

            if ($adressen === []) {
                continue;
            }

            $behalten = array_values(array_filter(
                $adressen,
                fn (Address $a) => Zustellbarkeit::zustellbar($a->getAddress()),
            ));

            if (count($behalten) !== count($adressen)) {
                // Setzt das Feld neu; leeres Array leert es.
                $email->{strtolower($feld)}(...$behalten);
            }

            $uebrig += count($behalten);
        }

        return $uebrig > 0;
    }

    /**
     * Den Auslöser aus dem internen Header lesen und ihn danach entfernen.
     *
     * So kann ein Modul, das über {@see \Illuminate\Support\Facades\Mail::html()}
     * verschickt (und damit keine Mailable-Klasse hat), im Maillog trotzdem als
     * Auslöser erscheinen – gesetzt über {@see \App\Mail\Vorlagen\VorlagenMailer::quelleMarkieren()}.
     */
    private function quelleAusHeaderZiehen(Email $email): ?string
    {
        $headers = $email->getHeaders();
        $name = \App\Mail\Vorlagen\VorlagenMailer::QUELLE_HEADER;

        if (! $headers->has($name)) {
            return null;
        }

        $wert = trim((string) $headers->get($name)?->getBodyAsString());
        $headers->remove($name);

        return $wert !== '' ? $wert : null;
    }

    /**
     * Eilige Mails bekommen Vorfahrt in der Warteschlange.
     *
     * Zeitkritisch ist alles, wo jemand vor dem Bildschirm wartet: ein
     * 2FA-Code oder ein Passwort-Link ist nach zehn Minuten wertlos.
     */
    private function prioritaet(?string $quelle): int
    {
        if ($quelle === null) {
            return 0;
        }

        foreach ((array) config('mail.outbox.eilig', []) as $klasse) {
            if ($quelle === $klasse || is_subclass_of($quelle, $klasse)) {
                return 10;
            }
        }

        return 0;
    }
}
