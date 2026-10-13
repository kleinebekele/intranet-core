<?php

namespace App\Http\Controllers;

use App\Models\FooterLink;
use Illuminate\View\View;

/**
 * Eigene Seiten aus dem Footer (Impressum, Datenschutz …) unter /seite/{slug}.
 * Bewusst ohne Anmeldung erreichbar – rechtliche Angaben müssen auch vor dem
 * Login lesbar sein.
 */
class SeiteController extends Controller
{
    public function show(string $slug): View
    {
        $seite = FooterLink::aktiv()
            ->where('art', FooterLink::INTERN)
            ->where('slug', $slug)
            ->firstOrFail();

        return view('seiten.show', ['seite' => $seite]);
    }
}
