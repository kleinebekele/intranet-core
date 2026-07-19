<?php

namespace App\Http\Controllers\Admin;

use App\Models\RouteSetting;
use App\Modules\Support\ModuleRegistry;
use App\Support\RoutenAliase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sprechende Adressen und feste Titel je Seite.
 *
 * Die Liste kann groß werden (jedes Modul bringt eigene Seiten mit), darum wird
 * nach Modul und Volltext gefiltert – serverseitig, wie im Kantinenmodul, damit
 * der Filter in der Adresse steht und teilbar bleibt.
 */
class SeoController
{
    public function index(Request $request, RoutenAliase $aliase, ModuleRegistry $registry): View
    {
        $suche = trim((string) $request->query('suche', ''));
        $modulFilter = (string) $request->query('modul', '');

        $eintraege = RouteSetting::alle();

        $zeilen = [];

        foreach ($aliase->verfuegbareRouten() as $route) {
            $name = $route->getName();
            $modulKey = $registry->currentKey($name);

            // Ursprüngliche Adresse: Ist sie ersetzt, liefert die Route bereits
            // die neue – die alte kennt nur die Merkliste.
            $original = RoutenAliase::urspruenglicherPfad($name) ?? $route->uri();

            $zeilen[] = [
                'name' => $name,
                'modulKey' => $modulKey,
                'modul' => $modulKey ? $registry->manifest($modulKey)?->name : 'Core',
                'original' => $original,
                'pfad' => $eintraege[$name]['pfad'] ?? null,
                'titel' => $eintraege[$name]['titel'] ?? null,
            ];
        }

        // Filter erst nach dem Aufbau: So bleiben Modulname und Adresse
        // durchsuchbar, nicht nur der technische Routen-Name.
        $zeilen = array_values(array_filter($zeilen, function (array $z) use ($suche, $modulFilter) {
            if ($modulFilter !== '') {
                $treffer = $modulFilter === 'core' ? $z['modulKey'] === null : $z['modulKey'] === $modulFilter;
                if (! $treffer) {
                    return false;
                }
            }

            if ($suche === '') {
                return true;
            }

            $heuhaufen = mb_strtolower(implode(' ', [
                $z['name'], $z['modul'], $z['original'], (string) $z['pfad'], (string) $z['titel'],
            ]));

            return str_contains($heuhaufen, mb_strtolower($suche));
        }));

        return view('admin.seo.index', [
            'zeilen' => $zeilen,
            'gesamt' => count($aliase->verfuegbareRouten()),
            'suche' => $suche,
            'modulFilter' => $modulFilter,
            'module' => $registry->manifests(),
        ]);
    }

    public function update(Request $request, RoutenAliase $aliase): RedirectResponse
    {
        $daten = $request->validate([
            'route_name' => ['required', 'string'],
            'pfad' => [
                'nullable', 'string', 'max:120',
                // Nur das, was in einer Adresse nicht weh tut. Schrägstriche sind
                // erlaubt, damit auch /schule/speiseplan möglich ist.
                'regex:/^[a-z0-9]+(?:[-\/][a-z0-9]+)*$/',
                Rule::unique('route_settings', 'pfad')->ignore($request->input('route_name'), 'route_name'),
            ],
            'titel' => ['nullable', 'string', 'max:120'],
        ], [
            'pfad.regex' => 'Die Adresse darf nur Kleinbuchstaben, Ziffern, Bindestriche und Schrägstriche enthalten – z. B. speiseplan oder schule/speiseplan.',
            'pfad.unique' => 'Diese Adresse ist bereits an eine andere Seite vergeben.',
        ]);

        $name = $daten['route_name'];
        $pfad = trim((string) ($daten['pfad'] ?? ''), '/ ');
        $titel = trim((string) ($daten['titel'] ?? ''));

        // Nur bekannte Seiten – sonst könnte man über ein verändertes Formular
        // Einträge für beliebige Routen-Namen anlegen.
        $bekannt = collect($aliase->verfuegbareRouten())->map(fn ($r) => $r->getName())->all();

        if (! in_array($name, $bekannt, true)) {
            return back()->withErrors('Diese Seite gibt es nicht (mehr).');
        }

        if ($fehler = $this->kollision($name, $pfad, $aliase)) {
            return back()->withErrors($fehler);
        }

        if ($pfad === '' && $titel === '') {
            RouteSetting::where('route_name', $name)->delete();
        } else {
            RouteSetting::updateOrCreate(
                ['route_name' => $name],
                ['pfad' => $pfad ?: null, 'titel' => $titel ?: null],
            );
        }

        return redirect()
            ->route('admin.seo.index', $request->only('suche', 'modul'))
            ->with('status', 'Gespeichert. Die Seite ist ab sofort unter der neuen Adresse erreichbar, die alte leitet dorthin weiter.');
    }

    /**
     * Kollidiert die Wunschadresse mit einer bestehenden Route?
     *
     * Ohne diese Prüfung könnte man eine Seite unerreichbar machen – etwa indem
     * man `dashboard` vergibt und damit die Startseite verdeckt.
     */
    private function kollision(string $name, string $pfad, RoutenAliase $aliase): ?string
    {
        if ($pfad === '') {
            return null;
        }

        foreach ($aliase->verfuegbareRouten() as $route) {
            if ($route->getName() === $name) {
                continue;
            }

            $vergeben = RoutenAliase::urspruenglicherPfad($route->getName()) ?? $route->uri();

            if (trim($vergeben, '/') === $pfad || trim($route->uri(), '/') === $pfad) {
                return "Die Adresse [{$pfad}] gehört bereits zur Seite ".Str::of($route->getName())->afterLast('.').'.';
            }
        }

        return null;
    }
}
