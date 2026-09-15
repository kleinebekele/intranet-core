<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Benutzerbereiche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenutzerbereicheTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Benutzerbereiche::zuruecksetzen();
        parent::tearDown();
    }

    public function test_bereich_erscheint_auf_der_bearbeiten_seite(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Testperson']);

        Benutzerbereiche::registrieren('test', fn (User $u) => "<p>Bereich für {$u->name}</p>");
        Benutzerbereiche::registrieren('leer', fn (User $u) => null);

        $this->actingAs($admin)->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Bereich für Testperson', false)
            ->assertSee('data-benutzerbereich="test"', false)
            ->assertDontSee('data-benutzerbereich="leer"', false);
    }

    public function test_fehler_im_bereich_bricht_die_seite_nicht(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        Benutzerbereiche::registrieren('kaputt', function () {
            throw new \RuntimeException('Fremdsystem weg');
        });

        $this->actingAs($admin)->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('konnte nicht geladen werden')
            ->assertSee('Fremdsystem weg');
    }
}
