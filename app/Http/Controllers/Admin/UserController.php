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
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $roleFilter = (string) $request->query('role', '');

        $users = User::with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter) {
                $query->whereHas('roles', fn ($q) => $q->where('roles.role_id', $roleFilter));
            })
            ->orderBy('name')
            ->get();

        $roles = Role::orderByDesc('is_system')->orderBy('role_id')->get();

        return view('admin.users.index', compact('users', 'roles', 'search', 'roleFilter'));
    }

    public function create(): View
    {
        $roles = Role::orderByDesc('is_system')->orderBy('role_id')->get();

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

        $user->roles()->sync($this->rolesWithBaseline($data['roles'] ?? []));

        // Willkommens-Mail mit Link zum Passwort-Setzen.
        $token = Password::broker()->createToken($user);
        $user->notify(new WelcomeNewUser($token));

        return redirect()->route('admin.users.index')
            ->with('status', "Benutzer \"{$user->name}\" wurde angelegt und per E-Mail eingeladen.");
    }

    public function edit(User $user): View
    {
        $roles = Role::orderByDesc('is_system')->orderBy('role_id')->get();
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

        $user->roles()->sync($this->rolesWithBaseline($data['roles'] ?? []));

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

    /**
     * Stellt sicher, dass die Baseline-Rolle "user" immer enthalten ist –
     * jeder Benutzer hat sie automatisch.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function rolesWithBaseline(array $roles): array
    {
        return array_values(array_unique([...$roles, 'user']));
    }

    /** Einen Passwort-Reset-Link an den Benutzer versenden. */
    public function sendReset(User $user): RedirectResponse
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new PasswordResetLinkNotification($token));

        return back()->with('status', "Passwort-Reset-Link an {$user->email} versendet.");
    }

    /**
     * TOTP zurücksetzen (z. B. Handy verloren): der Benutzer fällt auf den
     * Standard-Mail-Code zurück und kann TOTP im Profil neu einrichten.
     */
    public function resetTotp(User $user): RedirectResponse
    {
        $user->forceFill([
            'totp_secret' => null,
            'totp_confirmed_at' => null,
        ])->save();

        return back()->with('status', "TOTP für {$user->email} zurückgesetzt – es gilt wieder der Code per E-Mail.");
    }
}
