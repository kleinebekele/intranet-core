<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;

/**
 * Schreibt die Anmelde-Ereignisse ins Audit-Log und hält am Benutzer fest,
 * wann er sich zuletzt angemeldet hat.
 *
 * Hängt an Laravels Auth-Ereignissen statt an den Controllern: So zählt jeder
 * Weg – Passwort, Microsoft, Registrierung und das Dauer-Cookie („angemeldet
 * bleiben"), das eine Sitzung ohne Anmeldemaske eröffnet.
 *
 * Fehlgeschlagene Passwort-Anmeldungen schreibt LoginRequest selbst; dort ist
 * bekannt, WARUM es scheiterte (falsches Passwort, gesperrt, nur Microsoft).
 */
class AnmeldungenProtokollieren
{
    public function angemeldet(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        // Ohne Model-Save: updated_at soll dadurch nicht wandern.
        User::whereKey($user->getKey())->update(['zuletzt_angemeldet_am' => now()]);
        $user->zuletzt_angemeldet_am = now();

        $weg = $this->weg($event);

        Audit::schreiben('anmeldung', self::WEGE[$weg], $user, ['weg' => $weg], akteur: $user);
    }

    public function abgemeldet(Logout $event): void
    {
        if ($event->user instanceof User) {
            Audit::schreiben('abmeldung', null, $event->user, akteur: $event->user);
        }
    }

    public function ausgesperrt(Lockout $event): void
    {
        $email = (string) $event->request->input('email', '');

        Audit::schreiben(
            'anmeldung.gesperrt',
            "Zu viele Fehlversuche für {$email}.",
            $email !== '' ? User::query()->where('email', $email)->first() : null,
            ['email' => $email],
            akteur: false,
        );
    }

    public function registriert(Registered $event): void
    {
        if ($event->user instanceof User) {
            Audit::schreiben('registrierung', 'Selbst registriert.', $event->user, akteur: $event->user);
        }
    }

    public function passwortZurueckgesetzt(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            Audit::schreiben('passwort.zurueckgesetzt', 'Über den Passwort-Link.', $event->user, akteur: $event->user);
        }
    }

    private const WEGE = [
        'passwort' => 'Mit Passwort.',
        'microsoft' => 'Über Microsoft.',
        'registrierung' => 'Direkt nach der Registrierung.',
        'cookie' => 'Angemeldet geblieben (Dauer-Cookie).',
        'system' => 'Durch das System.',
    ];

    /** Über welchen Weg kam die Anmeldung zustande? */
    private function weg(Login $event): string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'system';
        }

        $route = request()?->route();
        $name = $route?->getName() ?? '';
        $pfad = request()?->path() ?? '';

        return match (true) {
            $name === 'login' || $pfad === 'login' => 'passwort',
            str_starts_with($name, 'auth.microsoft.') => 'microsoft',
            $name === 'register' || $pfad === 'register' => 'registrierung',
            $event->remember => 'cookie',
            default => 'system',
        };
    }
}
