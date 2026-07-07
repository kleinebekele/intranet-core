<?php

namespace App\Http\Controllers;

use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * TOTP-Verwaltung im eigenen Profil: einrichten (Secret + QR anzeigen),
 * mit erstem Code bestätigen, wieder entfernen.
 */
class TwoFactorController extends Controller
{
    /** Schritt 1: neues Secret erzeugen und zur Bestätigung anzeigen. */
    public function setup(Request $request): RedirectResponse
    {
        $request->session()->put('totp_pending_secret', Totp::generateSecret());

        return redirect()->route('profile.edit')->withFragment('two-factor');
    }

    /** Schritt 2: erst wenn ein gültiger Code eintrifft, wird TOTP aktiv. */
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
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('totp_pending_secret');

        return redirect()->route('profile.edit')->with('status', 'totp-confirmed');
    }

    /** Einrichtung abbrechen (verwirft das unbestätigte Secret). */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('totp_pending_secret');

        return redirect()->route('profile.edit');
    }

    /** TOTP entfernen → zurück zum Mail-Code (Passwort als Bestätigung). */
    public function disable(Request $request): RedirectResponse
    {
        $request->validateWithBag('totp', ['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'totp_secret' => null,
            'totp_confirmed_at' => null,
        ])->save();

        return redirect()->route('profile.edit')->with('status', 'totp-disabled');
    }
}
