<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Systemeinstellungen, die Administratoren im Betrieb ändern können – je ein
 * Reiter für das Erscheinungsbild (Haupttitel, Logo, Favicon) und den
 * Mailversand (Stundenlimit des Ausgangskorbs).
 *
 * Alles hier ist bewusst NICHT in der `.env`: Es sind Werte, die jemand ohne
 * Serverzugang ändern können soll.
 */
class SettingController
{
    /** Ablageort der hochgeladenen Bilder auf der `public`-Disk. */
    private const ORDNER = 'branding';

    public function index(): View
    {
        return view('admin.settings.index', [
            'haupttitel' => Setting::get('haupttitel', ''),
            'haupttitelStandard' => config('app.name', 'Intranet'),
            'logoPfad' => Setting::get('logo'),
            'faviconPfad' => Setting::get('favicon'),
        ]);
    }

    /** Eigener Reiter „Mailversand": das Stundenlimit des Ausgangskorbs. */
    public function mailversand(): View
    {
        return view('admin.settings.mailversand', [
            'stundenlimit' => Setting::get('mail_stundenlimit', ''),
            'outboxAktiv' => (bool) config('mail.outbox.aktiv', true),
        ]);
    }

    public function mailversandSpeichern(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            // 0/leer = kein Limit. Obergrenze nur als Tippfehler-Bremse.
            'mail_stundenlimit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        Setting::set('mail_stundenlimit', (string) ($daten['mail_stundenlimit'] ?? ''));

        Audit::schreiben('einstellungen.gespeichert', null, daten: [
            'mail_stundenlimit' => $daten['mail_stundenlimit'] ?? '',
        ]);

        return redirect()
            ->route('admin.settings.mailversand')
            ->with('status', 'Mailversand gespeichert.');
    }

    public function update(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'haupttitel' => ['nullable', 'string', 'max:60'],
            // Logo steht in der Kopfzeile, darf also etwas größer sein als das Favicon.
            'logo' => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:1024', $this->svgOhneSkript(...)],
            'logo_entfernen' => ['nullable', 'boolean'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg,jpg,jpeg,webp', 'max:512', $this->svgOhneSkript(...)],
            'favicon_entfernen' => ['nullable', 'boolean'],
        ], [
            'logo.mimes' => 'Als Logo sind PNG, SVG, JPG oder WebP möglich.',
            'logo.max' => 'Das Logo darf höchstens 1 MB groß sein.',
            'favicon.mimes' => 'Als Favicon sind PNG, ICO, SVG, JPG oder WebP möglich.',
            'favicon.max' => 'Das Favicon darf höchstens 512 KB groß sein.',
        ]);

        Setting::set('haupttitel', trim((string) ($daten['haupttitel'] ?? '')));

        $this->bildVerarbeiten($request, 'logo');
        $this->bildVerarbeiten($request, 'favicon');

        Audit::schreiben('einstellungen.gespeichert', null, daten: [
            'haupttitel' => $daten['haupttitel'] ?? '',
            'logo' => $request->hasFile('logo') ? 'neu' : ($request->boolean('logo_entfernen') ? 'entfernt' : 'unverändert'),
            'favicon' => $request->hasFile('favicon') ? 'neu' : ($request->boolean('favicon_entfernen') ? 'entfernt' : 'unverändert'),
        ]);

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Erscheinungsbild gespeichert.');
    }

    /**
     * Ein SVG ist ein Dokument, kein Bild: Direkt aufgerufen führt der Browser
     * darin enthaltene Skripte im Namen des Intranets aus. Logo und Favicon
     * liegen öffentlich – deshalb nur SVGs ohne aktive Inhalte annehmen.
     */
    private function svgOhneSkript(string $feld, mixed $datei, \Closure $fehler): void
    {
        if (! $datei instanceof UploadedFile || strtolower($datei->getClientOriginalExtension()) !== 'svg') {
            return;
        }

        $inhalt = (string) file_get_contents($datei->getRealPath());

        // Skripte, Ereignis-Attribute, eingebettete Fremdinhalte, Entitäten und
        // Verweise nach außen (nur Sprungmarken #id bleiben erlaubt).
        $aktiv = '/<\s*(script|foreignObject|iframe|embed|object)\b|\son[a-z]+\s*=|javascript:|<!ENTITY|href\s*=\s*["\']\s*(?!#)/i';

        if (preg_match($aktiv, $inhalt)) {
            $fehler('Dieses SVG enthält Skripte, Verweise oder eingebettete Inhalte. Bitte als PNG hochladen oder ein schlichtes SVG verwenden.');
        }
    }

    /**
     * Ein hochgeladenes Bild ablegen bzw. entfernen.
     *
     * Das alte Bild wird in beiden Fällen gelöscht – sonst sammeln sich mit
     * jedem Austausch unbenutzte Dateien im Ablageordner an.
     */
    private function bildVerarbeiten(Request $request, string $schluessel): void
    {
        $entfernen = $request->boolean($schluessel.'_entfernen');
        $neu = $request->hasFile($schluessel);

        if (! $entfernen && ! $neu) {
            return;
        }

        if ($alt = Setting::get($schluessel)) {
            Storage::disk('public')->delete($alt);
        }

        if ($neu) {
            Setting::set($schluessel, $request->file($schluessel)->store(self::ORDNER, 'public'));
        } else {
            Setting::vergessen($schluessel);
        }
    }
}
