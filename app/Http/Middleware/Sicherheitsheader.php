<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grundschutz-Header für jede Antwort des Intranets – unabhängig davon, ob
 * der Webserver davor sie setzt. Was schon gesetzt ist (vom Webserver oder
 * von einer Route mit eigener Regel), bleibt unangetastet.
 *
 *  - Kein Einbetten in fremde Seiten (Clickjacking).
 *  - Der Browser rät keinen anderen Dateityp als den gemeldeten.
 *  - Fremde Seiten sehen beim Klick auf einen Link nur die Domain.
 *  - Im Produktivbetrieb nur noch HTTPS (HSTS, ein Jahr).
 *
 * Bewusst KEINE Skript-Regel (script-src): Alpine und die eingebundenen
 * Dienstleister-Skripte der Footer-Seiten bräuchten Ausnahmen, die den
 * Nutzen weitgehend aufheben.
 */
class Sicherheitsheader
{
    public function handle(Request $request, Closure $next): Response
    {
        $antwort = $next($request);
        $kopf = $antwort->headers;

        $vorgaben = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        if (app()->environment('production')) {
            $vorgaben['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($vorgaben as $name => $wert) {
            if (! $kopf->has($name)) {
                $kopf->set($name, $wert);
            }
        }

        if (! $kopf->has('Content-Security-Policy')) {
            $kopf->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $antwort;
    }
}
