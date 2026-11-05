<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        // Abgelaufene oder schon benutzte Links gleich erkennen: Statt erst nach dem
        // Eintippen des Passworts zu scheitern, zeigt die Seite dann nur den Knopf
        // „Neuen Link senden" (wie „Passwort vergessen", Adresse schon eingetragen).
        $token = (string) $request->route('token');
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $request->email)])->first();
        $gueltig = $user !== null && (Password::broker()->tokenExists($user, $token)
            || Password::broker('einladungen')->tokenExists($user, $token));

        return view('auth.reset-password', ['request' => $request, 'abgelaufen' => ! $gueltig]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $daten = $request->only('email', 'password', 'password_confirmation', 'token');

        $setzen = function (User $user) use ($request) {
            $user->forceFill([
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
            ])->save();

            // Ein offener Link des jeweils anderen Weges ist damit erledigt.
            Password::broker()->deleteToken($user);
            Password::broker('einladungen')->deleteToken($user);

            event(new PasswordReset($user));
        };

        // Dieselbe Seite dient „Passwort vergessen" (kurz gültig) und dem
        // Einladungslink (Broker "einladungen", tagelang gültig).
        $status = Password::reset($daten, $setzen);

        if ($status === Password::INVALID_TOKEN) {
            $status = Password::broker('einladungen')->reset($daten, $setzen);
        }

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
