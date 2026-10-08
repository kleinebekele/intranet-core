<?php

namespace Tests\Feature;

use App\Models\Einladung;
use App\Models\MailOutbox;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Einladungen werden vorgemerkt, nicht verschickt.
 *
 * Der Sinn des Puffers: Ein Import legt hunderte Benutzer an, und verschickte
 * Mails holt man nicht zurück. Erst die Freigabe durch einen Menschen löst
 * Versand aus.
 */
class EinladungsPufferTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_vormerken_verschickt_nichts(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);

        Einladung::vormerken($user, 'Linear/BenutzerImport');

        $this->assertSame(1, Einladung::wartend()->count());
        $this->assertSame(0, MailOutbox::count(), 'Es darf ohne Freigabe keine Mail geben.');
    }

    public function test_freigeben_verschickt_genau_eine_mail(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);
        $einladung = Einladung::vormerken($user);

        $this->assertTrue($einladung->freigeben());

        $this->assertSame(1, MailOutbox::count());
        $this->assertSame(Einladung::VERSCHICKT, $einladung->fresh()->status);
    }

    public function test_zweimal_freigeben_verschickt_nicht_doppelt(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);
        $einladung = Einladung::vormerken($user);

        $einladung->freigeben();
        $this->assertFalse($einladung->freigeben());

        $this->assertSame(1, MailOutbox::count());
    }

    /** Ein zweiter Importlauf darf keine Warteschlange aus Dubletten erzeugen. */
    public function test_mehrfaches_vormerken_bleibt_eine_zeile(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);

        Einladung::vormerken($user);
        Einladung::vormerken($user);
        Einladung::vormerken($user);

        $this->assertSame(1, Einladung::count());
    }

    /** Eine bereits verschickte Einladung darf ein neuer Lauf nicht wiederbeleben. */
    public function test_verschickte_einladung_wird_nicht_zurueckgesetzt(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);
        Einladung::vormerken($user)->freigeben();

        Einladung::vormerken($user);

        $this->assertSame(Einladung::VERSCHICKT, Einladung::sole()->status);
        $this->assertSame(0, Einladung::wartend()->count());
    }

    /** Schüler mit künstlicher Adresse würden die Liste nur zumüllen. */
    public function test_kuenstliche_adressen_werden_nicht_vorgemerkt(): void
    {
        $user = User::factory()->create(['email' => 'schueler-4711@schueler.intern']);

        $this->assertNull(Einladung::vormerken($user));
        $this->assertSame(0, Einladung::count());
    }

    public function test_admin_kann_alle_auf_einmal_freigeben(): void
    {
        $admin = $this->admin();
        foreach (range(1, 3) as $i) {
            Einladung::vormerken(User::factory()->create(['email' => "person{$i}@example.org"]));
        }

        $this->actingAs($admin)->post(route('admin.einladungen.alle'))->assertRedirect();

        $this->assertSame(0, Einladung::wartend()->count());
        $this->assertSame(3, MailOutbox::count());
    }

    public function test_verwerfen_verschickt_nichts(): void
    {
        $admin = $this->admin();
        $einladung = Einladung::vormerken(User::factory()->create(['email' => 'person@example.org']));

        $this->actingAs($admin)->delete(route('admin.einladungen.verwerfen', $einladung))->assertRedirect();

        $this->assertSame(Einladung::VERWORFEN, $einladung->fresh()->status);
        $this->assertSame(0, MailOutbox::count());
    }

    public function test_admins_werden_ueber_wartende_einladungen_informiert(): void
    {
        $this->admin();
        $this->admin();
        Einladung::vormerken(User::factory()->create(['email' => 'person@example.org']));

        $benachrichtigt = Einladung::adminsBenachrichtigen('Linear/BenutzerImport');

        $this->assertSame(2, $benachrichtigt);
        // 2 Hinweis-Mails an die Admins – aber keine einzige Einladung.
        $this->assertSame(2, MailOutbox::count());
        $this->assertSame(1, Einladung::wartend()->count());
    }

    public function test_ohne_wartende_einladungen_wird_niemand_benachrichtigt(): void
    {
        $this->admin();

        $this->assertSame(0, Einladung::adminsBenachrichtigen());
        $this->assertSame(0, MailOutbox::count());
    }

    public function test_nicht_admins_kommen_nicht_an_den_puffer(): void
    {
        $this->admin(); // damit der nächste Benutzer nicht automatisch Admin wird

        $this->actingAs(User::factory()->create())
            ->get(route('admin.einladungen.index'))
            ->assertForbidden();
    }

    public function test_uebersicht_zeigt_wartende_einladungen(): void
    {
        Einladung::vormerken(User::factory()->create(['email' => 'lehrerin@example.org']));

        $this->actingAs($this->admin())
            ->get(route('admin.einladungen.index'))
            ->assertOk()
            ->assertSee('lehrerin@example.org');
    }

    /** Erst die Lehrer, die Eltern später: „Alle verschicken" mit Rolle trifft nur diese. */
    public function test_alle_freigeben_mit_rolle_trifft_nur_diese_rolle(): void
    {
        $admin = $this->admin();
        Role::firstOrCreate(['role_id' => 'teacher'], ['name' => 'Lehrer']);
        Role::firstOrCreate(['role_id' => 'parent'], ['name' => 'Eltern']);

        $lehrer = User::factory()->create(['email' => 'lehrer@example.org']);
        $lehrer->roles()->attach('teacher');
        $eltern = User::factory()->create(['email' => 'eltern@example.org']);
        $eltern->roles()->attach('parent');
        Einladung::vormerken($lehrer);
        Einladung::vormerken($eltern);

        $this->actingAs($admin)
            ->get(route('admin.einladungen.index', ['rolle' => 'teacher']))
            ->assertOk()
            ->assertSee('lehrer@example.org')
            ->assertDontSee('eltern@example.org');

        $this->actingAs($admin)->post(route('admin.einladungen.alle'), ['rolle' => 'teacher'])->assertRedirect();

        $this->assertSame(1, MailOutbox::count());
        $this->assertSame(Einladung::VERSCHICKT, Einladung::where('user_id', $lehrer->id)->sole()->status);
        $this->assertSame(Einladung::WARTEND, Einladung::where('user_id', $eltern->id)->sole()->status);
    }

    /** Die Mail geht gedrosselt raus und wird oft erst Tage später geöffnet. */
    public function test_einladungslink_gilt_auch_nach_tagen(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);
        $token = Password::broker('einladungen')->createToken($user);

        $this->travel(10)->days();

        $this->post(route('password.store'), $this->neuesPasswort($user, $token))
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('neues-Passwort-123', $user->fresh()->password));
    }

    /** „Passwort vergessen" bleibt kurz gültig – die lange Frist gilt nur für Einladungen. */
    public function test_reset_link_bleibt_kurz_gueltig(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);
        $alt = $user->password;
        $token = Password::broker()->createToken($user);

        $this->travel(2)->hours();

        $this->post(route('password.store'), $this->neuesPasswort($user, $token))
            ->assertSessionHasErrors('email');

        $this->assertSame($alt, $user->fresh()->password);
    }

    /** Ist das Passwort gesetzt, taugt der Einladungslink nicht noch einmal. */
    public function test_einladungslink_nur_einmal(): void
    {
        $user = User::factory()->create(['email' => 'lehrer@example.org']);
        $token = Password::broker('einladungen')->createToken($user);

        $this->post(route('password.store'), $this->neuesPasswort($user, $token))
            ->assertRedirect(route('login'));
        $this->post(route('password.store'), $this->neuesPasswort($user, $token, 'anderes-Passwort-456'))
            ->assertSessionHasErrors('email');
    }

    private function neuesPasswort(User $user, string $token, string $passwort = 'neues-Passwort-123'): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => $passwort,
            'password_confirmation' => $passwort,
        ];
    }
}
