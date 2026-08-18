<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MicrosoftAnmeldung;
use App\Models\Role;
use App\Models\User;
use App\Support\Microsoft\MicrosoftProfil;
use App\Support\Microsoft\MicrosoftSso;
use App\Support\Microsoft\MicrosoftSsoFehler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Anmeldung mit dem Microsoft-Konto.
 *
 * Grundsatz: Das Intranet-Konto entscheidet, nicht Microsoft. Wer hier schon
 * ein Konto hat, kommt über Microsoft herein (die Zuordnung läuft beim ersten
 * Mal über die E-Mail-Adresse, danach über die unveränderliche Objekt-ID).
 * Neu angelegt wird nur, wer in einer der freigegebenen Microsoft-365-Gruppen
 * ist – ein Konto im Tenant allein reicht nicht.
 *
 * Der Passwort-Weg bleibt daneben bestehen. Das ist bewusst so: Fällt Microsoft
 * aus, kommen die Admins trotzdem noch in ihr eigenes Intranet.
 */
class MicrosoftLoginController extends Controller
{
    public function __construct(private readonly MicrosoftSso $sso) {}

    /** Schritt 1: zu Microsoft schicken. */
    public function start(Request $request): RedirectResponse
    {
        abort_unless($this->sso->aktiv(), 404);

        return redirect()->away($this->sso->startUrl($request));
    }

    /** Schritt 2: Rückkehr von Microsoft – prüfen, anmelden, protokollieren. */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->sso->aktiv(), 404);

        try {
            $profil = $this->sso->profil($request);
        } catch (MicrosoftSsoFehler $fehler) {
            $this->protokoll($request, $fehler->ergebnis, $fehler->getMessage());

            return $this->abweisen($fehler->getMessage());
        }

        $benutzer = $this->kontoSuchen($profil);

        if ($benutzer === null) {
            return $this->unbekannten($request, $profil);
        }

        if ($benutzer->istGesperrt()) {
            $this->protokoll($request, 'gesperrt', 'Das Intranet-Konto ist gesperrt.', $profil, $benutzer);

            return $this->abweisen(trans('auth.gesperrt'));
        }

        $this->kontoNachtragen($benutzer, $profil);

        $this->anmelden($request, $benutzer);

        $this->protokoll($request, 'angemeldet', null, $profil, $benutzer);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Jemand aus dem Tenant, den es im Intranet (noch) nicht gibt.
     * Angelegt wird nur, wer in einer freigegebenen Gruppe ist.
     */
    private function unbekannten(Request $request, MicrosoftProfil $profil): RedirectResponse
    {
        if (! $this->sso->darfAnlegen()) {
            $meldung = 'Für '.$profil->email.' gibt es kein Intranet-Konto. Bitte wenden Sie sich an die Verwaltung.';
            $this->protokoll($request, 'kein_konto', $meldung, $profil);

            return $this->abweisen($meldung);
        }

        // Gruppen ließen sich nicht ermitteln: Das ist ein Einrichtungsfehler,
        // kein Fall von "darf nicht". Deshalb eine eigene, deutliche Meldung –
        // sonst sucht der Admin den Fehler beim Benutzer.
        if (! $profil->gruppenBekannt) {
            $meldung = 'Die Gruppen-Zugehörigkeit konnte nicht geprüft werden. In der App-Registrierung fehlt '
                .'der Anspruch groups in der Tokenkonfiguration (oder die Berechtigung GroupMember.Read.All).';
            $this->protokoll($request, 'fehler', $meldung, $profil);

            return $this->abweisen('Die Anmeldung ist noch nicht vollständig eingerichtet. Bitte die Verwaltung informieren.');
        }

        if (! $profil->inEinerGruppe($this->sso->gruppen())) {
            $meldung = 'Nicht in einer freigegebenen Microsoft-365-Gruppe.';
            $this->protokoll($request, 'keine_gruppe', $meldung, $profil);

            return $this->abweisen('Für '.$profil->email.' ist kein Zugang zum Intranet freigegeben.');
        }

        $benutzer = $this->kontoAnlegen($profil);

        $this->anmelden($request, $benutzer);

        $this->protokoll($request, 'neu_angelegt', null, $profil, $benutzer);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Das passende Intranet-Konto finden: erst über die Microsoft-Objekt-ID,
     * beim ersten Mal über die E-Mail-Adresse (Groß-/Kleinschreibung egal).
     */
    private function kontoSuchen(MicrosoftProfil $profil): ?User
    {
        return User::query()->where('microsoft_id', $profil->id)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$profil->email])->first();
    }

    private function kontoAnlegen(MicrosoftProfil $profil): User
    {
        $benutzer = new User;
        $benutzer->name = $profil->name;
        $benutzer->email = $profil->email;
        // Ein Zufallspasswort, das niemand kennt: Das Konto ist damit nicht
        // über den Passwort-Weg erreichbar, kann aber jederzeit per
        // "Passwort vergessen" freigeschaltet werden.
        $benutzer->password = Str::random(64);
        $benutzer->microsoft_id = $profil->id;
        // Die Adresse hat Microsoft gerade bestätigt.
        $benutzer->email_verified_at = now();
        $benutzer->microsoft_angemeldet_am = now();
        $benutzer->save();

        foreach ($this->sso->neueRollen() as $rolle) {
            Role::firstOrCreate(['role_id' => $rolle], ['name' => $rolle]);
            $benutzer->roles()->syncWithoutDetaching([$rolle]);
        }

        return $benutzer;
    }

    /** Objekt-ID am bestehenden Konto vermerken und den Zeitpunkt festhalten. */
    private function kontoNachtragen(User $benutzer, MicrosoftProfil $profil): void
    {
        $daten = ['microsoft_angemeldet_am' => now()];

        // Nur eintragen, wenn noch nichts dasteht: Eine bereits vergebene
        // Zuordnung wird nicht stillschweigend umgehängt.
        if ($benutzer->microsoft_id === null) {
            $daten['microsoft_id'] = $profil->id;
        }

        $benutzer->forceFill($daten)->save();
    }

    private function anmelden(Request $request, User $benutzer): void
    {
        Auth::guard('web')->login($benutzer);

        $request->session()->regenerate();

        // Microsoft hat den zweiten Faktor bereits verlangt (sofern im Tenant
        // eingerichtet). Eine zweite Code-Abfrage im Intranet wäre für den
        // Benutzer nur eine Hürde ohne zusätzlichen Schutz.
        $request->session()->put('two_factor_passed', true);
        $request->session()->put(MicrosoftSso::ANGEMELDET_UEBER, true);
    }

    private function abweisen(string $meldung): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['microsoft' => $meldung]);
    }

    private function protokoll(
        Request $request,
        string $ergebnis,
        ?string $meldung = null,
        ?MicrosoftProfil $profil = null,
        ?User $benutzer = null,
    ): void {
        MicrosoftAnmeldung::create([
            'email' => $profil?->email,
            'microsoft_id' => $profil?->id,
            'name' => $profil?->name,
            'ergebnis' => $ergebnis,
            'meldung' => $meldung,
            'user_id' => $benutzer?->id,
            'ip' => $request->ip(),
        ]);
    }
}
