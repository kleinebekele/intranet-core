<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $geaendert = array_intersect_key($request->user()->getDirty(), array_flip(['name', 'email']));
        $vorher = array_intersect_key($request->user()->getOriginal(), $geaendert);

        $request->user()->save();

        if ($geaendert !== []) {
            Audit::schreiben('profil.geaendert', 'Geändert: '.implode(', ', array_keys($geaendert)).'.', $request->user(), [
                'vorher' => $vorher,
                'nachher' => $geaendert,
            ]);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Audit::schreiben('benutzer.geloescht', "Eigenes Konto {$user->email} gelöscht.", $user, ['email' => $user->email], akteur: $user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
