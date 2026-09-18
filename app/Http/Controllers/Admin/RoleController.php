<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // Feste Ordnung: System-Rollen zuerst, darunter je Modul ein Block (nach
        // Modulname), zuletzt die von Hand angelegten und abgeglichenen Gruppen.
        $modulNamen = Module::pluck('name', 'key');

        $gruppen = $roles
            ->sortBy(fn (Role $role) => mb_strtolower($role->name))
            ->groupBy(fn (Role $role) => match (true) {
                $role->gehoertZuModul() => '1'.mb_strtolower($modulNamen[$role->modul] ?? $role->modul).'|'.($modulNamen[$role->modul] ?? $role->modul),
                $role->isSystem() => '0|System',
                default => '2|Von Hand angelegt und abgeglichene Gruppen',
            })
            ->sortKeys()
            ->mapWithKeys(fn ($rollen, string $schluessel) => [explode('|', $schluessel, 2)[1] => $rollen]);

        return view('admin.roles.index', compact('roles', 'gruppen'));
    }

    public function create(): View
    {
        return view('admin.roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:roles,role_id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Role::create($data);
        Audit::schreiben('rolle.angelegt', "Rolle „{$data['name']}\" angelegt.", ziel: 'Rolle '.$data['role_id']);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$data['role_id']}\" wurde angelegt.");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->gehoertZuModul()) {
            return back()->withErrors(['role' => "Die Rolle \"{$role->name}\" bringt das Modul „{$role->modul}\" mit – der Name kommt von dort und würde beim nächsten Abgleich zurückgesetzt."]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $alt = $role->name;
        $role->update($data);
        if ($alt !== $role->name) {
            Audit::schreiben('rolle.geaendert', "Umbenannt: „{$alt}\" → „{$role->name}\".", ziel: 'Rolle '.$role->role_id);
        }

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$role->role_id}\" wurde umbenannt.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isSystem()) {
            return back()->withErrors(['role' => "Die System-Rolle \"{$role->role_id}\" kann nicht gelöscht werden."]);
        }

        if ($role->gehoertZuModul()) {
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
