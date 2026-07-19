<?php

namespace App\Listeners;

use App\Models\MailOutbox;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

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
        // Notausgang: Ist der Ausgangskorb abgeschaltet, geht alles wie bisher
        // sofort raus. Wichtig fuer lokale Entwicklung ohne laufenden Scheduler.
        if (! config('mail.outbox.aktiv', true)) {
            return true;
        }

        $email = $event->message;
        $quelle = $event->data['__laravel_mailable']
            ?? $event->data['__laravel_notification']
            ?? null;

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
