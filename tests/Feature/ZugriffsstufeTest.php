<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleMenuItem;
use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use App\Modules\Support\Zugriffsstufe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Lesen < bearbeiten < verwalten: die Stufe an der Zuordnung Menüpunkt ↔ Rolle
 * entscheidet, welche Anfragen auf einer Modulseite durchgehen.
 */
class ZugriffsstufeTest extends TestCase
{
    use RefreshDatabase;

    private ModuleMenuItem $punkt;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->prefix('modules/tm')->name('module.tm.')->group(function (): void {
            Route::get('/gerichte', fn () => Blade::render("liste @darf('bearbeiten')[knopf-bearbeiten]@enddarf @darf('verwalten')[knopf-neu]@enddarf"))->name('dishes.index');
            Route::get('/gerichte/neu', fn () => 'form-neu')->name('dishes.create');
            Route::post('/gerichte', fn () => 'angelegt')->name('dishes.store');
            Route::get('/gerichte/{id}/bearbeiten', fn () => 'form-bearbeiten')->name('dishes.edit');
            Route::put('/gerichte/{id}', fn () => 'gespeichert')->name('dishes.update');
            Route::delete('/gerichte/{id}', fn () => 'geloescht')->name('dishes.destroy');
            Route::post('/gerichte/abhaken', fn () => 'abgehakt')->name('dishes.toggle');
            Route::post('/gerichte/suchen', fn () => 'gefunden')->name('dishes.lookup');
        });

        $this->app->make(ModuleRegistry::class)->register(
            ModuleManifest::make('tm', 'Testmodul')
                ->item('dishes', 'Gerichte', 'module.tm.dishes.index')
                ->lesend('dishes.lookup')
        );

        $modul = Module::create(['key' => 'tm', 'name' => 'Testmodul', 'position' => 0, 'is_enabled' => true]);
        $this->punkt = $modul->menuItems()->create(['key' => 'dishes', 'label' => 'Gerichte', 'route_name' => 'module.tm.dishes.index', 'position' => 0]);

        Role::forceCreate(['role_id' => 'crew', 'name' => 'Crew']);
        User::factory()->create(); // erster Benutzer = Admin
    }

    private function mitStufe(string $stufe): User
    {
        $this->punkt->roles()->sync(['crew' => ['stufe' => $stufe]]);
        $user = User::factory()->create();
        $user->roles()->attach('crew');

        return $user->fresh();
    }

    /** @return array<string, int> Aktion => HTTP-Status */
    private function ergebnisse(User $user): array
    {
        $this->actingAs($user);

        return [
            'liste' => $this->get('/modules/tm/gerichte')->status(),
            'suchen' => $this->post('/modules/tm/gerichte/suchen')->status(),
            'bearbeiten-form' => $this->get('/modules/tm/gerichte/1/bearbeiten')->status(),
            'speichern' => $this->put('/modules/tm/gerichte/1')->status(),
            'abhaken' => $this->post('/modules/tm/gerichte/abhaken')->status(),
            'neu-form' => $this->get('/modules/tm/gerichte/neu')->status(),
            'anlegen' => $this->post('/modules/tm/gerichte')->status(),
            'loeschen' => $this->delete('/modules/tm/gerichte/1')->status(),
        ];
    }

    public function test_lesen_darf_nur_ansehen_und_suchen(): void
    {
        $this->assertSame([
            'liste' => 200, 'suchen' => 200,
            'bearbeiten-form' => 403, 'speichern' => 403, 'abhaken' => 403,
            'neu-form' => 403, 'anlegen' => 403, 'loeschen' => 403,
        ], $this->ergebnisse($this->mitStufe('lesen')));
    }

    public function test_bearbeiten_darf_aendern_aber_nicht_anlegen_oder_loeschen(): void
    {
        $this->assertSame([
            'liste' => 200, 'suchen' => 200,
            'bearbeiten-form' => 200, 'speichern' => 200, 'abhaken' => 200,
            'neu-form' => 403, 'anlegen' => 403, 'loeschen' => 403,
        ], $this->ergebnisse($this->mitStufe('bearbeiten')));
    }

    public function test_verwalten_darf_alles(): void
    {
        $this->assertNotContains(403, $this->ergebnisse($this->mitStufe('verwalten')));
    }

    public function test_hoechste_stufe_ueber_mehrere_rollen_gewinnt(): void
    {
        Role::forceCreate(['role_id' => 'kueche', 'name' => 'Küche']);
        $user = $this->mitStufe('lesen');
        $this->punkt->roles()->attach('kueche', ['stufe' => 'verwalten']);
        $user->roles()->attach('kueche');

        $this->actingAs($user->fresh())->delete('/modules/tm/gerichte/1')->assertOk();
    }

    public function test_blade_blendet_knoepfe_nach_stufe_aus(): void
    {
        $this->actingAs($this->mitStufe('bearbeiten'))->get('/modules/tm/gerichte')
            ->assertSee('[knopf-bearbeiten]')
            ->assertDontSee('[knopf-neu]');
    }

    public function test_admin_darf_alles(): void
    {
        $this->assertNotContains(403, $this->ergebnisse(User::where('is_admin', true)->first()));
    }

    public function test_regel_aus_anfrageart_und_routenname(): void
    {
        $this->assertSame(Zugriffsstufe::Lesen, Zugriffsstufe::benoetigt('GET', 'module.x.a.index'));
        $this->assertSame(Zugriffsstufe::Verwalten, Zugriffsstufe::benoetigt('GET', 'module.x.a.create'));
        $this->assertSame(Zugriffsstufe::Bearbeiten, Zugriffsstufe::benoetigt('GET', 'module.x.a.edit'));
        $this->assertSame(Zugriffsstufe::Verwalten, Zugriffsstufe::benoetigt('POST', 'module.x.a.store'));
        $this->assertSame(Zugriffsstufe::Bearbeiten, Zugriffsstufe::benoetigt('POST', 'module.x.a.push'));
        $this->assertSame(Zugriffsstufe::Bearbeiten, Zugriffsstufe::benoetigt('PATCH', 'module.x.a.update'));
        $this->assertSame(Zugriffsstufe::Verwalten, Zugriffsstufe::benoetigt('DELETE', 'module.x.a.destroy'));

        $manifest = ModuleManifest::make('x', 'X')->stufe('verwalten', 'a.import')->lesend('module.x.a.suche');
        $this->assertSame(Zugriffsstufe::Verwalten, Zugriffsstufe::benoetigt('POST', 'module.x.a.import', $manifest));
        $this->assertSame(Zugriffsstufe::Lesen, Zugriffsstufe::benoetigt('POST', 'module.x.a.suche', $manifest));
    }
}
