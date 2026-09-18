<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module liefern ihre Rollen über das Manifest; `modules:sync` legt sie an
 * und ordnet sie dem Modul zu.
 */
class ModulRollenSyncTest extends TestCase
{
    use RefreshDatabase;

    private function anmelden(ModuleManifest $manifest): void
    {
        $this->app->make(ModuleRegistry::class)->register($manifest);
    }

    private function testmodul(): ModuleManifest
    {
        return ModuleManifest::make('tm', 'Testmodul')->item('index', 'Start', 'module.tm.index');
    }

    public function test_sync_legt_die_rollen_des_moduls_an(): void
    {
        $this->anmelden($this->testmodul()
            ->rolle('tm-koch', 'Koch')
            ->rolle('tm-lehrer', 'Lehrer', plattformweit: true));

        $this->artisan('modules:sync')->assertSuccessful();

        $koch = Role::find('tm-koch');
        $this->assertSame('Koch', $koch->name);
        $this->assertSame('tm', $koch->modul);
        $this->assertFalse($koch->plattformweit);
        $this->assertNull($koch->quelle);
        $this->assertTrue(Role::find('tm-lehrer')->plattformweit);
    }

    public function test_vorhandene_rolle_wird_uebernommen_und_behaelt_ihre_mitglieder(): void
    {
        $rolle = Role::create(['role_id' => 'tm-koch', 'name' => 'Alter Name']);
        $user = User::factory()->create();
        $user->roles()->attach($rolle->role_id);

        $this->anmelden($this->testmodul()->rolle('tm-koch', 'Koch'));
        $this->artisan('modules:sync')->assertSuccessful();

        $rolle->refresh();
        $this->assertSame('tm', $rolle->modul);
        $this->assertSame('Koch', $rolle->name);
        $this->assertTrue($user->roles()->where('roles.role_id', 'tm-koch')->exists());
    }

    public function test_nicht_mehr_angemeldete_rolle_wird_freigegeben_statt_geloescht(): void
    {
        $this->anmelden($this->testmodul()->rolle('tm-koch', 'Koch'));
        $this->artisan('modules:sync')->assertSuccessful();

        $this->app->forgetInstance(ModuleRegistry::class);
        $this->anmelden($this->testmodul());
        $this->artisan('modules:sync')->assertSuccessful();

        $rolle = Role::find('tm-koch');
        $this->assertNotNull($rolle);
        $this->assertNull($rolle->modul);
    }

    public function test_rolle_eines_anderen_moduls_und_basisrollen_bleiben_unangetastet(): void
    {
        Role::create(['role_id' => 'fremd', 'name' => 'Fremd'])->forceFill(['modul' => 'anderes'])->save();

        $this->anmelden($this->testmodul()->rolle('fremd', 'Gekapert')->rolle('admin', 'Gekapert'));
        $this->artisan('modules:sync')->assertSuccessful();

        $this->assertSame('anderes', Role::find('fremd')->modul);
        $this->assertSame('Fremd', Role::find('fremd')->name);
        $this->assertNull(Role::find('admin')->modul);
    }
}
