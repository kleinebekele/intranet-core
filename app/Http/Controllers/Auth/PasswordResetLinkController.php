<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Microsoft\MicrosoftSso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Konten, die über Microsoft laufen, würde ein neues Passwort nichts
        // nützen: Die Anmeldung damit wird ohnehin abgewiesen. Statt sie in
        // diese Sackgasse laufen zu lassen, sagen wir es gleich.
        $benutzer = User::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $request->input('email'))])->first();

        if ($benutzer?->nurUeberMicrosoft() && app(MicrosoftSso::class)->aktiv()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => trans('auth.nur_microsoft')]);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
