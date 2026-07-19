<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Einstellungen, die Administratoren im Betrieb ändern können – Erscheinungsbild
 * (Haupttitel, Favicon) und Betriebsgrenzen (Mail-Stundenlimit).
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
            'stundenlimit' => Setting::get('mail_stundenlimit', ''),
            'outboxAktiv' => (bool) config('mail.outbox.aktiv', true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'haupttitel' => ['nullable', 'string', 'max:60'],
            // 0/leer = kein Limit. Obergrenze nur als Tippfehler-Bremse.
            'mail_stundenlimit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            // Logo steht in der Kopfzeile, darf also etwas größer sein als das Favicon.
            'logo' => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:1024'],
            'logo_entfernen' => ['nullable', 'boolean'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg,jpg,jpeg,webp', 'max:512'],
            'favicon_entfernen' => ['nullable', 'boolean'],
        ], [
            'logo.mimes' => 'Als Logo sind PNG, SVG, JPG oder WebP möglich.',
            'logo.max' => 'Das Logo darf höchstens 1 MB groß sein.',
            'favicon.mimes' => 'Als Favicon sind PNG, ICO, SVG, JPG oder WebP möglich.',
            'favicon.max' => 'Das Favicon darf höchstens 512 KB groß sein.',
        ]);

        Setting::set('haupttitel', trim((string) ($daten['haupttitel'] ?? '')));
        Setting::set('mail_stundenlimit', (string) ($daten['mail_stundenlimit'] ?? ''));

        $this->bildVerarbeiten($request, 'logo');
        $this->bildVerarbeiten($request, 'favicon');

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Einstellungen gespeichert.');
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
