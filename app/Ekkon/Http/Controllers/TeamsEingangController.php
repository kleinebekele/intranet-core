<?php

namespace App\Ekkon\Http\Controllers;

use App\Ekkon\Models\GraphKonto;
use App\Ekkon\Models\TeamsChatStand;
use App\Ekkon\Models\TeamsNachricht;
use App\Ekkon\Services\KiClient;
use App\Ekkon\Services\TeamsGraphClient;
use App\Models\Setting;
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

        $ki = new KiClient;

        return view('ekkon::teams.index', [
            'konto' => GraphKonto::aktuelles(),
            'ki' => [
                'aktiv' => (bool) Setting::get(KiClient::AKTIV, false),
                'url' => $ki->url(),
                'schluessel_da' => $ki->schluessel() !== '',
                'modell' => $ki->modell(),
                'spezialanwendung' => $ki->spezialanwendung(),
                'ordner' => $ki->ordner(),
                'wissensquellen' => $ki->wissensquellenAktiv(),
                'verlauf' => $ki->verlauf(),
                'wissensquellen_namen' => \App\Ekkon\Support\Wissensquellen::namen(),
                'system' => $ki->systemPrompt(),
                'gruppen_nur_erwaehnt' => $ki->gruppenNurBeiErwaehnung(),
            ],
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

    // ── KI-Einstellungen ────────────────────────────────────────────────

    public function kiSpeichern(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'url' => ['required', 'url', 'starts_with:https://', 'max:255'],
            'schluessel' => ['nullable', 'string', 'max:500'],
            'modell' => ['nullable', 'string', 'max:120'],
            'system' => ['nullable', 'string', 'max:4000'],
            'aktiv' => ['nullable', 'boolean'],
            'gruppen_nur_erwaehnt' => ['nullable', 'boolean'],
        ]);

        $ki = new KiClient;
        Setting::set(KiClient::URL, rtrim($daten['url'], '/'));
        // Leer gelassen = bestehenden Schlüssel behalten (er wird nie angezeigt).
        if (filled($daten['schluessel'] ?? null)) {
            $ki->schluesselSpeichern(trim($daten['schluessel']));
        }
        Setting::set(KiClient::MODELL, trim((string) ($daten['modell'] ?? '')));
        Setting::set(KiClient::SPEZIALANWENDUNG, trim((string) $request->input('spezialanwendung', '')));
        Setting::set(KiClient::ORDNER, trim((string) $request->input('ordner', '')));
        Setting::set(KiClient::WISSENSQUELLEN, $request->boolean('wissensquellen') ? '1' : '0');
        Setting::set(KiClient::VERLAUF, (string) max(0, min(50, (int) $request->input('verlauf', KiClient::STANDARD_VERLAUF))));
        \Illuminate\Support\Facades\Cache::forget('ekkon-ki-spezialanwendung-'.trim((string) $request->input('spezialanwendung', '')));
        Setting::set(KiClient::SYSTEM, trim((string) ($daten['system'] ?? '')) ?: KiClient::STANDARD_SYSTEM);
        Setting::set(KiClient::AKTIV, $request->boolean('aktiv') ? '1' : '0');
        Setting::set(KiClient::GRUPPEN_NUR_ERWAEHNT, $request->boolean('gruppen_nur_erwaehnt') ? '1' : '0');

        if ($request->boolean('aktiv') && ! (new KiClient)->aktiv()) {
            return back()->withErrors(['ki' => 'KI eingeschaltet, aber ohne Schlüssel oder Modell antwortet sie nicht.']);
        }

        return back()->with('status', 'KI-Einstellungen gespeichert.');
    }

    /** Modelle des Anbieters für die Auswahl – JSON, per Fetch aus der Maske. */
    public function kiModelle(): JsonResponse
    {
        try {
            return response()->json(['modelle' => (new KiClient)->modelle()]);
        } catch (\RuntimeException $e) {
            return response()->json(['fehler' => $e->getMessage()], 502);
        }
    }

    /** Spezialanwendungen des API-Keys – JSON für die Auswahl. */
    public function kiSpezialanwendungen(): JsonResponse
    {
        try {
            return response()->json(['spezialanwendungen' => (new KiClient)->spezialanwendungen()]);
        } catch (\RuntimeException $e) {
            return response()->json(['fehler' => $e->getMessage()], 502);
        }
    }

    /** Dokumentenordner des API-Keys – JSON für die Auswahl. */
    public function kiOrdner(): JsonResponse
    {
        try {
            return response()->json(['ordner' => (new KiClient)->dokumentenordner()]);
        } catch (\RuntimeException $e) {
            return response()->json(['fehler' => $e->getMessage()], 502);
        }
    }

    /** Probefrage an die KI – zeigt Antwort oder Fehler direkt in der Maske. */
    public function kiTest(Request $request): RedirectResponse
    {
        $frage = trim((string) $request->input('frage', 'Antworte mit einem Satz: Funktioniert die Verbindung?'));

        try {
            // Mit dem angemeldeten Konto – so zeigt die Probefrage dasselbe Wissen wie im Chat.
            $a = (new KiClient)->antworte([['role' => 'user', 'content' => $frage !== '' ? $frage : 'Hallo']], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ki' => 'KI-Test fehlgeschlagen: '.$e->getMessage()]);
        }

        $wissen = ($a['wissen'] ?? []) === [] ? 'kein Wissen mitgegeben' : count($a['wissen']).' Wissenstreffer: '.implode(' | ', $a['wissen']);

        return back()->with('status', 'KI antwortet ('.$a['modell'].'; '.$wissen.'; Wissen wie für '.$request->user()?->name.'): '.mb_substr($a['text'], 0, 300));
    }

    public function destroy(Request $request, TeamsNachricht $nachricht): RedirectResponse|JsonResponse
    {
        $nachricht->delete();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
