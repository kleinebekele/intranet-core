<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\Role;
use App\Modules\Support\ModuleUninstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The admin panel for arranging the navigation:
 *  - reorder modules
 *  - reorder a module's sub-pages
 *  - enable / disable a module
 */
class ModuleController extends Controller
{
    public function __construct(private ModuleUninstaller $uninstaller) {}

    public function index(): View
    {
        $modules = Module::with(['menuItems.roles', 'roles'])->orderBy('position')->get();

        // Was hängt an jedem Modul? Zeigt der Entfernen-Dialog an, damit
        // niemand ins Blaue hinein löscht.
        $vorschauen = $modules->mapWithKeys(
            fn (Module $module) => [$module->id => $this->uninstaller->vorschau($module->key)],
        );

        // "admin" wird bewusst NICHT als Sichtbarkeits-Rolle angeboten – Admins
        // sehen ohnehin alles; "nur für Admins" regelt der separate Schalter.
        $roles = Role::where('role_id', '!=', 'admin')
            ->orderByDesc('is_system')
            ->orderBy('role_id')
            ->get();

        return view('admin.modules.index', compact('modules', 'roles', 'vorschauen'));
    }

    /**
     * Modul aus dieser Instanz entfernen.
     *
     * Standardmäßig verschwindet nur die Registrierung (Modul, Menüpunkte samt
     * Rollen, sprechende Adressen); die Tabellen des Moduls bleiben stehen.
     * Erst `mit_daten` rollt seine Migrationen zurück – und weil das echte
     * Daten kostet, muss dafür zusätzlich der Modul-Schlüssel getippt werden.
     *
     * Das Paket selbst bleibt installiert: Es aus der `composer.json` zu
     * werfen ist Sache der Konsole, worauf die Erfolgsmeldung hinweist.
     */
    public function destroy(Request $request, Module $module): RedirectResponse
    {
        $mitDaten = $request->boolean('mit_daten');

        $request->validate([
            'mit_daten' => ['sometimes', 'boolean'],
            'bestaetigung' => [
                Rule::requiredIf($mitDaten),
                Rule::in([$module->key]),
            ],
        ], [
            'bestaetigung.*' => "Zum Löschen der Daten bitte den Modul-Schlüssel „{$module->key}\" eintippen.",
        ]);

        $key = $module->key;

        try {
            $bericht = $this->uninstaller->entfernen($key, $mitDaten);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $meldung = "Modul \"{$bericht['name']}\" entfernt: {$bericht['menuepunkte']} Menüpunkt(e)";
        $meldung .= $bericht['adressen'] ? ", {$bericht['adressen']} sprechende Adresse(n)" : '';
        $meldung .= $bericht['migrationen']
            ? ', '.count($bericht['migrationen']).' Migration(en) zurückgerollt.'
            : '. Die Tabellen des Moduls sind unverändert geblieben.';

        $paket = $bericht['paket_name'] ?? "<vendor>/module-{$key}";
        $meldung .= " Damit es nicht beim nächsten \"modules:sync\" wieder auftaucht, muss das Paket noch aus der composer.json: composer remove {$paket}";

        return redirect()->route('admin.modules.index')->with('status', $meldung);
    }

    /**
     * Speichert, welche Rollen die Unterpunkte eines Moduls sehen dürfen
     * (Navigation UND Zugriff). Leere Auswahl = nur Administratoren;
     * "für alle" wählt man explizit über die Basis-Rolle `user`.
     */
    public function visibility(Request $request, Module $module): RedirectResponse
    {
        $data = $request->validate([
            'module_admins_only' => ['sometimes', 'boolean'],
            'item_admins_only' => ['array'],
            'item_admins_only.*' => ['boolean'],
            'item_roles' => ['array'],
            'item_roles.*' => ['array'],
            'item_roles.*.*' => ['string', 'exists:roles,role_id'],
        ]);

        $module->admins_only = (bool) ($data['module_admins_only'] ?? false);
        $module->save();

        // Nur die Unterpunkte dieses Moduls anfassen.
        $itemRoles = $data['item_roles'] ?? [];
        $itemAdminsOnly = $data['item_admins_only'] ?? [];
        foreach ($module->menuItems as $item) {
            $item->admins_only = (bool) ($itemAdminsOnly[$item->id] ?? false);
            $item->save();
            $item->roles()->sync($itemRoles[$item->id] ?? []);
        }

        return back()->with('status', "Sichtbarkeit von \"{$module->name}\" gespeichert.");
    }

    /** Persist a new module order (array of module ids in the desired order). */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:modules,id'],
        ])['ids'];

        foreach ($ids as $position => $id) {
            Module::where('id', $id)->update(['position' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    public function toggle(Module $module): RedirectResponse
    {
        $module->update(['is_enabled' => ! $module->is_enabled]);

        return back()->with('status', "Modul \"{$module->name}\" ".($module->is_enabled ? 'aktiviert' : 'deaktiviert').'.');
    }

    /** Persist a new order for one module's sub-pages. */
    public function reorderItems(Request $request, Module $module): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        foreach ($ids as $position => $id) {
            ModuleMenuItem::where('id', $id)
                ->where('module_id', $module->id)
                ->update(['position' => $position]);
        }

        return response()->json(['ok' => true]);
    }
}
