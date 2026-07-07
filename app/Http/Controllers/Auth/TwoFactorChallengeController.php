<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use App\Support\TwoFactorMailCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Die Zwei-Faktor-Abfrage nach dem Passwort-Login: TOTP für Benutzer mit
 * eingerichteter Authenticator-App, sonst Einmalcode per E-Mail.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorMailCode $mailCode)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('two_factor_passed') === true || ! config('intranet.two_factor')) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $usesTotp = $user->hasTotp();

        if (! $usesTotp) {
            $this->mailCode->sendIfMissing($user);
        }

        return view('auth.two-factor-challenge', [
            'usesTotp' => $usesTotp,
            'canResend' => ! $usesTotp && $this->mailCode->canResend($user),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        $valid = $user->hasTotp()
            ? Totp::verify($user->totp_secret, $request->input('code'))
            : $this->mailCode->verify($user, $request->input('code'));

        if (! $valid) {
            return back()->withErrors(['code' => 'Der Code ist ungültig oder abgelaufen.']);
        }

        $request->session()->put('two_factor_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTotp()) {
            return redirect()->route('two-factor.challenge');
        }

        if (! $this->mailCode->canResend($user)) {
            return back()->withErrors(['code' => 'Bitte warte kurz, bevor du einen neuen Code anforderst.']);
        }

        $this->mailCode->send($user);

        return back()->with('status', 'two-factor-code-sent');
    }
}
