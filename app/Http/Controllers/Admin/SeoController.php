<?php

namespace App\Http\Controllers\Admin;

use App\Models\ModuleMenuItem;
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

        // Die Beschriftung aus der Seitenleiste ist der beste Name, den es für
        // eine Seite gibt: deutsch, vom Admin selbst gepflegt – und zwar genau
        // das Wort, unter dem er die Seite kennt.
        $beschriftungen = ModuleMenuItem::query()
            ->whereNotNull('route_name')
            ->pluck('label', 'route_name')
            ->all();

        $zeilen = [];

        foreach ($aliase->verfuegbareRouten() as $route) {
            $name = $route->getName();
            $modulKey = $registry->currentKey($name);

            // Ursprüngliche Adresse: Ist sie ersetzt, liefert die Route bereits
            // die neue – die alte kennt nur die Merkliste.
            $original = RoutenAliase::urspruenglicherPfad($name) ?? $route->uri();

            $zeilen[$name] = [
                'name' => $name,
                // Das angehängte `.index` bezeichnet nur die Übersicht eines
                // Bereichs und steht sonst hinter jedem zweiten Namen.
                'technischerName' => Str::of($name)->replaceEnd('.index', '')->toString(),
                'bezeichnung' => $beschriftungen[$name] ?? self::bezeichnung($name),
                'modulKey' => $modulKey,
                'modul' => $modulKey ? $registry->manifest($modulKey)?->name : 'Core',
                'original' => $original,
                // Was gerade gilt – bei geerbten Seiten also schon der Pfad, der
                // sich aus dem Stamm ergibt. Genau das gehört ins Feld als
                // Vorgabe, damit man sieht, was passiert, wenn man nichts tut.
                'aktuell' => $route->uri(),
                'pfad' => $eintraege[$name]['pfad'] ?? null,
                'titel' => $eintraege[$name]['titel'] ?? null,
                'stammIgnorieren' => (bool) ($eintraege[$name]['stamm_ignorieren'] ?? false),
            ];
        }

        // Filter erst nach dem Aufbau: So bleiben Modulname und Adresse
        // durchsuchbar, nicht nur der technische Routen-Name.
        $zeilen = array_filter($zeilen, function (array $z) use ($suche, $modulFilter) {
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
                $z['name'], $z['bezeichnung'], $z['modul'], $z['original'],
                (string) $z['pfad'], (string) $z['titel'],
            ]));

            return str_contains($heuhaufen, mb_strtolower($suche));
        });

        return view('admin.seo.index', [
            'bereiche' => self::gruppieren($zeilen),
            'anzahl' => count($zeilen),
            'gesamt' => count($aliase->verfuegbareRouten()),
            'suche' => $suche,
            'modulFilter' => $modulFilter,
            'module' => $registry->manifests(),
        ]);
    }

    /**
     * Seiten zu Bereichen bündeln: Übersicht oben, Unterseiten eingeklappt.
     *
     * Ein Bereich wird von einer `…index`-Seite angeführt; alles, dessen Name
     * darunter liegt, gehört dazu. Ohne diese Bündelung stehen in einem
     * gewachsenen System hunderte gleichrangiger Zeilen untereinander, und die
     * eine Zeile, die man ändern will, geht darin unter.
     *
     * @param  array<string, array<string, mixed>>  $zeilen
     * @return array<int, array{zeile: array<string, mixed>, kinder: array<int, array<string, mixed>>}>
     */
    private static function gruppieren(array $zeilen): array
    {
        // KÜRZESTER Name zuerst, also der oberste Bereich. Damit bleibt die
        // Liste einstufig: Auch `…ekkon.notifications.create` hängt direkt unter
        // `…ekkon.index`. Eine echte Verschachtelung brächte Klapp-Ebenen in
        // Klapp-Ebenen – mehr Mechanik als Nutzen für eine Adressliste.
        $staemme = array_filter(array_keys($zeilen), fn ($n) => str_ends_with($n, '.index'));
        usort($staemme, fn ($a, $b) => strlen($a) <=> strlen($b));

        $bereiche = [];
        $kinder = [];

        foreach ($zeilen as $name => $zeile) {
            $stamm = null;

            foreach ($staemme as $kandidat) {
                if ($name !== $kandidat && str_starts_with($name, substr($kandidat, 0, -5))) {
                    $stamm = $kandidat;
                    break;
                }
            }

            if ($stamm === null) {
                $bereiche[$name] = ['zeile' => $zeile, 'kinder' => []];
            } else {
                $kinder[$stamm][] = $zeile;
            }
        }

        foreach ($kinder as $stamm => $liste) {
            // Der Stamm kann weggefiltert sein (Suche) – dann stehen seine
            // Seiten für sich, statt unsichtbar zu werden.
            if (isset($bereiche[$stamm])) {
                $bereiche[$stamm]['kinder'] = $liste;

                continue;
            }

            foreach ($liste as $zeile) {
                $bereiche[$zeile['name']] = ['zeile' => $zeile, 'kinder' => []];
            }
        }

        return array_values($bereiche);
    }

    /**
     * Notbehelf für Seiten ohne Menüpunkt (Core-Seiten, Unterseiten).
     *
     * Ein angehängtes `.index` fliegt weg: Es bezeichnet nur die Übersicht einer
     * Gruppe und stand sonst als „Index" in fast jeder Zeile – hundertmal
     * dasselbe Wort sagt niemandem, welche Seite gemeint ist.
     */
    private static function bezeichnung(string $routeName): string
    {
        return Str::of($routeName)
            ->replaceEnd('.index', '')
            ->afterLast('.')
            ->replace(['-', '_'], ' ')
            ->ucfirst()
            ->toString();
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
            'stamm_ignorieren' => ['nullable', 'boolean'],
        ], [
            'pfad.regex' => 'Die Adresse darf nur Kleinbuchstaben, Ziffern, Bindestriche und Schrägstriche enthalten – z. B. speiseplan oder schule/speiseplan.',
            'pfad.unique' => 'Diese Adresse ist bereits an eine andere Seite vergeben.',
        ]);

        $name = $daten['route_name'];
        $pfad = trim((string) ($daten['pfad'] ?? ''), '/ ');
        $titel = trim((string) ($daten['titel'] ?? ''));
        $stammIgnorieren = (bool) ($daten['stamm_ignorieren'] ?? false);

        // Nur bekannte Seiten – sonst könnte man über ein verändertes Formular
        // Einträge für beliebige Routen-Namen anlegen.
        $bekannt = collect($aliase->verfuegbareRouten())->map(fn ($r) => $r->getName())->all();

        if (! in_array($name, $bekannt, true)) {
            return back()->withErrors('Diese Seite gibt es nicht (mehr).');
        }

        if ($fehler = $this->kollision($name, $pfad, $aliase)) {
            return back()->withErrors($fehler);
        }

        // Ein leerer Eintrag ohne Häkchen sagt nichts aus – der kommt weg.
        if ($pfad === '' && $titel === '' && ! $stammIgnorieren) {
            RouteSetting::where('route_name', $name)->delete();

            // Ein Löschen über die Abfrage löst KEINE Model-Ereignisse aus – der
            // Cache würde den Eintrag also behalten und die selbst vergebene
            // Adresse weiter gelten. Zurücknehmen ginge damit gar nicht.
            RouteSetting::cacheVerwerfen();
        } else {
            RouteSetting::updateOrCreate(
                ['route_name' => $name],
                [
                    'pfad' => $pfad ?: null,
                    'titel' => $titel ?: null,
                    'stamm_ignorieren' => $stammIgnorieren,
                ],
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
