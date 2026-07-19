<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Willkommens-Mail für einen im Admin-Panel neu angelegten Benutzer.
 * Enthält einen Link zum Festlegen des eigenen Passworts (nutzt denselben
 * Mechanismus wie "Passwort vergessen": password_reset_tokens).
 */
class WelcomeNewUser extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

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
            ->subject('Willkommen im Intranet – dein Zugang ist bereit')
            ->greeting('Hallo '.$notifiable->name.'!')
            ->line('Für dich wurde ein Zugang zum Intranet angelegt. Herzlich willkommen!')
            ->line('Bitte lege über den folgenden Button dein persönliches Passwort fest:')
            ->action('Passwort festlegen', $url)
            ->line('Danach kannst du dich jederzeit mit deiner E-Mail-Adresse anmelden.')
            ->salutation('Viele Grüße vom Intranet-Team');
    }
}
