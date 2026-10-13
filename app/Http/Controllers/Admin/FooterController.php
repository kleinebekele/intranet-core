<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterLink;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Systemeinstellungen → Footer: Links zwischen Copyright und Versionsangabe,
 * extern oder als eigene Seite mit HTML-Inhalt (Impressum, Datenschutz …).
 */
class FooterController extends Controller
{
    public function index(): View
    {
        return view('admin.footer.index', [
            'links' => FooterLink::orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.footer.form', ['link' => new FooterLink(['art' => FooterLink::INTERN, 'aktiv' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $link = FooterLink::create($this->daten($request) + [
            'position' => (int) FooterLink::max('position') + 1,
        ]);
        Audit::schreiben('footer.gespeichert', 'Footer-Link „'.$link->titel.'" angelegt.');

        return redirect()->route('admin.footer.index')->with('status', 'Link „'.$link->titel.'" angelegt.');
    }

    public function edit(FooterLink $footer): View
    {
        return view('admin.footer.form', ['link' => $footer]);
    }

    public function update(Request $request, FooterLink $footer): RedirectResponse
    {
        $footer->update($this->daten($request, $footer));
        Audit::schreiben('footer.gespeichert', 'Footer-Link „'.$footer->titel.'" geändert.');

        return redirect()->route('admin.footer.index')->with('status', 'Link „'.$footer->titel.'" gespeichert.');
    }

    public function destroy(FooterLink $footer): RedirectResponse
    {
        $footer->delete();
        Audit::schreiben('footer.geloescht', 'Footer-Link „'.$footer->titel.'" gelöscht.');

        return redirect()->route('admin.footer.index')->with('status', 'Link „'.$footer->titel.'" gelöscht.');
    }

    /** Einen Platz nach links bzw. rechts rücken (Tausch mit dem Nachbarn). */
    public function verschieben(Request $request, FooterLink $footer): RedirectResponse
    {
        $links = FooterLink::orderBy('position')->orderBy('id')->get()->values();
        $i = $links->search(fn (FooterLink $l) => $l->is($footer));
        $j = $request->input('richtung') === 'links' ? $i - 1 : $i + 1;

        if ($j >= 0 && $j < $links->count()) {
            $neu = $links->all();
            [$neu[$i], $neu[$j]] = [$neu[$j], $neu[$i]];
            foreach ($neu as $position => $link) {
                $link->update(['position' => $position + 1]);
            }
        }

        return redirect()->route('admin.footer.index');
    }

    /** @return array<string, mixed> */
    private function daten(Request $request, ?FooterLink $link = null): array
    {
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:100'],
            'art' => ['required', Rule::in([FooterLink::INTERN, FooterLink::EXTERN])],
            'url' => ['nullable', 'required_if:art,'.FooterLink::EXTERN, 'url', 'max:2000'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]*$/',
                Rule::unique('footer_links', 'slug')->ignore($link?->id)],
            'inhalt' => ['nullable', 'string'],
            'einbindung' => ['nullable', 'string', 'max:20000'],
        ], [
            'url.required_if' => 'Ein externer Link braucht eine Adresse.',
            'slug.regex' => 'Adresse: nur Kleinbuchstaben, Ziffern und Bindestrich.',
            'slug.unique' => 'Diese Adresse ist schon vergeben.',
        ]);

        $intern = $daten['art'] === FooterLink::INTERN;
        $slug = $intern ? ($daten['slug'] ?? null ?: Str::slug($daten['titel'])) : null;

        if ($intern && FooterLink::where('slug', $slug)->when($link, fn ($q) => $q->whereKeyNot($link->id))->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        return [
            'titel' => $daten['titel'],
            'art' => $daten['art'],
            'url' => $intern ? null : ($daten['url'] ?? null),
            'slug' => $slug,
            'inhalt' => $intern ? ($daten['inhalt'] ?? '') : null,
            'einbindung' => $intern ? ($daten['einbindung'] ?? null) : null,
            'aktiv' => $request->boolean('aktiv'),
        ];
    }
}
