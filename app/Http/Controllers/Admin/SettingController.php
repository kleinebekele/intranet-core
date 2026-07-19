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
    /** Ablageort des hochgeladenen Favicons auf der `public`-Disk. */
    private const FAVICON_ORDNER = 'branding';

    public function index(): View
    {
        return view('admin.settings.index', [
            'haupttitel' => Setting::get('haupttitel', ''),
            'haupttitelStandard' => config('app.name', 'Intranet'),
            'faviconPfad' => Setting::get('favicon'),
            'stundenlimit' => Setting::get('mail_stundenlimit', ''),
            'stundenlimitEnv' => (int) env('MAIL_STUNDENLIMIT', 0),
            'outboxAktiv' => (bool) config('mail.outbox.aktiv', true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'haupttitel' => ['nullable', 'string', 'max:60'],
            // 0/leer = kein Limit. Obergrenze nur als Tippfehler-Bremse.
            'mail_stundenlimit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg,jpg,jpeg,webp', 'max:512'],
            'favicon_entfernen' => ['nullable', 'boolean'],
        ], [
            'favicon.mimes' => 'Als Favicon sind PNG, ICO, SVG, JPG oder WebP möglich.',
            'favicon.max' => 'Das Favicon darf höchstens 512 KB groß sein.',
        ]);

        Setting::set('haupttitel', trim((string) ($daten['haupttitel'] ?? '')));
        Setting::set('mail_stundenlimit', (string) ($daten['mail_stundenlimit'] ?? ''));

        if ($request->boolean('favicon_entfernen')) {
            $this->faviconLoeschen();
            Setting::vergessen('favicon');
        }

        if ($request->hasFile('favicon')) {
            // Altes zuerst weg, sonst sammeln sich unbenutzte Dateien an.
            $this->faviconLoeschen();

            $pfad = $request->file('favicon')->store(self::FAVICON_ORDNER, 'public');
            Setting::set('favicon', $pfad);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Einstellungen gespeichert.');
    }

    private function faviconLoeschen(): void
    {
        if ($alt = Setting::get('favicon')) {
            Storage::disk('public')->delete($alt);
        }
    }
}
