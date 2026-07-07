<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Default-Deny-Sichtbarkeit: Rollen an Menüpunkten steuern Menü UND Zugriff.
 * Keine Rollen am Punkt = nur Administratoren.
 */
class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        // Test-Modul-Routen nach der module.{key}.*-Konvention.
        Route::middleware(['web', 'auth'])->prefix('modules/tm')->name('module.tm.')->group(function (): void {
            Route::get('/', fn () => 'home')->name('index');
            Route::get('/foo', fn () => 'foo')->name('foo.index');
            Route::post('/foo', fn () => 'foo-store')->name('foo.store');
            Route::get('/misc', fn () => 'misc')->name('misc');
        });

        $this->module = Module::create(['key' => 'tm', 'name' => 'Testmodul', 'icon' => 'cog', 'position' => 0, 'is_enabled' => true]);
        $this->module->menuItems()->createMany([
            ['key' => 'index', 'label' => 'Start', 'route_name' => 'module.tm.index', 'position' => 0],
            ['key' => 'foo', 'label' => 'Foo', 'route_name' => 'module.tm.foo.index', 'position' => 1],
        ]);

        Role::forceCreate(['role_id' => 'crew', 'name' => 'Crew']);
    }

    private function item(string $routeName): ModuleMenuItem
    {
        return $this->module->menuItems()->where('route_name', $routeName)->firstOrFail();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user->fresh();
    }

    private function userMitRolle(?string $role = null): User
    {
        $this->admin(); // sicherstellen, dass der Test-User NICHT der erste (=Admin) ist

        $user = User::factory()->create();
        if ($role !== null) {
            $user->roles()->syncWithoutDetaching([$role]);
        }

        return $user->fresh();
    }

    public function test_punkt_ohne_rollen_ist_nur_fuer_admins(): void
    {
        $this->actingAs($this->userMitRolle())->get('/modules/tm/foo')->assertForbidden();
        $this->actingAs($this->admin())->get('/modules/tm/foo')->assertOk();
    }

    public function test_punkt_mit_rolle_laesst_genau_diese_rolle_durch(): void
    {
        $this->item('module.tm.foo.index')->roles()->sync(['crew']);

        $this->actingAs($this->userMitRolle('crew'))->get('/modules/tm/foo')->assertOk();
        $this->actingAs($this->userMitRolle())->get('/modules/tm/foo')->assertForbidden();
    }

    public function test_ressourcen_unterseiten_erben_die_regel_des_menuepunkts(): void
    {
        $this->item('module.tm.foo.index')->roles()->sync(['crew']);

        $this->actingAs($this->userMitRolle('crew'))->post('/modules/tm/foo')->assertOk();
        $this->actingAs($this->userMitRolle())->post('/modules/tm/foo')->assertForbidden();
    }

    public function test_basis_rolle_user_bedeutet_alle(): void
    {
        $this->item('module.tm.foo.index')->roles()->sync(['user']);

        // Jeder Benutzer bekommt die Rolle "user" automatisch.
        $this->actingAs($this->userMitRolle())->get('/modules/tm/foo')->assertOk();
    }

    public function test_technische_route_braucht_irgendeinen_sichtbaren_punkt(): void
    {
        $this->item('module.tm.foo.index')->roles()->sync(['crew']);

        $this->actingAs($this->userMitRolle('crew'))->get('/modules/tm/misc')->assertOk();
        $this->actingAs($this->userMitRolle())->get('/modules/tm/misc')->assertForbidden();
    }

    public function test_deaktiviertes_modul_ist_gesperrt(): void
    {
        $this->item('module.tm.foo.index')->roles()->sync(['crew']);
        $this->module->update(['is_enabled' => false]);

        $this->actingAs($this->userMitRolle('crew'))->get('/modules/tm/foo')->assertForbidden();
    }

    public function test_modul_admins_only_sperrt_trotz_rollen(): void
    {
        $this->item('module.tm.foo.index')->roles()->sync(['crew']);
        $this->module->forceFill(['admins_only' => true])->save();

        $this->actingAs($this->userMitRolle('crew'))->get('/modules/tm/foo')->assertForbidden();
        $this->actingAs($this->admin())->get('/modules/tm/foo')->assertOk();
    }

    public function test_sidebar_zeigt_modul_nur_bei_sichtbarem_unterpunkt(): void
    {
        $module = Module::with('menuItems.roles')->find($this->module->id);
        $user = $this->userMitRolle();
        $crew = $this->userMitRolle('crew');

        $this->assertFalse($module->isVisibleTo($user));

        $this->item('module.tm.foo.index')->roles()->sync(['crew']);
        $module = Module::with('menuItems.roles')->find($this->module->id);

        $this->assertTrue($module->isVisibleTo($crew));
        $this->assertFalse($module->isVisibleTo($user));
    }
}
