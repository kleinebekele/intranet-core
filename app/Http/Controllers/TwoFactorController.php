<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 2FA-Verwaltung im eigenen Profil: 2FA an-/abschalten (Opt-in je Benutzer)
 * und TOTP einrichten (Secret + QR anzeigen, mit erstem Code bestätigen)
 * bzw. wieder entfernen.
 */
class TwoFactorController extends Controller
{
    /** 2FA für das eigene Konto aktivieren (Standard-Faktor: Mail-Code). */
    public function enable(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['two_factor_enabled' => true])->save();
        Audit::schreiben('zweifaktor.aktiviert', 'Im eigenen Profil (Code per E-Mail).', $request->user());

        return redirect()->route('profile.edit')->with('status', 'two-factor-enabled');
    }

    /** 2FA komplett abschalten (nur wenn nicht erzwungen: FORCE_2FA bzw. FORCE_2FA_ADMINS). */
    public function disable(Request $request): RedirectResponse
    {
        if ($request->user()->zweiFaktorErzwungen()) {
            return redirect()->route('profile.edit');
        }

        $request->validateWithBag('totp', ['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_enabled' => false,
            'totp_secret' => null,
            'totp_confirmed_at' => null,
            'totp_app' => null,
        ])->save();
        Audit::schreiben('zweifaktor.deaktiviert', 'Im eigenen Profil.', $request->user());

        return redirect()->route('profile.edit')->with('status', 'two-factor-disabled');
    }

    /** TOTP Schritt 1: neues Secret erzeugen und zur Bestätigung anzeigen. */
    public function setup(Request $request): RedirectResponse
    {
        $request->session()->put('totp_pending_secret', Totp::generateSecret());

        return redirect()->route('profile.edit')->withFragment('two-factor');
    }

    /** TOTP Schritt 2: erst ein gültiger Code aktiviert die App (und damit 2FA). */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validateWithBag('totp', [
            'code' => ['required', 'string'],
            'app' => ['required', 'string', 'max:100'],
        ], [
            'app.required' => 'Bitte angeben, in welcher App du den Schlüssel speicherst.',
        ]);

        $secret = $request->session()->get('totp_pending_secret');

        if ($secret === null) {
            return redirect()->route('profile.edit');
        }

        if (! Totp::verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Der Code ist ungültig – bitte erneut versuchen.'], 'totp');
        }

        $request->user()->forceFill([
            'two_factor_enabled' => true,
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
            'totp_app' => trim($request->input('app')),
        ])->save();

        $request->session()->forget('totp_pending_secret');
        Audit::schreiben('zweifaktor.totp', 'Authenticator-App bestätigt.', $request->user());

        return redirect()->route('profile.edit')->with('status', 'totp-confirmed');
    }

    /** Gedankenstütze nachtragen/ändern: in welcher App liegt der Schlüssel? */
    public function updateApp(Request $request): RedirectResponse
    {
        $request->validateWithBag('totpApp', ['app' => ['required', 'string', 'max:100']]);

        $request->user()->forceFill(['totp_app' => trim($request->input('app'))])->save();

        return redirect()->route('profile.edit')->with('status', 'totp-app-saved')->withFragment('two-factor');
    }

    /** TOTP-Einrichtung abbrechen (verwirft das unbestätigte Secret). */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('totp_pending_secret');

        return redirect()->route('profile.edit');
    }

    /** Nur TOTP entfernen → 2FA bleibt an, zurück zum Mail-Code. */
    public function removeTotp(Request $request): RedirectResponse
    {
        $request->validateWithBag('totp', ['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'totp_secret' => null,
            'totp_confirmed_at' => null,
            'totp_app' => null,
        ])->save();
        Audit::schreiben('zweifaktor.totp_entfernt', 'Im eigenen Profil, zurück zum Mail-Code.', $request->user());

        return redirect()->route('profile.edit')->with('status', 'totp-disabled');
    }
}
