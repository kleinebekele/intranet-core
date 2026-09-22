<?php

namespace App\Ekkon\Http\Controllers;

use App\Ekkon\Models\GraphKonto;
use App\Ekkon\Models\TeamsChatStand;
use App\Ekkon\Models\TeamsNachricht;
use App\Ekkon\Services\TeamsGraphClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Teams-Chat-Eingang (Admin-Seite): was andere dem Bot-Konto in Teams
 * geschrieben haben, samt Stand des Lauschers und einer Antwortmöglichkeit
 * von Hand – zum Nachvollziehen, bevor eine KI übernimmt.
 */
class TeamsEingangController extends Controller
{
    public function index(): View
    {
        $letzterBlick = TeamsChatStand::query()->max('updated_at');

        return view('ekkon::teams.index', [
            'konto' => GraphKonto::aktuelles(),
            'nachrichten' => TeamsNachricht::query()->latest('gesendet_am')->limit(100)->get(),
            'chats' => TeamsChatStand::query()->orderByDesc('zuletzt_gesehen_am')->limit(50)->get(),
            // Lebt der Lauscher? Er schreibt bei jeder Runde mit Änderung den Stand;
            // ohne Änderung bleibt updated_at stehen – deshalb nur ein grober Hinweis.
            'letzterBlick' => $letzterBlick ? \Illuminate\Support\Carbon::parse($letzterBlick) : null,
        ]);
    }

    /** Antwort von Hand in den Chat der Nachricht – prüft nebenbei den Rückweg. */
    public function antworten(Request $request, TeamsNachricht $nachricht): RedirectResponse
    {
        $daten = $request->validate(['antwort' => ['required', 'string', 'max:4000']]);

        $fehler = (new TeamsGraphClient)->nachrichtPosten($nachricht->chat_id, '<p>'.nl2br(e($daten['antwort'])).'</p>');
        if ($fehler !== null) {
            return back()->withErrors(['antwort' => 'Antwort fehlgeschlagen: '.$fehler]);
        }

        $nachricht->update([
            'verarbeitet_am' => now(),
            'verarbeitung' => 'von Hand beantwortet ('.$request->user()?->name.')',
            'antwort' => $daten['antwort'],
        ]);

        return back()->with('status', 'Antwort gepostet.');
    }

    public function destroy(Request $request, TeamsNachricht $nachricht): RedirectResponse|JsonResponse
    {
        $nachricht->delete();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
