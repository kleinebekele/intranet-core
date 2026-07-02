<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Vom Admin ausgelöster "Passwort zurücksetzen"-Link für einen bestehenden
 * Benutzer. Eigene, deutschsprachige Variante – lässt den öffentlichen
 * "Passwort vergessen"-Ablauf unberührt.
 */
class PasswordResetLinkNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Passwort zurücksetzen')
            ->greeting('Hallo '.$notifiable->name.'!')
            ->line('Für dein Intranet-Konto wurde das Zurücksetzen des Passworts angefordert.')
            ->action('Neues Passwort festlegen', $url)
            ->line('Falls du das nicht veranlasst hast, kannst du diese E-Mail einfach ignorieren.')
            ->salutation('Viele Grüße vom Intranet-Team');
    }
}
