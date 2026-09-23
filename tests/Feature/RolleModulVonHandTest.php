<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rollen lassen sich beim Anlegen/Bearbeiten von Hand einem Modul zuordnen.
 * Die Zuordnung überlebt `modules:sync`; meldet das Modul die Rolle später
 * selbst an, übernimmt es sie.
 */
class RolleModulVonHandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(); // erster Benutzer wird Admin

        $this->app->make(ModuleRegistry::class)->register(
            ModuleManifest::make('kueche', 'Küche')->item('index', 'Start', 'module.kueche.index')
        );
        $this->artisan('modules:sync')->assertSuccessful();
    }

    public function test_rolle_mit_modul_anlegen(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), ['role_id' => 'spueler', 'name' => 'Spüler', 'modul' => 'kueche'])
            ->assertRedirect(route('admin.roles.index'));

        $rolle = Role::find('spueler');
        $this->assertSame('kueche', $rolle->modul);
        $this->assertTrue($rolle->modul_von_hand);
        $this->assertFalse($rolle->stammtAusManifest());
    }

    public function test_modul_beim_bearbeiten_setzen_und_wieder_loesen(): void
    {
        Role::create(['role_id' => 'ak-garten', 'name' => 'Arbeitskreis Garten']);

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', 'ak-garten'), ['name' => 'Arbeitskreis Garten', 'modul' => 'kueche']);
        $this->assertSame('kueche', Role::find('ak-garten')->modul);

        // Handzuordnung sperrt das Bearbeiten nicht.
        $this->actingAs($this->admin)->get(route('admin.roles.edit', 'ak-garten'))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', 'ak-garten'), ['name' => 'Arbeitskreis Garten', 'modul' => '']);
        $rolle = Role::find('ak-garten');
        $this->assertNull($rolle->modul);
        $this->assertFalse($rolle->modul_von_hand);
    }

    public function test_unbekanntes_modul_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), ['role_id' => 'x', 'name' => 'X', 'modul' => 'gibtsnicht'])
            ->assertSessionHasErrors('modul');
    }

    public function test_sync_laesst_handzuordnung_stehen(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), ['role_id' => 'spueler', 'name' => 'Spüler', 'modul' => 'kueche']);

        $this->artisan('modules:sync')->assertSuccessful();

        $this->assertSame('kueche', Role::find('spueler')->modul);
    }

    public function test_meldet_das_modul_die_rolle_an_uebernimmt_es_sie(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), ['role_id' => 'spueler', 'name' => 'Spüler', 'modul' => 'kueche']);

        $this->app->forgetInstance(ModuleRegistry::class);
        $this->app->make(ModuleRegistry::class)->register(
            ModuleManifest::make('kueche', 'Küche')->item('index', 'Start', 'module.kueche.index')->rolle('spueler', 'Spülkraft')
        );
        $this->artisan('modules:sync')->assertSuccessful();

        $rolle = Role::find('spueler');
        $this->assertSame('kueche', $rolle->modul);
        $this->assertFalse($rolle->modul_von_hand);
        $this->assertSame('Spülkraft', $rolle->name);
    }
}
