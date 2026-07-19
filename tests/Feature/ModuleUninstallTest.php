<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `modules:uninstall` – das Gegenstück zu `modules:sync`.
 *
 * Der Standard räumt nur die Registrierung ab; Tabellen fasst er erst mit
 * --mit-daten an. Genau diese Trennung sichern die Tests ab.
 */
class ModuleUninstallTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Module::create([
            'key' => 'tm', 'name' => 'Testmodul', 'position' => 0, 'is_enabled' => true,
        ]);

        $rolle = Role::create(['role_id' => 'tester', 'name' => 'Tester']);
        $punkt = $this->module->menuItems()->create([
            'key' => 'index', 'label' => 'Start', 'route_name' => 'module.tm.index', 'position' => 0,
        ]);
        $punkt->roles()->attach($rolle->role_id);

        DB::table('route_settings')->insert([
            ['route_name' => 'module.tm.index', 'pfad' => 'testmodul', 'created_at' => now(), 'updated_at' => now()],
            ['route_name' => 'module.news.index', 'pfad' => 'neuigkeiten', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** Meldet das fiktive Paket samt Migrationsverzeichnis in der Registry an. */
    private function paketInstallieren(): void
    {
        $manifest = ModuleManifest::make('tm', 'Testmodul')->item('index', 'Start', 'module.tm.index');
        $manifest->basePath = base_path('tests/Fixtures/testmodul');

        $this->app->make(ModuleRegistry::class)->register($manifest);
    }

    /** Legt die Modultabelle an und trägt die Migration als gelaufen ein. */
    private function migrationLaufenLassen(): void
    {
        Schema::create('tm_dinge', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        DB::table('tm_dinge')->insert(['name' => 'Testeintrag']);
        DB::table('migrations')->insert([
            'migration' => '2026_01_01_000000_create_tm_dinge_table',
            'batch' => 99,
        ]);
    }

    public function test_standard_entfernt_nur_die_registrierung(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        $this->artisan('modules:uninstall tm')
            ->expectsConfirmation('Modul „tm" aus der Registrierung entfernen?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
        $this->assertDatabaseMissing('module_menu_items', ['route_name' => 'module.tm.index']);
        $this->assertDatabaseMissing('route_settings', ['route_name' => 'module.tm.index']);
        // Fremde Adressen bleiben unberührt.
        $this->assertDatabaseHas('route_settings', ['route_name' => 'module.news.index']);
        // Ohne --mit-daten bleiben Tabelle und Inhalt stehen.
        $this->assertTrue(Schema::hasTable('tm_dinge'));
        $this->assertSame(1, DB::table('tm_dinge')->count());
    }

    public function test_mit_daten_rollt_die_migrationen_des_moduls_zurueck(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        $this->artisan('modules:uninstall tm --mit-daten')
            ->expectsConfirmation('Modul „tm" entfernen UND seine Tabellen samt Inhalt löschen?', 'yes')
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('tm_dinge'));
        $this->assertDatabaseMissing('migrations', ['migration' => '2026_01_01_000000_create_tm_dinge_table']);
        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
    }

    public function test_abgelehnte_rueckfrage_veraendert_nichts(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        $this->artisan('modules:uninstall tm --mit-daten')
            ->expectsConfirmation('Modul „tm" entfernen UND seine Tabellen samt Inhalt löschen?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseHas('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));
    }

    public function test_probelauf_veraendert_nichts(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        $this->artisan('modules:uninstall tm --mit-daten --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));
    }

    /** Aufzeichnung, wie sie `modules:sync` beim letzten Lauf hinterlassen hätte. */
    private function aufzeichnungAusFrueheremSync(): void
    {
        DB::table('module_migrations')->insert([
            'module_key' => 'tm',
            'migration' => '2026_01_01_000000_create_tm_dinge_table',
            'tabellen' => json_encode(['tm_dinge']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Der Kern der Sache: Ist das Paket weg, gibt es kein down() mehr – die
     * Tabelle muss sich trotzdem entfernen lassen, sonst bleibt sie ewig stehen.
     */
    public function test_mit_daten_raeumt_auch_ohne_paket_auf(): void
    {
        $this->migrationLaufenLassen();
        $this->aufzeichnungAusFrueheremSync();

        $this->artisan('modules:uninstall tm --mit-daten')
            ->expectsConfirmation('Modul „tm" entfernen UND seine Tabellen samt Inhalt löschen?', 'yes')
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('tm_dinge'));
        $this->assertDatabaseMissing('migrations', ['migration' => '2026_01_01_000000_create_tm_dinge_table']);
        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
        // Die Aufzeichnung selbst wird mit weggeräumt.
        $this->assertDatabaseMissing('module_migrations', ['module_key' => 'tm']);
    }

    /**
     * Ohne Paket UND ohne Aufzeichnung weiß niemand, welche Tabelle dazugehört.
     * Dann bleibt sie stehen – aber die Registrierung verschwindet trotzdem.
     */
    public function test_ohne_aufzeichnung_bleibt_die_tabelle_stehen(): void
    {
        $this->migrationLaufenLassen();

        $this->artisan('modules:uninstall tm --mit-daten')
            ->expectsConfirmation('Modul „tm" entfernen UND seine Tabellen samt Inhalt löschen?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));
    }

    public function test_sync_zeichnet_die_migrationen_des_moduls_auf(): void
    {
        $this->paketInstallieren();

        $this->artisan('modules:sync')->assertSuccessful();

        $this->assertDatabaseHas('module_migrations', [
            'module_key' => 'tm',
            'migration' => '2026_01_01_000000_create_tm_dinge_table',
        ]);
    }

    public function test_knopf_raeumt_auch_ohne_paket_die_tabelle_ab(): void
    {
        $this->migrationLaufenLassen();
        $this->aufzeichnungAusFrueheremSync();

        $this->actingAs($this->admin())
            ->delete(route('admin.modules.destroy', $this->module), ['mit_daten' => 1, 'bestaetigung' => 'tm'])
            ->assertRedirect(route('admin.modules.index'));

        $this->assertFalse(Schema::hasTable('tm_dinge'));
        $this->assertDatabaseMissing('modules', ['key' => 'tm']);

        // Ohne Paket lief kein down() – die Meldung darf das nicht behaupten.
        $this->assertStringNotContainsString('zurückgerollt', (string) session('status'));
        $this->assertStringContainsString('ohne down()', (string) session('status'));
    }

    public function test_verwaiste_registrierung_laesst_sich_aufraeumen(): void
    {
        $this->migrationLaufenLassen();

        $this->artisan('modules:uninstall tm')
            ->expectsConfirmation('Modul „tm" aus der Registrierung entfernen?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));
    }

    public function test_unbekannter_schluessel_meldet_fehler(): void
    {
        $this->artisan('modules:uninstall gibtsnicht')->assertFailed();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_knopf_im_backend_entfernt_nur_die_registrierung(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        $this->actingAs($this->admin())
            ->delete(route('admin.modules.destroy', $this->module))
            ->assertRedirect(route('admin.modules.index'));

        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));
    }

    public function test_knopf_loescht_daten_nur_mit_getipptem_schluessel(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        // Falscher Bestätigungstext: nichts darf passieren.
        $this->actingAs($this->admin())
            ->from(route('admin.modules.index'))
            ->delete(route('admin.modules.destroy', $this->module), ['mit_daten' => 1, 'bestaetigung' => 'tippfehler'])
            ->assertSessionHasErrors('bestaetigung');

        $this->assertDatabaseHas('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));

        // Mit richtigem Schlüssel geht es durch.
        $this->actingAs($this->admin())
            ->delete(route('admin.modules.destroy', $this->module), ['mit_daten' => 1, 'bestaetigung' => 'tm'])
            ->assertRedirect(route('admin.modules.index'));

        $this->assertFalse(Schema::hasTable('tm_dinge'));
        $this->assertDatabaseMissing('modules', ['key' => 'tm']);
    }

    public function test_knopf_verlangt_den_schluessel_auch_ohne_paket(): void
    {
        $this->migrationLaufenLassen();
        $this->aufzeichnungAusFrueheremSync();

        $this->actingAs($this->admin())
            ->from(route('admin.modules.index'))
            ->delete(route('admin.modules.destroy', $this->module), ['mit_daten' => 1, 'bestaetigung' => 'tippfehler'])
            ->assertSessionHasErrors('bestaetigung');

        $this->assertDatabaseHas('modules', ['key' => 'tm']);
        $this->assertTrue(Schema::hasTable('tm_dinge'));
    }

    public function test_nicht_admins_duerfen_kein_modul_entfernen(): void
    {
        $this->admin(); // damit der nächste Benutzer nicht automatisch Admin wird

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.modules.destroy', $this->module))
            ->assertForbidden();

        $this->assertDatabaseHas('modules', ['key' => 'tm']);
    }

    /**
     * Ist das Paket nicht installiert, kennt niemand seinen Namen. Dann darf
     * dort KEIN abtippbarer composer-Befehl stehen: Ein Platzhalter wie
     * "vendor/module-x" wird kopiert und löst auf dem Server ein ungewolltes
     * composer update aus (genau so passiert, 2026-07-20).
     */
    public function test_ohne_paket_steht_kein_erfundener_composer_befehl_da(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertDontSee('composer remove vendor/')
            ->assertDontSee('composer remove &lt;vendor&gt;/', false);

        $this->actingAs($this->admin())
            ->delete(route('admin.modules.destroy', $this->module))
            ->assertRedirect(route('admin.modules.index'));

        $this->assertStringNotContainsString('composer remove', (string) session('status'));
        $this->assertStringContainsString('composer.json', (string) session('status'));
    }

    public function test_uebersicht_zeigt_tabellen_und_zeilenzahl(): void
    {
        $this->paketInstallieren();
        $this->migrationLaufenLassen();

        $this->actingAs($this->admin())
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('tm_dinge')
            ->assertSee('1 Zeilen');
    }
}
