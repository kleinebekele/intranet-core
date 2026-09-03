<?php

namespace App\Http\Middleware;

use App\Support\Microsoft\MicrosoftSso;
use App\Support\TwoFactorTrust;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hält angemeldete Benutzer mit aktiver 2FA auf der Zwei-Faktor-Abfrage fest,
 * bis der zweite Faktor bestätigt wurde (oder das Gerät als vertrauenswürdig
 * bekannt ist). Hängt an der globalen web-Gruppe und schützt damit automatisch
 * auch alle Modul-Routen.
 */
class EnsureTwoFactorChallenge
{
    public function __construct(private readonly TwoFactorTrust $trust) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || ! $user->needsTwoFactor()
            || $request->session()->get('two_factor_passed') === true
            || $request->routeIs('two-factor.*', 'logout')
        ) {
            return $next($request);
        }

        // Über das Dauer-Cookie zurückgekehrt, und das Konto kommt
        // ausschliesslich über Microsoft herein? Dann ist der zweite Faktor
        // dort schon abgehandelt worden. Die Sitzung ist nach dem
        // Cookie-Login neu, deshalb fehlt der Merker aus dem SSO-Ablauf.
        //
        // Die Bedingung hängt bewusst an nurUeberMicrosoft(): Bei einem Konto,
        // das sich AUCH mit Passwort anmelden darf (Administratoren), könnte
        // das Cookie von einer Passwort-Anmeldung stammen – dort würde ein
        // Durchlassen die 2FA aushebeln.
        if (Auth::viaRemember()
            && $user->nurUeberMicrosoft()
            && app(MicrosoftSso::class)->aktiv()) {
            $request->session()->put('two_factor_passed', true);
            $request->session()->put(MicrosoftSso::ANGEMELDET_UEBER, true);

            return $next($request);
        }

        // Bekanntes Gerät ("30 Tage merken")? Dann ohne Abfrage durchlassen.
        if ($this->trust->check($user, $request)) {
            $request->session()->put('two_factor_passed', true);

            return $next($request);
        }

        return $request->expectsJson()
            ? abort(423, 'Zwei-Faktor-Bestätigung erforderlich.')
            : redirect()->route('two-factor.challenge');
    }
}
