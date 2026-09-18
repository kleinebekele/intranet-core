<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Rolle eines Moduls gilt nur, solange das Modul aktiv und installiert ist.
 */
class ModulRollenAktivTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(); // erster Benutzer wird Admin
        $this->user = User::factory()->create();

        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register(ModuleManifest::make('tm', 'Testmodul')
            ->item('index', 'Start', 'module.tm.index')
            ->rolle('tm-koch', 'Koch')
            ->rolle('tm-lehrer', 'Lehrer', plattformweit: true));
        $registry->register(ModuleManifest::make('anderes', 'Anderes')
            ->item('index', 'Start', 'module.anderes.index'));

        $this->artisan('modules:sync')->assertSuccessful();
    }

    private function modulAus(): void
    {
        Module::where('key', 'tm')->update(['is_enabled' => false]);
        Role::aktivStandVergessen();
    }

    public function test_rolle_gilt_solange_das_modul_aktiv_ist(): void
    {
        $this->user->roles()->attach('tm-koch');

        $this->assertTrue($this->user->hatRolle('tm-koch'));
        $this->assertTrue(Role::find('tm-koch')->istAktiv());
    }

    public function test_rolle_eines_deaktivierten_moduls_gilt_nicht(): void
    {
        $this->user->roles()->attach('tm-koch');
        $this->modulAus();

        $this->assertFalse($this->user->hatRolle('tm-koch'));
        $this->assertFalse(Role::find('tm-koch')->istAktiv());
        $this->assertNotContains('tm-koch', Role::aktiv()->pluck('role_id')->all());
        // Die Zuweisung selbst bleibt – beim Wiedereinschalten ist alles wie vorher.
        $this->assertTrue($this->user->roles()->where('roles.role_id', 'tm-koch')->exists());
    }

    public function test_sie_oeffnet_auch_keinen_fremden_menuepunkt_mehr(): void
    {
        $this->user->roles()->attach('tm-koch');
        $punkt = Module::where('key', 'anderes')->first()->menuItems()->first();
        $punkt->roles()->attach('tm-koch');

        $this->assertTrue($punkt->fresh()->isVisibleTo($this->user));

        $this->modulAus();

        $this->assertFalse($punkt->fresh()->isVisibleTo($this->user->fresh()));
    }

    public function test_plattformweite_und_handangelegte_rollen_gelten_weiter(): void
    {
        Role::create(['role_id' => 'hand', 'name' => 'Von Hand']);
        $this->user->roles()->attach(['tm-lehrer', 'hand']);
        $this->modulAus();

        $this->assertTrue($this->user->hatRolle('tm-lehrer'));
        $this->assertTrue($this->user->hatRolle('hand'));
    }

    public function test_rolle_eines_deinstallierten_pakets_gilt_nicht(): void
    {
        $this->user->roles()->attach('tm-koch');

        $this->app->forgetInstance(ModuleRegistry::class);
        $this->app->singleton(ModuleRegistry::class);
        Role::aktivStandVergessen();

        $this->assertFalse($this->user->hatRolle('tm-koch'));
    }
}
