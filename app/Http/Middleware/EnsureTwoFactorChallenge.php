<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hält angemeldete Benutzer auf der Zwei-Faktor-Abfrage fest, bis der
 * zweite Faktor bestätigt wurde. Hängt an der globalen web-Gruppe und
 * schützt damit automatisch auch alle Modul-Routen.
 */
class EnsureTwoFactorChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || ! config('intranet.two_factor')
            || $request->session()->get('two_factor_passed') === true
            || $request->routeIs('two-factor.*', 'logout')
        ) {
            return $next($request);
        }

        return $request->expectsJson()
            ? abort(423, 'Zwei-Faktor-Bestätigung erforderlich.')
            : redirect()->route('two-factor.challenge');
    }
}
