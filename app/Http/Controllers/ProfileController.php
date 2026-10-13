<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Mail\Vorlagen\VorlagenMailer;
use App\Models\User;
use App\Support\Audit;
use App\Support\Profilbild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /** Wie lange der Bestätigungslink für eine neue E-Mail gilt. */
    public const EMAIL_LINK_MINUTEN = 60;

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
        $user = $request->user();
        $user->fill(['name' => $request->validated('name')]);

        $geaendert = array_intersect_key($user->getDirty(), array_flip(['name']));
        $vorher = array_intersect_key($user->getOriginal(), $geaendert);

        $user->save();

        if ($geaendert !== []) {
            Audit::schreiben('profil.geaendert', 'Geändert: '.implode(', ', array_keys($geaendert)).'.', $user, [
                'vorher' => $vorher,
                'nachher' => $geaendert,
            ]);
        }

        if (! $request->emailGeaendert()) {
            return Redirect::route('profile.edit')->with('status', 'profile-updated');
        }

        // Die E-Mail ist an mehreren Stellen ein Ausweis (Nextcloud-/Linear-
        // Abgleich, Absenderfreigabe, Passwort vergessen). Deshalb gilt sie erst,
        // wenn der Link an genau diese Adresse angeklickt wurde.
        $schluessel = 'email-aendern:'.$user->id;

        if (RateLimiter::tooManyAttempts($schluessel, 5)) {
            return Redirect::route('profile.edit')->withErrors([
                'email' => 'Zu viele Änderungen in kurzer Zeit. Bitte versuche es in einer Stunde noch einmal.',
            ]);
        }

        RateLimiter::hit($schluessel, 3600);

        $neu = $request->validated('email');
        $token = Str::random(40);

        $user->forceFill([
            'email_neu' => $neu,
            'email_neu_token' => hash('sha256', $token),
            'email_neu_am' => now(),
        ])->save();

        app(VorlagenMailer::class)->senden('email_bestaetigen', $neu, [
            'name' => $user->name,
            'neue_mail' => $neu,
            'link' => url(route('profile.email.bestaetigen', ['token' => $token], false)),
            'minuten' => self::EMAIL_LINK_MINUTEN,
        ]);

        Audit::schreiben('profil.email_angefragt', "Neue Adresse {$neu} angefragt, wartet auf Bestätigung.", $user, [
            'vorher' => $user->email,
            'neu' => $neu,
        ]);

        return Redirect::route('profile.edit')->with('status', "Wir haben einen Bestätigungslink an {$neu} geschickt. Erst nach dem Klick darauf gilt die neue Adresse.");
    }


    /**
     * Link aus der Bestätigungsmail: übernimmt die wartende Adresse.
     */
    public function emailBestaetigen(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        $gueltig = $user->email_neu
            && $user->email_neu_token
            && hash_equals($user->email_neu_token, hash('sha256', $token))
            && $user->email_neu_am?->gt(now()->subMinutes(self::EMAIL_LINK_MINUTEN));

        if (! $gueltig) {
            return Redirect::route('profile.edit')->withErrors([
                'email' => 'Der Bestätigungslink ist ungültig oder abgelaufen. Trag die neue Adresse bitte noch einmal ein.',
            ]);
        }

        $neu = $user->email_neu;
        $alt = $user->email;

        $vergeben = User::whereRaw('LOWER(email) = ?', [mb_strtolower($neu)])
            ->whereKeyNot($user->id)
            ->exists();

        if ($vergeben) {
            $this->emailAnfrageVerwerfen($user);

            return Redirect::route('profile.edit')->withErrors([
                'email' => 'Diese Adresse gehört inzwischen zu einem anderen Konto.',
            ]);
        }

        $user->forceFill([
            'email' => $neu,
            'email_verified_at' => now(),
            'email_neu' => null,
            'email_neu_token' => null,
            'email_neu_am' => null,
        ])->save();

        Audit::schreiben('profil.geaendert', 'Geändert: email (bestätigt).', $user, [
            'vorher' => ['email' => $alt],
            'nachher' => ['email' => $neu],
        ]);

        // Hinweis an die bisherige Adresse – falls jemand anderes am Werk war.
        app(VorlagenMailer::class)->senden('login_geaendert', $alt, [
            'name' => $user->name,
            'neue_mail' => $neu,
        ]);

        return Redirect::route('profile.edit')->with('status', "Deine Anmelde-Adresse ist jetzt {$neu}.");
    }

    /**
     * Wartende Adressänderung zurückziehen.
     */
    public function emailAbbrechen(Request $request): RedirectResponse
    {
        $this->emailAnfrageVerwerfen($request->user());

        return Redirect::route('profile.edit')->with('status', 'Die Adressänderung wurde zurückgezogen.');
    }

    private function emailAnfrageVerwerfen(User $user): void
    {
        $user->forceFill([
            'email_neu' => null,
            'email_neu_token' => null,
            'email_neu_am' => null,
        ])->save();
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
