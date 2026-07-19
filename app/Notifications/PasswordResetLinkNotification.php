<?php

namespace App\Notifications;

use App\Mail\VorlagenMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Vom Admin ausgelöster "Passwort zurücksetzen"-Link für einen bestehenden
 * Benutzer. Lässt den öffentlichen "Passwort vergessen"-Ablauf unberührt.
 *
 * Der Text kommt aus der bearbeitbaren Vorlage „passwort_reset".
 */
class PasswordResetLinkNotification extends Notification
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

        return (new VorlagenMail('passwort_reset', [
            'name' => $notifiable->name,
            'link' => $url,
        ]))->to($notifiable->email);
    }
}
