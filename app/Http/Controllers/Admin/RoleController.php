<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleRegistry;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD-Verwaltung der Rollen (nur für Administratoren).
 *
 * Der Schlüssel `role_id` ist fachlich und wird beim Anlegen einmal gesetzt;
 * danach lässt sich nur noch der Anzeige-`name` pflegen, damit bestehende
 * Verknüpfungen in user_roles stabil bleiben.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')
            ->orderByDesc('is_system') // System-Rollen (admin, user) zuerst
            ->orderBy('role_id')
            ->get();

        // Gruppierung wie überall, hier aber die von Hand angelegten direkt unter
        // System: an denen arbeitet man im Panel, der Rest ist zugeklappt.
        $gruppen = Role::nachHerkunft($roles);
        $gruppen = $gruppen->only(['System', 'Von Hand angelegt'])
            ->merge($gruppen->except(['System', 'Von Hand angelegt']));

        $modulNamen = Module::pluck('name', 'key');

        return view('admin.roles.index', compact('roles', 'gruppen', 'modulNamen'));
    }

    public function create(): View
    {
        return view('admin.roles.create', ['module' => $this->modulAuswahl()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:roles,role_id'],
            'name' => ['required', 'string', 'max:255'],
            'modul' => ['nullable', 'string', Rule::in($this->modulAuswahl()->keys())],
        ]);

        $role = Role::create(['role_id' => $data['role_id'], 'name' => $data['name']]);
        $this->modulZuordnen($role, $data['modul'] ?? null);
        Audit::schreiben('rolle.angelegt', "Rolle „{$data['name']}\" angelegt.", ziel: 'Rolle '.$data['role_id']);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$data['role_id']}\" wurde angelegt.");
    }

    public function edit(Role $role): View|RedirectResponse
    {
        if ($sperre = $this->namenssperre($role)) {
            return redirect()->route('admin.roles.index')->withErrors(['role' => $sperre]);
        }

        return view('admin.roles.edit', ['role' => $role, 'module' => $this->modulAuswahl()]);
    }

    /**
     * Module, denen man eine Rolle von Hand zuordnen kann: alle installierten,
     * auch abgeschaltete (die Rolle ruht dann mit ihrem Modul).
     *
     * @return Collection<string, Module> key => Modul, nach Name
     */
    private function modulAuswahl(): Collection
    {
        return Module::whereIn('key', app(ModuleRegistry::class)->keys())
            ->orderBy('name')
            ->get()
            ->keyBy('key');
    }

    /**
     * Handzuordnung setzen oder aufheben. `modul_von_hand` sorgt dafür, dass
     * `modules:sync` die Zuordnung stehen lässt. Gibt zurück, ob sich etwas
     * geändert hat.
     */
    private function modulZuordnen(Role $role, ?string $modul): bool
    {
        $modul = $modul !== '' ? $modul : null;
        if ($role->modul === $modul) {
            return false;
        }

        $role->forceFill([
            'modul' => $modul,
            'modul_von_hand' => $modul !== null,
            'plattformweit' => false,
        ])->save();
        Role::aktivStandVergessen();

        return true;
    }

    /**
     * Warum sich diese Rolle im Panel nicht umbenennen lässt – null, wenn sie
     * dem Panel gehört. Abgeglichene Rollen (roles.quelle, z. B. Klassen aus
     * Linear) sind wie auf „Benutzer bearbeiten" nur zur Ansicht: Name und
     * Mitglieder kommen aus dem Abgleich.
     */
    private function namenssperre(Role $role): ?string
    {
        return match (true) {
            $role->istVerwaltet() => "Die Rolle \"{$role->name}\" pflegt der Abgleich „{$role->quelle}\" – sie lässt sich hier nicht bearbeiten.",
            $role->stammtAusManifest() => "Die Rolle \"{$role->name}\" bringt das Modul „{$role->modul}\" mit – der Name kommt von dort und würde beim nächsten Abgleich zurückgesetzt.",
            default => null,
        };
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($sperre = $this->namenssperre($role)) {
            return back()->withErrors(['role' => $sperre]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'modul' => ['nullable', 'string', Rule::in($this->modulAuswahl()->keys())],
        ]);

        $alt = $role->name;
        $altesModul = $role->modul;
        $role->update(['name' => $data['name']]);
        if ($alt !== $role->name) {
            Audit::schreiben('rolle.geaendert', "Umbenannt: „{$alt}\" → „{$role->name}\".", ziel: 'Rolle '.$role->role_id);
        }

        // System-Rollen (admin, user) gehören immer dem Core.
        if (! $role->isSystem() && $this->modulZuordnen($role, $data['modul'] ?? null)) {
            Audit::schreiben(
                'rolle.geaendert',
                'Modul: „'.($altesModul ?? 'keins').'" → „'.($role->modul ?? 'keins').'".',
                ziel: 'Rolle '.$role->role_id,
            );
        }

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$role->role_id}\" wurde gespeichert.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isSystem()) {
            return back()->withErrors(['role' => "Die System-Rolle \"{$role->role_id}\" kann nicht gelöscht werden."]);
        }

        if ($role->stammtAusManifest()) {
            return back()->withErrors(['role' => "Die Rolle \"{$role->name}\" bringt das Modul „{$role->modul}\" mit – sie verschwindet, wenn das Modul entfernt wird."]);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Rollen mit Zuweisungen können nicht gelöscht werden. Hebe zuerst alle Zuweisungen auf.']);
        }

        $roleId = $role->role_id;
        $role->delete();
        Audit::schreiben('rolle.geloescht', "Rolle „{$role->name}\" gelöscht.", ziel: 'Rolle '.$roleId);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$roleId}\" wurde gelöscht.");
    }

    /**
     * Mitglieder einer Rolle: Liste + Auswahl aus allen Benutzern zum Hinzufügen.
     *
     * Rollen sind zugleich Gruppen (Arbeitskreise, Klassen). Bisher ließ sich
     * eine Rolle nur am einzelnen Benutzer setzen – für einen Arbeitskreis aus
     * zwanzig Leuten unbrauchbar. Hier geht es andersherum: von der Rolle aus
     * die Menschen zusammensuchen.
     */
    public function mitglieder(Request $request, Role $role): View
    {
        $suche = trim((string) $request->query('q', ''));

        $mitglieder = $role->users()
            ->orderBy('name')
            ->get();

        // Kandidaten: alle, die die Rolle noch nicht haben – optional gefiltert.
        // Die Liste ist bei einer Schule vierstellig; ohne Suchwort nur eine
        // überschaubare erste Seite, damit die Seite nicht erschlägt.
        $kandidaten = collect();
        if (! $role->istVerwaltet()) {
            $kandidaten = User::query()
                ->whereNotIn('id', $mitglieder->pluck('id'))
                ->when($suche !== '', fn ($q) => $q->where(function ($q) use ($suche) {
                    $q->where('name', 'like', "%{$suche}%")
                        ->orWhere('email', 'like', "%{$suche}%");
                }))
                ->orderBy('name')
                ->limit(200)
                ->get();
        }

        return view('admin.roles.mitglieder', compact('role', 'mitglieder', 'kandidaten', 'suche'));
    }

    public function mitgliederHinzufuegen(Request $request, Role $role): RedirectResponse
    {
        if ($role->istVerwaltet()) {
            return back()->withErrors(['role' => "Die Rolle \"{$role->name}\" wird vom Abgleich „{$role->quelle}\" gepflegt – Mitglieder lassen sich dort nicht von Hand setzen."]);
        }

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $vorher = $role->users()->pluck('users.id')->all();
        $role->users()->syncWithoutDetaching($data['user_ids']);
        $neu = User::whereIn('id', array_diff($data['user_ids'], $vorher))->pluck('name')->all();

        if ($neu) {
            Audit::schreiben(
                'rolle.mitglieder_hinzugefuegt',
                count($neu).' Mitglied(er) hinzugefügt: '.implode(', ', $neu),
                ziel: 'Rolle '.$role->role_id,
                daten: ['hinzugefuegt' => $neu],
            );
        }

        return redirect()->route('admin.roles.mitglieder', $role)
            ->with('status', count($neu).' Mitglied(er) zu "'.$role->name.'" hinzugefügt.');
    }

    public function mitgliedEntfernen(Role $role, User $user): RedirectResponse
    {
        if ($role->istVerwaltet()) {
            return back()->withErrors(['role' => "Die Rolle \"{$role->name}\" wird vom Abgleich „{$role->quelle}\" gepflegt – Mitglieder lassen sich dort nicht von Hand entfernen."]);
        }

        if ($role->role_id === 'user') {
            return back()->withErrors(['role' => 'Die Rolle "user" hat jeder Benutzer automatisch.']);
        }

        $role->users()->detach($user->id);
        Audit::schreiben('rolle.mitglied_entfernt', "{$user->name} aus „{$role->name}\" entfernt.", $user, ziel: 'Rolle '.$role->role_id);

        return redirect()->route('admin.roles.mitglieder', $role)
            ->with('status', "{$user->name} wurde aus \"{$role->name}\" entfernt.");
    }

    /**
     * Sichtbarkeit von der Rolle aus: welche Unterseiten welcher Module sieht
     * sie? Dieselbe Zuordnung wie unter Verwaltung → Module, nur andersherum.
     * Module, denen die Rolle fremd ist (sie gehört einem anderen Modul),
     * erscheinen nur mit den Punkten, an denen sie schon hängt – zum Entfernen.
     */
    public function sichtbarkeit(Role $role): View|RedirectResponse
    {
        if ($role->role_id === 'admin') {
            return redirect()->route('admin.roles.index')
                ->withErrors(['role' => 'Administratoren sehen ohnehin alles.']);
        }

        $module = $this->sichtbarkeitsModule($role);

        return view('admin.roles.sichtbarkeit', compact('role', 'module'));
    }

    public function sichtbarkeitSpeichern(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->role_id === 'admin', 404);

        $data = $request->validate([
            'items' => ['array'],
            'items.*' => ['integer'],
        ]);
        $gewaehlt = collect($data['items'] ?? [])->map(fn ($id) => (int) $id);

        $vorher = [];
        $nachher = [];
        foreach ($this->sichtbarkeitsModule($role) as $eintrag) {
            foreach ($eintrag['items'] as $item) {
                $hatte = $item->roles->contains('role_id', $role->role_id);
                $soll = $gewaehlt->contains($item->id);
                $bezeichnung = $eintrag['modul']->name.' → '.$item->label;

                if ($hatte) {
                    $vorher[] = $bezeichnung;
                }
                if ($soll) {
                    $nachher[] = $bezeichnung;
                }
                if ($soll && ! $hatte) {
                    $item->roles()->attach($role->role_id);
                } elseif (! $soll && $hatte) {
                    $item->roles()->detach($role->role_id);
                }
            }
        }

        if ($vorher !== $nachher) {
            Audit::schreiben('rolle.sichtbarkeit', null, ziel: 'Rolle '.$role->role_id, daten: [
                'neu' => array_values(array_diff($nachher, $vorher)),
                'entfernt' => array_values(array_diff($vorher, $nachher)),
            ]);
        }

        return redirect()->route('admin.roles.sichtbarkeit', $role)
            ->with('status', "Sichtbarkeit von \"{$role->name}\" gespeichert.");
    }

    /**
     * Module in Navigationsreihenfolge, je mit den Unterpunkten, die sich für
     * diese Rolle setzen lassen. Module ohne solchen Punkt fallen weg.
     *
     * @return Collection<int, array{modul: Module, items: Collection<int, ModuleMenuItem>, fremd: bool}>
     */
    private function sichtbarkeitsModule(Role $role): Collection
    {
        return Module::whereIn('key', app(ModuleRegistry::class)->keys())
            ->with('menuItems.roles')
            ->orderBy('position')
            ->get()
            ->map(function (Module $modul) use ($role) {
                $fremd = $role->auswahlgruppeFuer($modul->key) === 'fremd';

                return [
                    'modul' => $modul,
                    'items' => $modul->menuItems->filter(
                        fn (ModuleMenuItem $item) => ! $fremd || $item->roles->contains('role_id', $role->role_id),
                    )->values(),
                    'fremd' => $fremd,
                ];
            })
            ->filter(fn (array $eintrag) => $eintrag['items']->isNotEmpty())
            ->values();
    }

    /** Alle Benutzer-Zuweisungen einer (Nicht-System-)Rolle aufheben. */
    public function detachAll(Role $role): RedirectResponse
    {
        if ($role->isSystem()) {
            return back()->withErrors(['role' => "Bei der System-Rolle \"{$role->role_id}\" können Zuweisungen nicht aufgehoben werden."]);
        }

        if ($role->istVerwaltet()) {
            return back()->withErrors(['role' => "Die Rolle \"{$role->name}\" wird vom Abgleich „{$role->quelle}\" gepflegt – der nächste Lauf würde die Zuweisungen wiederherstellen."]);
        }

        $count = $role->users()->count();
        $role->users()->detach();
        Audit::schreiben('rolle.zuweisungen_aufgehoben', "{$count} Zuweisung(en) aufgehoben.", ziel: 'Rolle '.$role->role_id, daten: ['anzahl' => $count]);

        return redirect()->route('admin.roles.index')
            ->with('status', "Alle {$count} Zuweisung(en) der Rolle \"{$role->name}\" wurden aufgehoben.");
    }
}
