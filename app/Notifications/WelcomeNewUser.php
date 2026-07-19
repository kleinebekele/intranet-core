<?php

namespace App\Notifications;

use App\Mail\VorlagenMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Willkommens-Mail für einen neu angelegten Benutzer. Enthält einen Link zum
 * Festlegen des eigenen Passworts (nutzt denselben Mechanismus wie
 * "Passwort vergessen": password_reset_tokens).
 *
 * Der Text kommt aus der bearbeitbaren Vorlage „einladung"
 * (Verwaltung → Mailvorlagen).
 */
class WelcomeNewUser extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): VorlagenMail
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new VorlagenMail('einladung', [
            'name' => $notifiable->name,
            'link' => $url,
        ]))->to($notifiable->email);
    }
}
