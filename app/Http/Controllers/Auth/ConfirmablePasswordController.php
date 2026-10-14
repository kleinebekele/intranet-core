<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\PasskeyFehler;
use App\Support\Passkeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Besonders geschützte Bereiche: vor dem Betreten das Passwort bestätigen –
 * oder, wenn hinterlegt, den Passkey (Gerät + Gesicht/Finger/PIN, mindestens
 * so stark wie das Passwort).
 */
class ConfirmablePasswordController extends Controller
{
    public function __construct(private readonly Passkeys $passkeys) {}

    /**
     * Show the confirm password view.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('auth.confirm-password', [
            // Hat er einen Passkey, wird der als Erstes angeboten.
            'mitPasskey' => $this->passkeys->erlaubt($user) && $user->passkeys()->exists(),
        ]);
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Passkey-Abfrage vorbereiten – nur für die Passkeys des angemeldeten Benutzers. */
    public function passkeyOptionen(Request $request): JsonResponse
    {
        return response()->json($this->passkeys->anmeldenOptionen($request, $request->user()));
    }

    /** Passkey-Unterschrift prüfen; gilt dann wie ein bestätigtes Passwort. */
    public function passkeyBestaetigen(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $passkey = $this->passkeys->anmelden($request, (array) $request->input('antwort'));
        } catch (PasskeyFehler $fehler) {
            Audit::schreiben('passwort.bestaetigung_fehlgeschlagen', 'Passkey: '.$fehler->getMessage(), $user);

            return response()->json(['meldung' => $fehler->getMessage()], 422);
        }

        // anmeldenOptionen() hat schon auf seine Passkeys beschränkt – doppelt
        // hält besser: ein fremder Passkey bestätigt nichts.
        if ($passkey->user_id !== $user->id) {
            return response()->json(['meldung' => 'Dieser Passkey gehört nicht zu deinem Konto.'], 422);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'weiter' => redirect()->intended(route('dashboard', absolute: false))->getTargetUrl(),
        ]);
    }
}
