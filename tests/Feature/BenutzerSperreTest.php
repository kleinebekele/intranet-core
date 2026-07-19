<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gesperrte Konten bleiben bestehen, kommen aber nicht mehr hinein.
 *
 * Der praktische Regelfall beim Linear-Abgleich: Gesperrt wird nachts vom Task,
 * die Sitzung im Browser ist von gestern noch offen. Die Sperre muss deshalb
 * auch mitten in einer laufenden Sitzung greifen, nicht erst beim nächsten
 * Anmelden.
 */
class BenutzerSperreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_gesperrter_benutzer_kommt_nicht_rein(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);
        $user->sperren('Schule verlassen');

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ungesperrter_benutzer_kommt_weiterhin_rein(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_sperre_beendet_die_laufende_sitzung_sofort(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->sperren('Schule verlassen');

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_entsperren_macht_das_konto_wieder_nutzbar(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);
        $user->sperren('versehentlich');
        $user->entsperren();

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_sperren_ueberschreibt_den_ersten_grund_nicht(): void
    {
        $user = User::factory()->create();
        $user->sperren('erster Grund');
        $zeitpunkt = $user->fresh()->gesperrt_am;

        $user->fresh()->sperren('zweiter Grund');

        $this->assertSame('erster Grund', $user->fresh()->gesperrt_grund);
        $this->assertEquals($zeitpunkt, $user->fresh()->gesperrt_am);
    }

    public function test_admin_kann_ueber_die_verwaltung_sperren_und_freigeben(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.sperre', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->istGesperrt());

        $this->actingAs($admin)->post(route('admin.users.sperre', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->istGesperrt());
    }

    /** Sonst könnte sich der letzte Administrator mit einem Klick aussperren. */
    public function test_niemand_kann_sich_selbst_sperren(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.sperre', $admin))->assertSessionHas('error');

        $this->assertFalse($admin->fresh()->istGesperrt());
    }

    public function test_die_meldung_ist_kein_roher_uebersetzungsschluessel(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);
        $user->sperren();

        // Literale geprüft, nicht über trans() – sonst verglichen wir den
        // fehlenden Schlüssel mit sich selbst und der Test wäre blind.
        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234'])
            ->assertSessionHasErrors(['email' => 'Dieses Konto ist gesperrt. Bitte wende dich an die Verwaltung.']);

        // Und in einer Instanz auf Standard-Sprache (en) ebenfalls Klartext:
        // Dort fehlt der Schlüssel in Laravels Mitgeliefertem, deshalb lang/en/auth.php.
        $this->app->setLocale('en');

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234'])
            ->assertSessionHasErrors(['email' => 'This account is blocked. Please contact the administration.']);
    }
}
