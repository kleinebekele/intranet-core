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
 * Verwaltung → Module: „Unterseiten sichtbar für" bietet je Modul nur dessen
 * eigene Rollen, die Core-Rollen und plattformweite Rollen an.
 */
class ModulRollenAuswahlTest extends TestCase
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
            ->rolle('kueche-koch', 'Koch')
            ->rolle('lehrer', 'Lehrer', plattformweit: true));
        $registry->register(ModuleManifest::make('zeugnis', 'Zeugnis')
            ->item('index', 'Start', 'module.zeugnis.index')
            ->rolle('zeugnis-admin', 'Zeugnis-Admin'));

        $this->artisan('modules:sync')->assertSuccessful();

        Role::create(['role_id' => 'ak-garten', 'name' => 'Arbeitskreis Garten']);
    }

    private function punkt(string $modul)
    {
        return Module::where('key', $modul)->first()->menuItems()->first();
    }

    public function test_einordnung_der_rollen_je_modul(): void
    {
        $gruppe = fn (string $rolle, string $modul) => Role::find($rolle)->auswahlgruppeFuer($modul);

        $this->assertSame('haupt', $gruppe('kueche-koch', 'kueche'));
        $this->assertSame('fremd', $gruppe('kueche-koch', 'zeugnis'));
        $this->assertSame('haupt', $gruppe('lehrer', 'zeugnis'));
        $this->assertSame('haupt', $gruppe('user', 'zeugnis'));
        $this->assertSame('weitere', $gruppe('ak-garten', 'zeugnis'));
    }

    public function test_seite_zeigt_fremde_modulrollen_nicht_an(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.modules.index'))->assertOk()->getContent();

        $zeugnisPunkt = $this->punkt('zeugnis')->id;
        $this->assertStringContainsString('name="item_roles['.$zeugnisPunkt.'][]" value="zeugnis-admin"', $html);
        $this->assertStringContainsString('name="item_roles['.$zeugnisPunkt.'][]" value="lehrer"', $html);
        $this->assertStringContainsString('name="item_roles['.$zeugnisPunkt.'][]" value="ak-garten"', $html);
        $this->assertStringNotContainsString('name="item_roles['.$zeugnisPunkt.'][]" value="kueche-koch"', $html);
    }

    public function test_fremde_rolle_laesst_sich_nicht_neu_zuordnen(): void
    {
        $punkt = $this->punkt('zeugnis');

        $this->actingAs($this->admin)->put(route('admin.modules.visibility', $punkt->module), [
            'item_roles' => [$punkt->id => ['zeugnis-admin', 'kueche-koch']],
        ])->assertRedirect();

        $this->assertSame(['zeugnis-admin'], $punkt->roles()->pluck('roles.role_id')->all());
    }

    public function test_altzuordnung_bleibt_bis_man_sie_entfernt(): void
    {
        $punkt = $this->punkt('zeugnis');
        $punkt->roles()->attach('kueche-koch');

        $html = $this->actingAs($this->admin)->get(route('admin.modules.index'))->getContent();
        $this->assertStringContainsString('name="item_roles['.$punkt->id.'][]" value="kueche-koch"', $html);

        $this->put(route('admin.modules.visibility', $punkt->module), [
            'item_roles' => [$punkt->id => ['kueche-koch']],
        ]);
        $this->assertSame(['kueche-koch'], $punkt->roles()->pluck('roles.role_id')->all());

        $this->put(route('admin.modules.visibility', $punkt->module), ['item_roles' => []]);
        $this->assertSame([], $punkt->roles()->pluck('roles.role_id')->all());
    }

    public function test_rollen_stehen_gruppiert_system_zuerst_dann_je_modul(): void
    {
        $this->actingAs($this->admin)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSeeInOrder(['System', 'Benutzer', 'Küche', 'Koch', 'Lehrer', 'Zeugnis', 'Zeugnis-Admin', 'Von Hand angelegt', 'Arbeitskreis Garten']);

        // In der Modul-Auswahl: erst die Zeile „System", darunter „Modulrollen".
        $this->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSeeInOrder(['System', 'Benutzer', 'Modulrollen', 'Koch', 'Lehrer']);
    }

    public function test_modulrolle_ist_im_rollen_panel_geschuetzt(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', 'kueche-koch'), ['name' => 'Anders'])
            ->assertSessionHasErrors('role');
        $this->delete(route('admin.roles.destroy', 'kueche-koch'))->assertSessionHasErrors('role');

        $this->assertSame('Koch', Role::find('kueche-koch')->name);
    }
}
