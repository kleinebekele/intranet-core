<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use App\Modules\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Ein Modul ohne (sichtbare) Unterseiten gehört nicht in Sidebar und Dashboard –
 * es hätte dort nur ein totes Ziel. In der Modulverwaltung bleibt es sichtbar,
 * sonst könnte man es weder einordnen noch entfernen.
 */
class NavigationLeereModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->prefix('modules/voll')->name('module.voll.')->group(function (): void {
            Route::get('/', fn () => 'home')->name('index');
        });

        // Beide Pakete sind installiert – nur eines bringt eine Seite mit.
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register(ModuleManifest::make('voll', 'Volles Modul')->item('index', 'Start', 'module.voll.index'));
        $registry->register(ModuleManifest::make('leer', 'Leeres Modul'));

        $voll = Module::create(['key' => 'voll', 'name' => 'Volles Modul', 'position' => 0, 'is_enabled' => true]);
        $voll->menuItems()->create(['key' => 'index', 'label' => 'Start', 'route_name' => 'module.voll.index', 'position' => 0]);

        Module::create(['key' => 'leer', 'name' => 'Leeres Modul', 'position' => 1, 'is_enabled' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user->fresh();
    }

    public function test_navigation_laesst_modul_ohne_seiten_weg(): void
    {
        $this->actingAs($this->admin());

        $keys = $this->app->make(Navigation::class)->modules()->pluck('key')->all();

        $this->assertSame(['voll'], $keys);
    }

    public function test_dashboard_zeigt_modul_ohne_seiten_nicht(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertOk()
            ->assertSee('Volles Modul')
            ->assertDontSee('Leeres Modul');
    }

    public function test_modulverwaltung_zeigt_es_weiterhin(): void
    {
        $this->actingAs($this->admin())->get('/admin/modules')
            ->assertOk()
            ->assertSee('Leeres Modul');
    }

    public function test_modul_dessen_punkte_der_benutzer_nicht_sehen_darf_faellt_ebenfalls_weg(): void
    {
        $this->admin(); // damit der nächste Benutzer nicht der erste (=Admin) ist
        $benutzer = User::factory()->create()->fresh();

        // Der Punkt hat keine Rollen -> Default-Deny, für diesen Benutzer unsichtbar.
        $keys = $this->actingAs($benutzer)->app->make(Navigation::class)->modules()->pluck('key')->all();

        $this->assertSame([], $keys);
    }
}
