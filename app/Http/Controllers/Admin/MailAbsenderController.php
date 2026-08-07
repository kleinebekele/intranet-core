<?php

namespace App\Http\Controllers\Admin;

use App\Models\MailAbsender;
use App\Models\MailOutbox;
use App\Support\Mailausloeser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eigener Absender und Antwort-an je Modul + Auslöser.
 *
 * Ergänzt das Maillog: Dort sieht man, WAS eine Mail ausgelöst hat; hier stellt
 * man ein, mit welchem Absender sie rausgeht. Angeboten werden alle Auslöser,
 * die entweder ein Modul angemeldet hat oder die im Log schon vorkamen.
 */
class MailAbsenderController
{
    public function index(): View
    {
        return view('admin.mail.absender', [
            'gruppen' => $this->kombinationen(),
        ]);
    }

    /**
     * Alle Konfig-Zeilen in einem Rutsch speichern.
     *
     * Wie überall im System wird nur die ABWEICHUNG gespeichert: Eine Zeile, in
     * der weder Absender noch Antwort-an stehen, wird gelöscht – dann gilt
     * wieder der Absender, den das Modul selbst setzt.
     */
    public function speichern(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'zeilen' => ['array'],
            'zeilen.*.modul' => ['required', 'string', 'max:191'],
            'zeilen.*.ausloeser' => ['required', 'string', 'max:191'],
            'zeilen.*.absender_name' => ['nullable', 'string', 'max:191'],
            'zeilen.*.absender_mail' => ['nullable', 'email', 'max:191'],
            'zeilen.*.antwort_an' => ['nullable', 'email', 'max:191'],
        ]);

        foreach ($daten['zeilen'] ?? [] as $zeile) {
            $name = trim((string) ($zeile['absender_name'] ?? ''));
            $mail = trim((string) ($zeile['absender_mail'] ?? ''));
            $antwort = trim((string) ($zeile['antwort_an'] ?? ''));

            $schluessel = [
                'modul' => $zeile['modul'],
                'ausloeser' => $zeile['ausloeser'],
            ];

            // Nichts eingetragen? Dann keine Zeile behalten.
            if ($mail === '' && $antwort === '') {
                MailAbsender::query()->where($schluessel)->delete();

                continue;
            }

            MailAbsender::query()->updateOrCreate($schluessel, [
                'absender_name' => $name !== '' ? $name : null,
                'absender_mail' => $mail !== '' ? $mail : null,
                'antwort_an' => $antwort !== '' ? $antwort : null,
            ]);
        }

        return back()->with('status', 'Absender-Einstellungen gespeichert.');
    }

    /**
     * Die bekannten Kombinationen, nach Modul gruppiert, mit den aktuell
     * gespeicherten Werten.
     *
     * @return array<string, list<array{
     *     modul: string, ausloeser: string,
     *     absender_name: ?string, absender_mail: ?string, antwort_an: ?string
     * }>>
     */
    private function kombinationen(): array
    {
        // 1. Von Modulen angemeldete Auslöser (auch ungenutzte).
        $kombis = Mailausloeser::alle();

        // 2. Im Log tatsächlich vorgekommene – so erscheinen auch Auslöser, die
        //    kein Modul förmlich anmeldet.
        MailOutbox::query()
            ->select('modul', 'quelle')
            ->whereNotNull('quelle')
            ->distinct()
            ->get()
            ->each(function ($zeile) use (&$kombis) {
                $modul = (string) ($zeile->modul ?: 'Core');
                $ausloeser = (string) $zeile->quelle;
                $kombis[$modul.'|'.$ausloeser] ??= ['modul' => $modul, 'ausloeser' => $ausloeser];
            });

        // 3. Bereits gespeicherte Werte drüberlegen.
        $gespeichert = MailAbsender::all()->keyBy(fn ($a) => $a->modul.'|'.$a->ausloeser);

        $gruppen = [];

        foreach ($kombis as $eintrag) {
            $konfig = $gespeichert->get($eintrag['modul'].'|'.$eintrag['ausloeser']);

            $gruppen[$eintrag['modul']][] = [
                'modul' => $eintrag['modul'],
                'ausloeser' => $eintrag['ausloeser'],
                'absender_name' => $konfig?->absender_name,
                'absender_mail' => $konfig?->absender_mail,
                'antwort_an' => $konfig?->antwort_an,
            ];
        }

        // Module alphabetisch, „Core" zuerst; Auslöser je Gruppe alphabetisch.
        uksort($gruppen, function ($a, $b) {
            if ($a === 'Core') {
                return -1;
            }
            if ($b === 'Core') {
                return 1;
            }

            return strcasecmp($a, $b);
        });

        foreach ($gruppen as &$zeilen) {
            usort($zeilen, fn ($a, $b) => strcasecmp($a['ausloeser'], $b['ausloeser']));
        }

        return $gruppen;
    }
}
