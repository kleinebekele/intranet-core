<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schaltet die detaillierte Laravel-Fehlerseite (Stacktrace, Datei, Zeile) NUR
 * fuer eingeloggte Administratoren ein – und nur fuer deren jeweiligen Request.
 *
 * Damit sieht ein Admin bei einem Fehler die Ursache direkt im Browser, statt
 * ins Logfile auf dem Server schauen zu muessen; alle anderen bekommen
 * weiterhin die neutrale Fehlerseite. `APP_DEBUG` in der .env bleibt auf
 * `false` – normale Besucher sehen keinerlei Interna.
 *
 * Steht in der web-Gruppe hinter StartSession, deshalb ist der angemeldete
 * Benutzer hier bereits ueber die Session aufloesbar.
 */
class AdminSiehtFehlerdetails
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            config(['app.debug' => true]);
        }

        return $next($request);
    }
}
