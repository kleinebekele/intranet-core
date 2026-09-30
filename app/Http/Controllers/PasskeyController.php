<?php

namespace App\Http\Controllers;

use App\Models\Passkey;
use App\Support\Audit;
use App\Support\PasskeyFehler;
use App\Support\Passkeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Passkeys im eigenen Profil anlegen und entfernen.
 *
 * Anlegen verlangt das aktuelle Passwort: Wer nur eine fremde Sitzung erwischt
 * hat, soll sich darüber keinen dauerhaften Zugang einrichten können. Ausnahme
 * sind die ersten Minuten nach der Passwort-Anmeldung (Angebot nach dem Login).
 */
class PasskeyController extends Controller
{
    public function __construct(private readonly Passkeys $passkeys) {}

    /** Schritt 1: Passwort prüfen, Optionen für das Gerät ausgeben. */
    public function optionen(Request $request): JsonResponse
    {
        // Wie auf der Anmeldeseite: Microsoft-Konten melden sich nur dort an.
        abort_unless($this->passkeys->erlaubt($request->user()), 403);

        // Direkt nach der Passwort-Anmeldung (Angebot) nicht erneut fragen.
        // Von Hand statt validate(): Das würde außerhalb von api/* per
        // Redirect antworten, das Skript braucht aber JSON.
        if (! $this->passkeys->passwortFrisch($request)
            && ! Hash::check((string) $request->input('password'), $request->user()->password)) {
            return response()->json(['meldung' => 'Das Passwort stimmt nicht.'], 422);
        }

        return response()->json($this->passkeys->anlegenOptionen($request, $request->user()));
    }

    /** Schritt 2: Antwort des Geräts prüfen und speichern. */
    public function speichern(Request $request): JsonResponse
    {
        $name = mb_substr(trim((string) $request->input('name')), 0, 100) ?: 'Passkey vom '.now()->format('d.m.Y');

        try {
            $passkey = $this->passkeys->anlegen($request, $request->user(), (array) $request->input('antwort'), $name);
        } catch (PasskeyFehler $fehler) {
            return response()->json(['meldung' => 'Der Passkey konnte nicht gespeichert werden: '.$fehler->getMessage()], 422);
        }

        Audit::schreiben('passkey.angelegt', $passkey->name, $request->user());

        // "Ersetzen": die übrigen Passkeys des Kontos entfernen – für den Fall,
        // dass der alte nicht mehr passte und man aufräumen will.
        $entfernt = 0;
        if ($request->boolean('ersetzen')) {
            $request->user()->passkeys()->whereKeyNot($passkey->getKey())->get()
                ->each(function (Passkey $alt) use ($request, &$entfernt) {
                    $alt->delete();
                    Audit::schreiben('passkey.entfernt', $alt->name.' (ersetzt)', $request->user());
                    $entfernt++;
                });
        }

        $this->passkeys->geraetMerken($request->user());
        $request->session()->flash('status', 'Passkey angelegt ('.$passkey->name.')'
            .($entfernt > 0 ? ', '.$entfernt.' alte(r) entfernt' : '')
            .' – ab jetzt kannst du dich damit anmelden.');

        return response()->json(['ok' => true]);
    }

    /** "Später": eine Woche lang nicht mehr fragen. */
    public function angebotSpaeter(Request $request): JsonResponse
    {
        $request->user()->forceFill(['passkey_angebot_pause_bis' => now()->addWeek()])->save();

        return response()->json(['ok' => true]);
    }

    /** "Nicht mehr fragen": das Angebot nach dem Login dauerhaft ausblenden. */
    public function angebotAus(Request $request): JsonResponse
    {
        $request->user()->forceFill(['passkey_angebot_aus_am' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function entfernen(Request $request, Passkey $passkey): RedirectResponse
    {
        abort_unless($passkey->user_id === $request->user()->id, 404);

        $passkey->delete();
        Audit::schreiben('passkey.entfernt', $passkey->name, $request->user());

        return redirect()->route('profile.edit')->withFragment('passkeys')->with('status', 'Passkey entfernt ('.$passkey->name.').');
    }
}
