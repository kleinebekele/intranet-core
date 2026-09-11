<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailKonto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Mime\Email;

/**
 * SMTP-Absender verwalten: eigene Postfächer mit eigenem Server-Zugang,
 * die Module (z. B. der Newsletter) je Mail auswählen können.
 *
 * Das Passwort wird verschlüsselt gespeichert und nie wieder angezeigt; beim
 * Bearbeiten bedeutet ein leeres Passwortfeld „unverändert lassen".
 */
class MailKontoController extends Controller
{
    public function index(): View
    {
        return view('admin.mail.konten.index', [
            'konten' => MailKonto::orderBy('bezeichnung')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.mail.konten.form', [
            'konto' => new MailKonto(['port' => 587, 'verschluesselung' => 'tls', 'aktiv' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $konto = MailKonto::create($this->daten($request));

        return redirect()->route('admin.mail.konten.index')
            ->with('status', "SMTP-Absender „{$konto->bezeichnung}\" angelegt.");
    }

    public function edit(MailKonto $konto): View
    {
        return view('admin.mail.konten.form', compact('konto'));
    }

    public function update(Request $request, MailKonto $konto): RedirectResponse
    {
        $daten = $this->daten($request);

        // Leeres Passwort = altes behalten.
        if (($daten['passwort'] ?? '') === '') {
            unset($daten['passwort']);
        }

        $konto->update($daten);

        return redirect()->route('admin.mail.konten.index')
            ->with('status', "SMTP-Absender „{$konto->bezeichnung}\" gespeichert.");
    }

    public function destroy(MailKonto $konto): RedirectResponse
    {
        $bezeichnung = $konto->bezeichnung;
        $konto->delete();

        return redirect()->route('admin.mail.konten.index')
            ->with('status', "SMTP-Absender „{$bezeichnung}\" gelöscht. Noch wartende Mails über dieses Konto bleiben im Ausgangskorb liegen.");
    }

    /**
     * Verbindung prüfen: eine kurze Testmail an den angemeldeten Admin – direkt
     * über den Transport des Kontos, am Ausgangskorb vorbei, damit die Antwort
     * des Servers sofort sichtbar ist.
     */
    public function testmail(Request $request, MailKonto $konto): RedirectResponse
    {
        $an = (string) $request->user()->email;

        try {
            $email = (new Email)
                ->from(new \Symfony\Component\Mime\Address($konto->absender_mail, (string) ($konto->absender_name ?? '')))
                ->to($an)
                ->subject('[TEST] SMTP-Absender „'.$konto->bezeichnung.'"')
                ->text("Diese Testmail kam über das Konto „{$konto->bezeichnung}\" ({$konto->host}:{$konto->port}). Wenn du sie liest, stimmt der Zugang.");

            if (filled($konto->antwort_an)) {
                $email->replyTo($konto->antwort_an);
            }

            Mail::mailer($konto->registrieren())->getSymfonyTransport()->send($email);
        } catch (\Throwable $e) {
            return back()->with('error', 'Testmail fehlgeschlagen: '.$e->getMessage());
        }

        return back()->with('status', "Testmail über „{$konto->bezeichnung}\" an {$an} verschickt.");
    }

    /** @return array<string, mixed> */
    private function daten(Request $request): array
    {
        $daten = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:120'],
            'absender_mail' => ['required', 'email', 'max:191'],
            'absender_name' => ['nullable', 'string', 'max:120'],
            'antwort_an' => ['nullable', 'email', 'max:191'],
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'verschluesselung' => ['required', Rule::in(array_keys(MailKonto::VERSCHLUESSELUNGEN))],
            'benutzername' => ['nullable', 'string', 'max:191'],
            'passwort' => ['nullable', 'string', 'max:255'],
            'aktiv' => ['nullable', 'boolean'],
        ]);

        $daten['aktiv'] = $request->boolean('aktiv');
        $daten['absender_name'] = trim((string) ($daten['absender_name'] ?? '')) ?: null;
        $daten['antwort_an'] = trim((string) ($daten['antwort_an'] ?? '')) ?: null;
        $daten['benutzername'] = trim((string) ($daten['benutzername'] ?? '')) ?: null;

        return $daten;
    }
}
