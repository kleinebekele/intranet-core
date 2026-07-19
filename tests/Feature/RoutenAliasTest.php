<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\RouteSetting;
use App\Models\User;
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

        // Erzeugt Verweise so, wie es das Menü tut: mitten in einer Anfrage.
        Route::middleware('web')->get('verweise', fn () => implode('|', [
            route('module.tm.categories.index', absolute: false),
            route('module.tm.categories.edit', 7, absolute: false),
        ]));

        // Im Betrieb macht Laravel das selbst, nachdem alle Routen geladen sind.
        // Hier kommen sie nachträglich dazu – ohne diese Zeile fände `route()`
        // sie gar nicht erst, und der Test scheiterte an der Kulisse statt an
        // der Sache.
        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * BEWUSST ohne Aufruf von `anwenden()`: Genau das war der Fehler, den die
     * erste Fassung dieser Tests nicht gefunden hat. Sie riefen die Umschreibung
     * von Hand auf und prüften damit nur die Logik – nicht, ob sie im echten
     * Ablauf überhaupt jemals läuft (sie tat es nicht). Alles hier geht deshalb
     * durch eine richtige Anfrage.
     */
    private function aliasSetzen(string $routeName, string $pfad): void
    {
        RouteSetting::create(['route_name' => $routeName, 'pfad' => $pfad]);
    }

    public function test_die_seite_ist_unter_der_neuen_adresse_erreichbar(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        $this->get('/kategorien')->assertOk()->assertSee('liste');
    }

    public function test_bereichsadresse_zieht_die_unterseiten_mit(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        $this->get('/kategorien/anlegen')->assertOk()->assertSee('anlegen');
        $this->get('/kategorien/7/bearbeiten')->assertOk()->assertSee('bearbeiten 7');
    }

    public function test_erzeugte_verweise_zeigen_auf_die_neue_adresse(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        // Nicht `route()` im Test selbst: Die Adressen gelten innerhalb einer
        // Anfrage. Genau so entstehen ja auch die Verweise im Menü.
        $this->assertSame(
            '/kategorien|/kategorien/7/bearbeiten',
            $this->get('/verweise')->assertOk()->getContent(),
        );
    }

    public function test_nur_der_gleiche_namensraum_zieht_mit(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');

        // `kategorien-import` faengt zwar mit `kategorien` an, liegt aber nicht
        // darunter. Ohne den Schraegstrich im Vergleich waere daraus
        // `/kategorien-import` geworden – eine fremde Seite mitverschoben.
        $this->get('/kategorien-import')->assertNotFound();
        $this->get('/modules/tm/kategorien-import')->assertOk()->assertSee('import');
    }

    public function test_eigener_eintrag_schlaegt_den_stammpfad(): void
    {
        $this->aliasSetzen('module.tm.categories.index', 'kategorien');
        $this->aliasSetzen('module.tm.categories.create', 'neue-kategorie');

        $this->get('/neue-kategorie')->assertOk()->assertSee('anlegen');
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
