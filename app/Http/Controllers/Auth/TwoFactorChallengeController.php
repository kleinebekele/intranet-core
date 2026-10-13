<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use App\Support\TwoFactorMailCode;
use App\Support\TwoFactorTrust;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Die Zwei-Faktor-Abfrage nach dem Passwort-Login: TOTP für Benutzer mit
 * eingerichteter Authenticator-App, sonst Einmalcode per E-Mail.
 */
class TwoFactorChallengeController extends Controller
{
    /** Fehlversuche mit der Authenticator-App, bis die Abfrage pausiert. */
    private const TOTP_VERSUCHE = 5;

    /** So lange pausiert sie danach (Sekunden). */
    private const TOTP_SPERRE = 900;

    public function __construct(
        private readonly TwoFactorMailCode $mailCode,
        private readonly TwoFactorTrust $trust,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($request->session()->get('two_factor_passed') === true || ! $user->needsTwoFactor()) {
            return redirect()->route('dashboard');
        }

        $usesTotp = $user->hasTotp();

        if (! $usesTotp) {
            $this->mailCode->sendIfMissing($user);
        }

        return view('auth.two-factor-challenge', [
            'usesTotp' => $usesTotp,
            'totpApp' => $usesTotp ? $user->totp_app : null,
            'canResend' => ! $usesTotp && $this->mailCode->canResend($user),
            'rememberDays' => $this->trust->enabled() ? $this->trust->days() : 0,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        $valid = $user->hasTotp()
            ? $this->totpPruefen($user, $request->input('code'))
            : $this->mailCode->verify($user, $request->input('code'));

        if ($valid === null) {
            $minuten = (int) ceil(RateLimiter::availableIn($this->totpSchluessel($user)) / 60);

            return back()->withErrors(['code' => "Zu viele falsche Codes. Bitte warte {$minuten} Minuten."]);
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'Der Code ist ungültig oder abgelaufen.']);
        }

        $request->session()->put('two_factor_passed', true);

        if ($request->boolean('remember_device')) {
            $this->trust->remember($user);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * TOTP mit Versuchszähler je Benutzer und Replay-Schutz: Ein Code gilt
     * nur einmal, auch innerhalb seines 30-Sekunden-Fensters.
     *
     * @return bool|null null = gesperrt (zu viele Fehlversuche)
     */
    private function totpPruefen(User $user, string $code): ?bool
    {
        $schluessel = $this->totpSchluessel($user);

        if (RateLimiter::tooManyAttempts($schluessel, self::TOTP_VERSUCHE)) {
            return null;
        }

        $schritt = Totp::zeitschritt($user->totp_secret, $code);
        $zuletzt = Cache::get($schluessel.':zuletzt');

        if ($schritt === null || ($zuletzt !== null && $schritt <= $zuletzt)) {
            RateLimiter::hit($schluessel, self::TOTP_SPERRE);

            return false;
        }

        Cache::put($schluessel.':zuletzt', $schritt, 300);
        RateLimiter::clear($schluessel);

        return true;
    }

    private function totpSchluessel(User $user): string
    {
        return "two-factor:totp:{$user->id}";
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
