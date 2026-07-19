<?php

namespace App\Http\Middleware;

use App\Support\RoutenAliase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wendet die in der Verwaltung vergebenen Adressen an – vor dem Routing.
 *
 * Warum als Middleware und nicht im boot() eines Providers: Laravel lädt die
 * Routen erst in einem `booted`-Callback, also NACH allen Providern – bei
 * aktivem `route:cache` sogar in einem zweiten, noch später laufenden. Wer im
 * boot() umschreibt, arbeitet an einer Sammlung, die es noch nicht gibt. Das
 * fällt lokal nicht auf und erst auf einem Server mit Routen-Cache.
 *
 * Globale Middleware läuft dagegen im Kernel VOR `dispatchToRouter` – die
 * Routen sind da garantiert geladen, egal ob zwischengespeichert oder nicht.
 * Der Zeitpunkt hängt damit nicht mehr an Laravels innerer Reihenfolge.
 *
 * Nur im Web: In Konsole und Queue erzeugt `route()` weiter die technische
 * Adresse. Die bleibt gültig – die sprechende ist ein Alias, keine Ablösung.
 */
class RoutenAliaseAnwenden
{
    public function __construct(private readonly RoutenAliase $aliase) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->aliase->anwenden();

        return $next($request);
    }
}
