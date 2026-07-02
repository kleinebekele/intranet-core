<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The admin panel for arranging the navigation:
 *  - reorder modules
 *  - reorder a module's sub-pages
 *  - enable / disable a module
 */
class ModuleController extends Controller
{
    public function index(): View
    {
        $modules = Module::with(['menuItems.roles', 'roles'])->orderBy('position')->get();
        $roles = Role::orderByDesc('is_system')->orderBy('role_id')->get();

        return view('admin.modules.index', compact('modules', 'roles'));
    }

    /**
     * Speichert, welche Rollen ein Modul und seine Unterpunkte in der
     * Navigation sehen dürfen. Leere Auswahl = für alle sichtbar.
     */
    public function visibility(Request $request, Module $module): RedirectResponse
    {
        $data = $request->validate([
            'module_roles'     => ['array'],
            'module_roles.*'   => ['string', 'exists:roles,role_id'],
            'item_roles'       => ['array'],
            'item_roles.*'     => ['array'],
            'item_roles.*.*'   => ['string', 'exists:roles,role_id'],
        ]);

        $module->roles()->sync($data['module_roles'] ?? []);

        // Nur die Unterpunkte dieses Moduls anfassen.
        $itemRoles = $data['item_roles'] ?? [];
        foreach ($module->menuItems as $item) {
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
