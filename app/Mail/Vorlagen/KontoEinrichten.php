<?php

namespace App\Mail\Vorlagen;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Platzhalter `{{ konto_einrichten }}` – in jeder Mailvorlage verfügbar.
 *
 * Hat der Empfänger noch kein eingerichtetes Konto (kein Passwort, keine
 * Microsoft-Anmeldung), erklärt ein farbig markierter Absatz, wie er über
 * „Passwort vergessen" sein Passwort festlegt. Der Link führt über eine
 * signierte Adresse auf diese Seite und trägt die Mailadresse dort schon ein –
 * die Adresse selbst steht nicht in der URL. Hat der Empfänger schon ein
 * Konto (oder gibt es ihn gar nicht), bleibt der Platzhalter leer.
 */
class KontoEinrichten
{
    public const PLATZHALTER = 'konto_einrichten';

    public const ERKLAERUNG = 'Hinweis „So richten Sie Ihr Konto ein" mit Link zu „Passwort vergessen" (Mailadresse vorbelegt) – erscheint nur, wenn der Empfänger noch kein Passwort hat';

    /** Braucht dieser Benutzer den Hinweis? */
    public static function noetig(?User $user): bool
    {
        return $user !== null
            && ! $user->istGesperrt()
            && $user->password === null
            && ! $user->nurUeberMicrosoft();
    }

    /**
     * Werte für eine Empfängeradresse.
     *
     * @return array{html: string, text: string}
     */
    public static function fuer(?string $email): array
    {
        $user = filled($email) ? User::where('email', $email)->first() : null;

        if (! self::noetig($user)) {
            return ['html' => '', 'text' => ''];
        }

        return self::bauen($user->email, URL::signedRoute('konto.einrichten', ['user' => $user->id]));
    }

    /** Vorschau im Editor. @return array{html: string, text: string} */
    public static function beispiel(): array
    {
        return self::bauen('anna.beispiel@example.org', url('/konto/einrichten/1?signature=…'));
    }

    /** @return array{html: string, text: string} */
    private static function bauen(string $email, string $link): array
    {
        $html = '<div style="margin:0 0 16px;padding:12px 16px;background:#fffbeb;border-left:4px solid #f59e0b;border-radius:4px;">'
            .'<p style="margin:0 0 8px;"><strong>Noch kein Passwort für das Intranet?</strong> So richten Sie Ihr Konto ein:</p>'
            .'<p style="margin:0 0 8px;">Klicken Sie auf den Link unten. Er öffnet die Seite „Passwort vergessen", auf der Ihre Anmelde-Adresse '
            .'<strong>'.e($email).'</strong> schon eingetragen ist. Dort auf „Link zum Zurücksetzen senden" klicken – Sie bekommen dann eine E-Mail, '
            .'über die Sie Ihr Passwort festlegen.</p>'
            .'<p style="margin:0;"><a href="'.e($link).'" style="color:#b45309;font-weight:bold;">Passwort festlegen</a></p>'
            .'</div>';

        $text = "Noch kein Passwort für das Intranet? So richten Sie Ihr Konto ein:\n"
            ."Öffnen Sie den Link unten. Er führt zur Seite „Passwort vergessen\", auf der Ihre Anmelde-Adresse {$email} schon eingetragen ist. "
            ."Dort auf „Link zum Zurücksetzen senden\" klicken – Sie bekommen dann eine E-Mail, über die Sie Ihr Passwort festlegen.\n"
            ."Passwort festlegen: {$link}\n";

        return ['html' => $html, 'text' => $text];
    }
}
