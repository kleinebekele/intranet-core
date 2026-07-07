<?php

namespace App\Http\Controllers;

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

        return redirect()->route('profile.edit')->with('status', 'two-factor-enabled');
    }

    /** 2FA komplett abschalten (nur wenn nicht per FORCE_2FA erzwungen). */
    public function disable(Request $request): RedirectResponse
    {
        if (config('intranet.two_factor_forced')) {
            return redirect()->route('profile.edit');
        }

        $request->validateWithBag('totp', ['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_enabled' => false,
            'totp_secret' => null,
            'totp_confirmed_at' => null,
        ])->save();

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
        $request->validate(['code' => ['required', 'string']]);

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
        ])->save();

        $request->session()->forget('totp_pending_secret');

        return redirect()->route('profile.edit')->with('status', 'totp-confirmed');
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
        ])->save();

        return redirect()->route('profile.edit')->with('status', 'totp-disabled');
    }
}
