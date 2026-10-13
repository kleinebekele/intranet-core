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

        // Die Antwort ist immer dieselbe – ob es das Konto gibt, ob es über
        // Microsoft läuft oder gerade gedrosselt ist. Sonst ließe sich über
        // dieses öffentliche Formular abfragen, wer ein Konto hat.
        //
        // Konten, die über Microsoft laufen, bekommen keinen Link: Ein neues
        // Passwort nützte ihnen nichts. Den Hinweis darauf zeigt das Formular
        // allen (siehe auth/forgot-password).
        $benutzer = User::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $request->input('email'))])->first();

        if (! ($benutzer?->nurUeberMicrosoft() && app(MicrosoftSso::class)->aktiv())) {
            Password::sendResetLink($request->only('email'));
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
