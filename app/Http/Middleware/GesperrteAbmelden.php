<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eine Sperre wirkt sofort – auch für bereits angemeldete Benutzer.
 *
 * Ohne diese Middleware könnte jemand, der gerade gesperrt wurde, bis zum
 * Ablauf seiner Sitzung weiterarbeiten. Gerade beim automatischen Abgleich mit
 * Linear (Schulabgänger) ist das der praktische Regelfall: Gesperrt wird
 * nachts, im Browser ist die Sitzung von gestern noch offen.
 */
class GesperrteAbmelden
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->istGesperrt()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => trans('auth.gesperrt')]);
        }

        return $next($request);
    }
}
