<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\Profilbild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Darstellung (hell/dunkel/wie System) speichern.
     */
    public function darstellung(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'farbschema' => ['required', 'in:hell,dunkel,system'],
        ]);

        $request->user()->forceFill(['farbschema' => $daten['farbschema']])->save();

        return Redirect::route('profile.edit')->with('status', 'Darstellung gespeichert.');
    }

    /**
     * Profilbild hochladen oder entfernen.
     */
    public function profilbild(Request $request): RedirectResponse
    {
        if ($request->boolean('entfernen')) {
            Profilbild::entfernen($request->user());

            return Redirect::route('profile.edit')->with('status', 'Profilbild entfernt.');
        }

        $request->validate([
            'profilbild' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);

        Profilbild::speichern($request->user(), $request->file('profilbild'));

        return Redirect::route('profile.edit')->with('status', 'Profilbild gespeichert.');
    }

    /**
     * Profilbild ausliefern – nur an angemeldete Benutzer, die Datei liegt privat.
     */
    public function profilbildZeigen(User $user): StreamedResponse
    {
        abort_unless($user->profilbild && Storage::disk('local')->exists($user->profilbild), 404);

        return Storage::disk('local')->response($user->profilbild, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
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

        Profilbild::entfernen($user);
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
