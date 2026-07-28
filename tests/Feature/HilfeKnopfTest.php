<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Hilfe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Kontexthilfe ist eine Einbahnstraße: Der Core fragt, ein Modul antwortet.
 * Ohne Anbieter darf im Layout nichts erscheinen – sonst hätte jede Instanz
 * ohne Wiki einen toten „?"-Knopf in der Kopfzeile.
 */
class HilfeKnopfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Hilfe::vergessen();
    }

    protected function tearDown(): void
    {
        Hilfe::vergessen();

        parent::tearDown();
    }

    public function test_ohne_anbieter_erscheint_kein_hilfe_knopf(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Hilfe zu dieser Seite');
    }

    public function test_anbieter_bringt_den_knopf_in_die_kopfzeile(): void
    {
        Hilfe::anbieten(fn (string $route) => $route === 'dashboard' ? 'https://beispiel.test/wiki/start' : null);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hilfe zu dieser Seite')
            ->assertSee('https://beispiel.test/wiki/start');
    }

    public function test_ohne_treffer_zur_route_bleibt_der_knopf_weg(): void
    {
        Hilfe::anbieten(fn (string $route) => $route === 'gibt.es.nicht' ? 'https://beispiel.test/wiki/start' : null);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Hilfe zu dieser Seite');
    }

    public function test_der_erste_anbieter_mit_treffer_gewinnt(): void
    {
        Hilfe::anbieten(fn () => null);
        Hilfe::anbieten(fn () => 'https://beispiel.test/erste');
        Hilfe::anbieten(fn () => 'https://beispiel.test/zweite');

        $this->assertSame('https://beispiel.test/erste', Hilfe::url('dashboard'));
    }

    public function test_der_anbieter_bekommt_den_benutzer(): void
    {
        $user = User::factory()->create();

        Hilfe::anbieten(fn (string $route, ?User $u) => $u?->id === $user->id ? 'https://beispiel.test/treffer' : null);

        $this->assertSame('https://beispiel.test/treffer', Hilfe::url('dashboard', $user));
        $this->assertNull(Hilfe::url('dashboard', User::factory()->create()));
    }
}
