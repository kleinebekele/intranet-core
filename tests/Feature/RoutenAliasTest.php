<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\RouteSetting;
use App\Models\User;
use App\Support\RoutenAliase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprechende Adressen (Verwaltung → SEO).
 *
 * Der wunde Punkt ist der Stammpfad: Eine Bereichsadresse zieht alles mit, was
 * darunter liegt – und nur das. Zeichenketten-Vergleiche auf Adressen gehen
 * genau da lautlos daneben.
 */
class RoutenAliasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->prefix('modules/tm')->name('module.tm.')->group(function (): void {
            Route::get('kategorien', fn () => 'liste')->name('categories.index');
            Route::get('kategorien/anlegen', fn () => 'anlegen')->name('categories.create');
            Route::get('kategorien/{id}/bearbeiten', fn ($id) => 'bearbeiten '.$id)->name('categories.edit');

            // Sieht wie eine Unterseite aus, ist aber keine – der Test-Kniff.
            Route::get('kategorien-import', fn () => 'import')->name('import.index');
        });
    }

    private function aliasSetzen(string $routeName, string $pfad): void
    {
        RouteSetting::create(['route_name' => $routeName, 'pfad' => $pfad]);

        app(RoutenAliase::class)->anwenden();
    }

    public function test_bereichsadresse_zieht_die_unterseiten_mit(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        $this->assertSame('/kategorien', route('module.tm.categories.index', absolute: false));
        $this->assertSame('/kategorien/anlegen', route('module.tm.categories.create', absolute: false));
        $this->assertSame('/kategorien/7/bearbeiten', route('module.tm.categories.edit', 7, absolute: false));

        $this->get('/kategorien/7/bearbeiten')->assertOk()->assertSee('bearbeiten 7');
    }

    public function test_nur_der_gleiche_namensraum_zieht_mit(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        // `kategorien-import` faengt zwar mit `kategorien` an, liegt aber nicht
        // darunter. Ohne den Schraegstrich im Vergleich waere daraus
        // `/kategorien-import` geworden – eine fremde Seite mitverschoben.
        $this->assertSame(
            '/modules/tm/kategorien-import',
            route('module.tm.import.index', absolute: false),
        );
    }

    public function test_eigener_eintrag_schlaegt_den_stammpfad(): void
    {
        RouteSetting::create(['route_name' => 'module.tm.categories.index', 'pfad' => 'kategorien']);
        RouteSetting::create(['route_name' => 'module.tm.categories.create', 'pfad' => 'neue-kategorie']);

        app(RoutenAliase::class)->anwenden();

        $this->assertSame('/neue-kategorie', route('module.tm.categories.create', absolute: false));
    }

    public function test_die_alte_adresse_leitet_weiter(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        $this->get('/modules/tm/kategorien')->assertRedirect('/kategorien');
    }

    /**
     * In der Verwaltung soll stehen, wie die Seite im Menü heißt – nicht
     * hundertmal „Index", was ja nur die Übersicht einer Gruppe bezeichnet.
     */
    public function test_die_liste_zeigt_die_beschriftung_aus_dem_menue(): void
    {
        $modul = Module::create([
            'key' => 'tm', 'name' => 'Testmodul', 'icon' => 'cog', 'position' => 0, 'is_enabled' => true,
        ]);

        $modul->menuItems()->create([
            'key' => 'categories', 'label' => 'Kategorien',
            'route_name' => 'module.tm.categories.index', 'position' => 0,
        ]);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $antwort = $this->actingAs($admin)->get(route('admin.seo.index'));

        $antwort->assertOk()
            ->assertSee('Kategorien')
            // Ohne Menüpunkt bleibt der Notbehelf – aber ohne das „Index“.
            ->assertSee('Create')
            ->assertDontSee('>Index<', false);
    }
}
