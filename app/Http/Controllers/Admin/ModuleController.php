<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleMenuItem;
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
        $modules = Module::with('menuItems')->orderBy('position')->get();

        return view('admin.modules.index', compact('modules'));
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
