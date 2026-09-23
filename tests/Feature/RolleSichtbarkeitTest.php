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
 * Rollen → Sichtbarkeit: die Unterseiten-Zuordnung von der Rolle aus setzen.
 */
class RolleSichtbarkeitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(); // erster Benutzer wird Admin

        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register(ModuleManifest::make('kueche', 'Küche')
            ->item('index', 'Start', 'module.kueche.index')
            ->item('plan', 'Speiseplan', 'module.kueche.plan')
            ->rolle('kueche-koch', 'Koch'));
        $registry->register(ModuleManifest::make('zeugnis', 'Zeugnis')
            ->item('index', 'Start', 'module.zeugnis.index'));
        $this->artisan('modules:sync')->assertSuccessful();

        Role::create(['role_id' => 'ak-garten', 'name' => 'Arbeitskreis Garten']);
    }

    private function punkt(string $modul, string $label)
    {
        return Module::where('key', $modul)->first()->menuItems()->where('label', $label)->first();
    }

    public function test_haekchen_setzen_und_entfernen(): void
    {
        $start = $this->punkt('kueche', 'Start');
        $plan = $this->punkt('kueche', 'Speiseplan');
        $plan->roles()->attach('ak-garten');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.sichtbarkeit.update', 'ak-garten'), ['items' => [$start->id]])
            ->assertRedirect(route('admin.roles.sichtbarkeit', 'ak-garten'));

        $this->assertTrue($start->roles()->where('roles.role_id', 'ak-garten')->exists());
        $this->assertFalse($plan->roles()->where('roles.role_id', 'ak-garten')->exists());
    }

    public function test_fremdes_modul_wird_nicht_angeboten_und_nicht_gesetzt(): void
    {
        $zeugnis = $this->punkt('zeugnis', 'Start');

        $this->actingAs($this->admin)
            ->get(route('admin.roles.sichtbarkeit', 'kueche-koch'))
            ->assertOk()
            ->assertSee('Speiseplan')
            ->assertDontSee('name="items[]" value="'.$zeugnis->id.'"', false);

        $this->actingAs($this->admin)
            ->put(route('admin.roles.sichtbarkeit.update', 'kueche-koch'), ['items' => [$zeugnis->id]]);

        $this->assertFalse($zeugnis->roles()->where('roles.role_id', 'kueche-koch')->exists());
    }

    public function test_andere_rollen_am_punkt_bleiben_unberuehrt(): void
    {
        $start = $this->punkt('kueche', 'Start');
        $start->roles()->attach('user');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.sichtbarkeit.update', 'ak-garten'), ['items' => [$start->id]]);

        $this->assertEqualsCanonicalizing(['user', 'ak-garten'], $start->roles()->pluck('roles.role_id')->all());
    }

    public function test_admin_hat_keine_sichtbarkeitsseite(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.sichtbarkeit', 'admin'))
            ->assertRedirect(route('admin.roles.index'));
    }
}
