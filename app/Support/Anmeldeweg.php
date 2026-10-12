<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Merkt sich, auf welchem Weg das Angemeldet-bleiben-Cookie dieses Browsers
 * entstanden ist: Microsoft-SSO oder Passkey.
 *
 * Hintergrund: Läuft die Sitzung ab (SESSION_LIFETIME), meldet Laravel den
 * Benutzer still über das Dauer-Cookie wieder an – in einer frischen Sitzung,
 * ohne den Merker `two_factor_passed`. Bei Konten, die sich auch per Passwort
 * anmelden dürfen, wüsste die 2FA-Middleware dann nicht, ob das Cookie aus
 * einer Passwort-Anmeldung stammt (dann Code abfragen) oder aus SSO/Passkey
 * (dann ist der zweite Faktor schon erledigt).
 *
 * Jede Anmeldung in diesem Browser setzt oder löscht den Merker, deshalb passt
 * er immer zum Dauer-Cookie desselben Browsers. Das Cookie ist von Laravel
 * verschlüsselt und an Benutzer + remember_token gebunden; fälschen lässt es
 * sich nicht, und nach einem Abmelden (neuer remember_token) ist es wertlos.
 */
class Anmeldeweg
{
    public const MICROSOFT = 'microsoft';

    public const PASSKEY = 'passkey';

    private const COOKIE = 'intranet_anmeldeweg';

    /** Nach einer Anmeldung über SSO/Passkey mit Dauer-Cookie. */
    public function merken(User $user, string $weg): void
    {
        if ($user->getRememberToken() === null) {
            return;
        }

        // So lange wie das Angemeldet-bleiben-Cookie selbst.
        Cookie::queue(self::COOKIE, implode('|', [$user->id, $weg, $this->hash($user)]), 60 * 24 * 400);
    }

    /** Nach einer Passwort-Anmeldung oder beim Abmelden. */
    public function vergessen(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * Der Weg, über den das Dauer-Cookie dieses Browsers entstanden ist –
     * oder null, wenn es (womöglich) aus einer Passwort-Anmeldung stammt.
     */
    public function fuer(User $user, Request $request): ?string
    {
        [$id, $weg, $hash] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 3), 3, null);

        if ((int) $id !== $user->id
            || ! in_array($weg, [self::MICROSOFT, self::PASSKEY], true)
            || $user->getRememberToken() === null
            || ! hash_equals($this->hash($user), (string) $hash)) {
            return null;
        }

        return $weg;
    }

    private function hash(User $user): string
    {
        return hash('sha256', (string) $user->getRememberToken());
    }
}
