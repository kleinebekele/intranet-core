<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verwaltung → Benutzer → Bearbeiten: Rollen nach Herkunft gruppiert; Gruppen,
 * die ein Abgleich pflegt (z. B. „Eltern Klasse 4A"), sind nur zu sehen –
 * und gehen beim Speichern nicht verloren.
 */
class BenutzerRollenauswahlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(); // erster Benutzer wird Admin
        $this->person = User::factory()->create();

        $this->app->make(ModuleRegistry::class)->register(
            ModuleManifest::make('kueche', 'Küche')
                ->item('index', 'Start', 'module.kueche.index')
                ->rolle('kueche-koch', 'Koch'),
        );
        $this->artisan('modules:sync');

        Role::create(['role_id' => 'ak-garten', 'name' => 'Arbeitskreis Garten']);
        Role::create(['role_id' => 'linear-klasse-4a-eltern', 'name' => 'Eltern Klasse 4A', 'quelle' => 'linear']);
        Role::create(['role_id' => 'linear-klasse-5b-eltern', 'name' => 'Eltern Klasse 5B', 'quelle' => 'linear']);

        $this->person->roles()->attach(['kueche-koch', 'linear-klasse-4a-eltern']);
    }

    public function test_auswahl_ist_nach_herkunft_gruppiert(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->person))
            ->assertOk()
            ->assertSeeInOrder(['System', 'Benutzer', 'Küche', 'Koch', 'Von Hand angelegt', 'Arbeitskreis Garten']);
    }

    public function test_abgeglichene_gruppen_sind_nur_zu_sehen_nicht_waehlbar(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.users.edit', $this->person))->getContent();

        // Die eigene Klassen-Gruppe steht da – aber nicht als Häkchen.
        $this->assertStringContainsString('Eltern Klasse 4A', $html);
        $this->assertStringNotContainsString('value="linear-klasse-4a-eltern"', $html);
        // Fremde Klassen-Gruppen tauchen gar nicht auf.
        $this->assertStringNotContainsString('Eltern Klasse 5B', $html);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('Eltern Klasse 4A');
    }

    public function test_speichern_laesst_abgeglichene_gruppen_unangetastet(): void
    {
        // Formular schickt nur die wählbaren Rollen – Koch abgewählt, Garten neu.
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->person), [
            'name' => $this->person->name,
            'email' => $this->person->email,
            'roles' => ['ak-garten'],
        ])->assertRedirect();

        $rollen = $this->person->roles()->pluck('roles.role_id')->sort()->values()->all();
        $this->assertSame(['ak-garten', 'linear-klasse-4a-eltern', 'user'], $rollen);
    }

    public function test_abgeglichene_gruppe_laesst_sich_nicht_per_request_vergeben(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->person), [
            'name' => $this->person->name,
            'email' => $this->person->email,
            'roles' => ['kueche-koch', 'linear-klasse-5b-eltern'],
        ])->assertRedirect();

        $this->assertFalse($this->person->roles()->where('roles.role_id', 'linear-klasse-5b-eltern')->exists());
        $this->assertTrue($this->person->roles()->where('roles.role_id', 'linear-klasse-4a-eltern')->exists());
    }
}
