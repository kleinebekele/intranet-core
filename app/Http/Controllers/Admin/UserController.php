<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use App\Notifications\WelcomeNewUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * CRUD-Verwaltung der Benutzer (nur für Administratoren).
 *
 *  - E-Mail ist unveränderbar (nur beim Anlegen setzbar).
 *  - Rollen können zugewiesen/entzogen werden (n:n über user_roles).
 *  - Neue Benutzer erhalten eine Willkommens-Mail zum Passwort-Setzen.
 *  - Für bestehende Benutzer kann ein Passwort-Reset-Link versendet werden.
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('role_id')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,role_id'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user = new User();
        $user->forceFill([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'is_admin'          => (bool) ($data['is_admin'] ?? false),
            'source'            => 'manual',
            'password'          => null,
            'email_verified_at' => now(), // vom Admin angelegt = vertrauenswürdig
        ])->save();

        $user->roles()->sync($data['roles'] ?? []);

        // Willkommens-Mail mit Link zum Passwort-Setzen.
        $token = Password::broker()->createToken($user);
        $user->notify(new WelcomeNewUser($token));

        return redirect()->route('admin.users.index')
            ->with('status', "Benutzer \"{$user->name}\" wurde angelegt und per E-Mail eingeladen.");
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('role_id')->get();
        $user->load('roles');

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,role_id'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        // Selbst-Aussperrung verhindern: eigenen Admin-Status nicht entziehen.
        $isAdmin = (bool) ($data['is_admin'] ?? false);
        if ($user->id === $request->user()->id && ! $isAdmin) {
            return back()->withErrors(['is_admin' => 'Du kannst dir den Admin-Status nicht selbst entziehen.']);
        }

        // E-Mail wird bewusst NICHT übernommen (unveränderbar).
        $user->name = $data['name'];
        $user->is_admin = $isAdmin;
        $user->save();

        $user->roles()->sync($data['roles'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('status', "Benutzer \"{$user->name}\" wurde aktualisiert.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Du kannst dein eigenes Konto nicht löschen.']);
        }

        $name = $user->name;
        $user->roles()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', "Benutzer \"{$name}\" wurde gelöscht.");
    }

    /** Einen Passwort-Reset-Link an den Benutzer versenden. */
    public function sendReset(User $user): RedirectResponse
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new PasswordResetLinkNotification($token));

        return back()->with('status', "Passwort-Reset-Link an {$user->email} versendet.");
    }
}
