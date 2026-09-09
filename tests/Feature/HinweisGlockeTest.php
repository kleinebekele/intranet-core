<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Hinweis;
use App\Support\Hinweise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Die Glocke ist eine Einbahnstraße: Module melden, der Core zeigt. Sie ist
 * immer da (grau), zählt die Einzelfälle in den roten Punkt und verlinkt
 * jeden Hinweis auf die Seite, auf der man ihn beheben kann.
 */
class HinweisGlockeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Hinweise::vergessen();
    }

    protected function tearDown(): void
    {
        Hinweise::vergessen();

        parent::tearDown();
    }

    public function test_ohne_anbieter_ist_die_glocke_da_aber_leer(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Keine offenen Hinweise')
            ->assertSee('Nichts offen');
    }

    public function test_hinweise_erscheinen_mit_zahl_und_link(): void
    {
        Hinweise::anbieten(fn (User $user) => [
            new Hinweis('3 Benachrichtigungen nicht zugestellt', 'https://beispiel.test/ekkon/benachrichtigungen', 'Ekkon', 3),
            new Hinweis('Zertifikat läuft ab', 'https://beispiel.test/zertifikat'),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('4 offene Hinweise')
            ->assertSee('3 Benachrichtigungen nicht zugestellt')
            ->assertSee('https://beispiel.test/ekkon/benachrichtigungen')
            ->assertSee('Zertifikat läuft ab')
            ->assertDontSee('Nichts offen');
    }

    public function test_anbieter_sieht_den_benutzer(): void
    {
        Hinweise::anbieten(fn (User $user) => $user->isAdmin()
            ? [new Hinweis('Nur für Admins', 'https://beispiel.test/admin')]
            : []);

        $admin = User::factory()->create(); // der erste Benutzer wird automatisch Admin
        $normal = User::factory()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Nur für Admins');
        $this->actingAs($normal)->get(route('dashboard'))->assertOk()->assertDontSee('Nur für Admins');
    }

    public function test_ein_kaputter_anbieter_reisst_die_seite_nicht_mit(): void
    {
        Hinweise::anbieten(function (User $user): array {
            throw new RuntimeException('Tabelle fehlt');
        });
        Hinweise::anbieten(fn (User $user) => [new Hinweis('Geht noch', 'https://beispiel.test/ok')]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Geht noch')
            ->assertSee('1 offene Hinweise');
    }
}
