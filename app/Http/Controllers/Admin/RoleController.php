<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
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
        $roles = Role::withCount('users')->orderBy('role_id')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:roles,role_id'],
            'name'    => ['required', 'string', 'max:255'],
        ]);

        Role::create($data);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$data['role_id']}\" wurde angelegt.");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $role->update($data);

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$role->role_id}\" wurde umbenannt.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $roleId = $role->role_id;
        $role->delete(); // user_roles-Einträge verschwinden per cascadeOnDelete

        return redirect()->route('admin.roles.index')
            ->with('status', "Rolle \"{$roleId}\" wurde gelöscht.");
    }
}
