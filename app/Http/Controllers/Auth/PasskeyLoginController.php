<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Microsoft\MicrosoftSso;
use App\Support\PasskeyFehler;
use App\Support\Passkeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Anmeldung mit einem Passkey (Face ID, Windows Hello, Sicherheitsschlüssel).
 *
 * Das Gerät verlangt Gesicht, Finger oder PIN und besitzt den geheimen
 * Schlüssel – Besitz plus Nachweis. Deshalb gilt die Zwei-Faktor-Abfrage
 * damit als erledigt.
 */
class PasskeyLoginController extends Controller
{
    public function __construct(private readonly Passkeys $passkeys) {}

    /**
     * Gibt es zur eingegebenen Adresse einen Passkey? Dann zeigt die
     * Anmeldeseite den Knopf. Verrät, ob eine Adresse ein Konto mit Passkey
     * hat – deshalb eng gedrosselt (Route).
     */
    public function pruefen(Request $request): JsonResponse
    {
        return response()->json([
            'passkey' => $this->passkeys->benutzerMitPasskey($request->input('email')) !== null,
        ]);
    }

    public function optionen(Request $request): JsonResponse
    {
        $fuer = null;

        if (filled($request->input('email'))) {
            $fuer = $this->passkeys->benutzerMitPasskey($request->input('email'));

            if ($fuer === null) {
                return $this->abweisen('Zu dieser Adresse ist kein Passkey hinterlegt.');
            }
        }

        return response()->json($this->passkeys->anmeldenOptionen($request, $fuer));
    }

    public function anmelden(Request $request): JsonResponse
    {
        try {
            $passkey = $this->passkeys->anmelden($request, (array) $request->input('antwort'));
        } catch (PasskeyFehler $fehler) {
            Audit::schreiben('anmeldung.fehlgeschlagen', 'Passkey: '.$fehler->getMessage(), akteur: false);

            return $this->abweisen($fehler->getMessage());
        }

        $user = $passkey->user;

        if ($user->istGesperrt()) {
            Audit::schreiben('anmeldung.fehlgeschlagen', 'Passkey richtig, Konto ist gesperrt.', $user, akteur: false);

            return $this->abweisen(trans('auth.gesperrt'));
        }

        // Wie beim Passwort: Konten, die nur über Microsoft laufen, kommen auch
        // nicht per Passkey herein. Sonst bliebe nach dem Abschalten im
        // Microsoft-Konto (Austritt) ein Hintereingang offen.
        if ($user->nurUeberMicrosoft() && app(MicrosoftSso::class)->aktiv()) {
            Audit::schreiben('anmeldung.fehlgeschlagen', 'Passkey richtig, Konto meldet sich aber nur über Microsoft an.', $user, akteur: false);

            return $this->abweisen(trans('auth.nur_microsoft'));
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', true);

        return response()->json([
            'weiter' => redirect()->intended(route('dashboard', absolute: false))->getTargetUrl(),
        ]);
    }

    private function abweisen(string $meldung): JsonResponse
    {
        return response()->json(['meldung' => $meldung], 422);
    }
}
