<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweis an die Administratoren: Es liegen Einladungen zur Freigabe bereit.
 *
 * Bewusst nur eine Meldung MIT Anzahl statt einer Mail je Benutzer – sonst wäre
 * genau das erreicht, was der Puffer verhindern soll.
 */
class EinladungenWarten extends Notification
{
    use Queueable;

    public function __construct(public int $anzahl, public ?string $quelle = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $herkunft = $this->quelle ? " (aus {$this->quelle})" : '';

        return (new MailMessage)
            ->subject("{$this->anzahl} Einladungen warten auf Freigabe")
            ->greeting('Hallo '.$notifiable->name.'!')
            ->line("Es warten {$this->anzahl} Einladungen darauf, verschickt zu werden{$herkunft}.")
            ->line('Verschickt wird nichts, solange niemand zustimmt.')
            ->action('Einladungen ansehen', route('admin.einladungen.index'))
            ->line('Beim Freigeben gehen die Mails gedrosselt über den Ausgangskorb raus.')
            ->salutation('Viele Grüße vom Intranet');
    }
}
