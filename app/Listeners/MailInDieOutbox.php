<?php

namespace App\Listeners;

use App\Models\MailAbsender;
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

        // Auslöser-, Referenz- und Modul-Header VOR dem Notausgang auslesen und
        // entfernen: Sie sind nur intern gedacht und hätten in der ausgehenden
        // Mail nichts verloren – auch dann nicht, wenn der Ausgangskorb
        // abgeschaltet ist und die Mail gleich direkt rausgeht.
        $headerQuelle = $this->headerZiehen($email, \App\Mail\Vorlagen\VorlagenMailer::QUELLE_HEADER);
        $referenz = $this->headerZiehen($email, \App\Mail\Vorlagen\VorlagenMailer::REFERENZ_HEADER);
        $headerModul = $this->headerZiehen($email, \App\Mail\Vorlagen\VorlagenMailer::MODUL_HEADER);

        // Die auslösende Klasse (Mailable/Notification) bestimmt die EILIGKEIT –
        // sie wird über den Klassennamen erkannt (2FA, Passwort-Link). Das bleibt
        // getrennt von der ANZEIGE: ein Modul, das einen sprechenden Auslöser
        // per Header setzt, soll damit nicht die Vorfahrt-Erkennung aushebeln.
        $klasse = $event->data['__laravel_mailable']
            ?? $event->data['__laravel_notification']
            ?? null;

        // Auslöser fürs Log: ein ausdrücklich gesetzter Header GEWINNT (sprechend),
        // sonst der Klassenname als Rückfall (z. B. „TwoFactorCodeMail").
        $quelle = ($headerQuelle !== null && $headerQuelle !== '')
            ? $headerQuelle
            : $klasse;

        $modul = $this->modulErmitteln($headerModul, $klasse);

        // Eigenen Absender/Antwort-an setzen, falls für Modul+Auslöser hinterlegt.
        // Vor dem Serialisieren, damit die gespeicherte Nachricht schon stimmt –
        // auch beim Notausgang unten geht sie so korrekt raus.
        $this->absenderAnwenden($email, $modul, $quelle);

        // Notausgang: Ist der Ausgangskorb abgeschaltet, geht alles wie bisher
        // sofort raus. Wichtig fuer lokale Entwicklung ohne laufenden Scheduler.
        if (! config('mail.outbox.aktiv', true)) {
            return true;
        }

        try {
            MailOutbox::create([
                'status' => MailOutbox::WARTEND,
                'prioritaet' => $this->prioritaet($klasse),
                'mailer' => $event->data['__laravel_mailer'] ?? null,
                'betreff' => $email->getSubject(),
                'an' => array_map(fn (Address $a) => $a->getAddress(), $email->getTo()),
                'quelle' => $quelle,
                'modul' => $modul,
                'referenz' => $referenz,
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
     * Einen internen Intranet-Header lesen und ihn danach aus der Mail entfernen.
     *
     * Damit kann ein Modul, das über {@see \Illuminate\Support\Facades\Mail::html()}
     * verschickt (und damit keine Mailable-Klasse hat), im Maillog als Auslöser
     * erscheinen und seine Mail dort später wiederfinden – gesetzt über
     * {@see \App\Mail\Vorlagen\VorlagenMailer::quelleMarkieren()}.
     */
    private function headerZiehen(Email $email, string $name): ?string
    {
        $headers = $email->getHeaders();

        if (! $headers->has($name)) {
            return null;
        }

        $header = $headers->get($name);

        // getBodyAsString() liefert die MIME-kodierte Fassung (Umlaute/ß werden
        // zu „=?utf-8?Q?…"). Der UnstructuredHeader kennt den Klartext über
        // getBody(); nur darauf zurückfallen, falls es doch ein anderer Typ ist.
        $wert = $header instanceof \Symfony\Component\Mime\Header\UnstructuredHeader
            ? $header->getBody()
            : (string) $header?->getBodyAsString();

        $wert = trim($wert);
        $headers->remove($name);

        return $wert !== '' ? $wert : null;
    }

    /**
     * Eilige Mails bekommen Vorfahrt in der Warteschlange.
     *
     * Zeitkritisch ist alles, wo jemand vor dem Bildschirm wartet: ein
     * 2FA-Code oder ein Passwort-Link ist nach zehn Minuten wertlos. Erkannt
     * wird das am KLASSENNAMEN (Mailable/Notification), nicht am Auslöser-Text –
     * darum bekommt diese Methode die Klasse, nicht die Anzeige-Quelle.
     */
    private function prioritaet(?string $klasse): int
    {
        if ($klasse === null) {
            return 0;
        }

        foreach ((array) config('mail.outbox.eilig', []) as $eilig) {
            if ($klasse === $eilig || is_subclass_of($klasse, $eilig)) {
                return 10;
            }
        }

        return 0;
    }

    /**
     * Das auslösende Modul bestimmen: ein gesetzter Header gewinnt, sonst wird
     * es aus dem Namensraum der Klasse abgeleitet. `App\…` ist der Core, ein
     * Modul liegt unter `Intranet\Modules\<Name>\…`.
     */
    private function modulErmitteln(?string $headerModul, ?string $klasse): ?string
    {
        if ($headerModul !== null && $headerModul !== '') {
            return $headerModul;
        }

        if (is_string($klasse) && str_starts_with($klasse, 'Intranet\\Modules\\')) {
            $teile = explode('\\', $klasse);

            return $teile[2] ?? 'Core';
        }

        // Alles aus dem Core-Namensraum – und alles, was wir nicht zuordnen
        // können – zählt als Core. Eine reine `Mail::html()`-Mail ohne Header
        // ist praktisch immer Core.
        return 'Core';
    }

    /**
     * Eigenen Absender/Antwort-an aus der Konfig (Modul+Auslöser) setzen.
     *
     * Ist eine Zeile hinterlegt, GEWINNT sie über das, was das Modul selbst
     * gesetzt hat – genau das ist der Sinn der zentralen Einstellung. Ohne
     * Zeile bleibt alles, wie die Mail es mitbringt.
     */
    private function absenderAnwenden(Email $email, ?string $modul, ?string $quelle): void
    {
        $konfig = MailAbsender::fuer($modul, $quelle);

        if (! $konfig instanceof MailAbsender) {
            return;
        }

        if (filled($konfig->absender_mail)) {
            $email->from(new Address($konfig->absender_mail, (string) ($konfig->absender_name ?? '')));
        }

        if (filled($konfig->antwort_an)) {
            $email->replyTo(new Address($konfig->antwort_an));
        }
    }
}
