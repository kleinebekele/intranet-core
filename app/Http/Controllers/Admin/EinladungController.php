<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Einladung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Der Einladungs-Puffer: Hier entscheidet ein Mensch, ob die vorgemerkten
 * Zugangslinks tatsächlich verschickt werden.
 */
class EinladungController extends Controller
{
    public function index(): View
    {
        $wartend = Einladung::with('user.roles')->wartend()->oldest()->get();

        $erledigt = Einladung::with('user')
            ->where('status', '!=', Einladung::WARTEND)
            ->latest('entschieden_am')
            ->take(25)
            ->get();

        return view('admin.einladungen.index', compact('wartend', 'erledigt'));
    }

    /** Eine einzelne Einladung verschicken. */
    public function freigeben(Request $request, Einladung $einladung): RedirectResponse
    {
        $verschickt = $einladung->freigeben($request->user());

        return back()->with('status', $verschickt
            ? "Einladung an {$einladung->user->email} ist im Ausgangskorb."
            : "Einladung an {$einladung->user->email} konnte nicht verschickt werden (keine echte Adresse).");
    }

    /**
     * Alle wartenden auf einmal – der Regelfall nach einem Import.
     *
     * Der Versand läuft über den Ausgangskorb, wird also gedrosselt und nicht
     * in einem Schwall verschickt.
     */
    public function alleFreigeben(Request $request): RedirectResponse
    {
        $verschickt = 0;
        $uebersprungen = 0;

        foreach (Einladung::with('user')->wartend()->get() as $einladung) {
            $einladung->freigeben($request->user()) ? $verschickt++ : $uebersprungen++;
        }

        $meldung = "{$verschickt} Einladung(en) in den Ausgangskorb gelegt.";
        $meldung .= $uebersprungen ? " {$uebersprungen} übersprungen (keine echte Adresse)." : '';

        return back()->with('status', $meldung);
    }

    public function verwerfen(Request $request, Einladung $einladung): RedirectResponse
    {
        $einladung->verwerfen($request->user());

        return back()->with('status', "Einladung an {$einladung->user->email} verworfen.");
    }
}
