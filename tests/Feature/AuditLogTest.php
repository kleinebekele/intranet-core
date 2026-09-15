<?php

namespace Tests\Feature;

use App\Models\AuditEintrag;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit-Log: Anmeldungen und Verwaltungsaktionen landen in audit_log,
 * der Zeitpunkt der letzten Anmeldung steht am Benutzer.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_anmeldung_setzt_zeitpunkt_und_schreibt_eintrag(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);
        $this->assertNull($user->zuletzt_angemeldet_am);

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->zuletzt_angemeldet_am);

        $eintrag = AuditEintrag::where('aktion', 'anmeldung')->sole();
        $this->assertSame($user->id, $eintrag->user_id);
        $this->assertSame('passwort', $eintrag->daten['weg']);
    }

    public function test_falsches_passwort_wird_als_fehlversuch_protokolliert(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'falsch']);

        $this->assertGuest();
        $this->assertNull($user->fresh()->zuletzt_angemeldet_am);

        $eintrag = AuditEintrag::where('aktion', 'anmeldung.fehlgeschlagen')->sole();
        $this->assertNull($eintrag->user_id);
        $this->assertSame($user->id, $eintrag->betroffener_id);
        $this->assertDatabaseMissing('audit_log', ['aktion' => 'anmeldung']);
    }

    public function test_gesperrtes_konto_hinterlaesst_keine_anmeldung(): void
    {
        $user = User::factory()->create(['password' => bcrypt('geheim-1234')]);
        $user->sperren('Schule verlassen');

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim-1234'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($user->fresh()->zuletzt_angemeldet_am);
        $this->assertDatabaseMissing('audit_log', ['aktion' => 'anmeldung']);
        $this->assertDatabaseMissing('audit_log', ['aktion' => 'abmeldung']);
        $this->assertDatabaseHas('audit_log', ['aktion' => 'anmeldung.fehlgeschlagen', 'betroffener_id' => $user->id]);
    }

    public function test_abmeldung_wird_protokolliert(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('audit_log', ['aktion' => 'abmeldung', 'user_id' => $user->id]);
    }

    public function test_sperren_in_der_verwaltung_nennt_admin_und_betroffenen(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.sperre', $user));

        $eintrag = AuditEintrag::where('aktion', 'benutzer.gesperrt')->sole();
        $this->assertSame($admin->id, $eintrag->user_id);
        $this->assertSame($admin->name, $eintrag->akteur);
        $this->assertSame($user->id, $eintrag->betroffener_id);
    }

    public function test_benutzer_aendern_haelt_vorher_nachher_fest(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Alt']);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Neu',
            'email' => $user->email,
            'roles' => [],
        ]);

        $eintrag = AuditEintrag::where('aktion', 'benutzer.geaendert')->sole();
        $this->assertSame('Alt', $eintrag->daten['vorher']['name']);
        $this->assertSame('Neu', $eintrag->daten['nachher']['name']);
        $this->assertArrayNotHasKey('email', $eintrag->daten['nachher']);
    }

    public function test_admin_kann_email_aendern_und_es_wird_protokolliert(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['email' => 'alt@example.com']);
        $andere = User::factory()->create(['email' => 'belegt@example.com']);

        // Belegte Adresse wird abgelehnt.
        $this->actingAs($admin)->from(route('admin.users.edit', $user))
            ->put(route('admin.users.update', $user), ['name' => $user->name, 'email' => 'belegt@example.com', 'roles' => []])
            ->assertSessionHasErrors('email');
        $this->assertSame('alt@example.com', $user->fresh()->email);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => ' Neu@Example.com ',
            'roles' => [],
        ]);

        $user->refresh();
        $this->assertSame('neu@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);

        $eintrag = AuditEintrag::where('aktion', 'benutzer.geaendert')->sole();
        $this->assertSame('alt@example.com', $eintrag->daten['vorher']['email']);
        $this->assertSame('neu@example.com', $eintrag->daten['nachher']['email']);
    }

    public function test_eintrag_ueberlebt_das_loeschen_des_kontos(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Verschwindet']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $eintrag = AuditEintrag::where('aktion', 'benutzer.geloescht')->sole();
        $this->assertNull($eintrag->betroffener_id);
        $this->assertSame('Verschwindet', $eintrag->betroffener);
    }

    public function test_audit_seite_zeigt_und_filtert(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Filterkandidat']);

        Audit::schreiben('benutzer.gesperrt', 'Testgrund', $user, akteur: $admin);
        Audit::schreiben('modul.umgeschaltet', 'Aktiviert.', ziel: 'Modul kantine', akteur: $admin);

        $this->actingAs($admin)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Filterkandidat')
            ->assertSee('Modul kantine');

        $this->actingAs($admin)->get(route('admin.audit.index', ['aktion' => 'benutzer.gesperrt']))
            ->assertOk()
            ->assertSee('Filterkandidat')
            ->assertDontSee('Modul kantine');

        $this->actingAs($admin)->get(route('admin.audit.index', ['user' => $user->id]))
            ->assertOk()
            ->assertSee('Verlauf von')
            ->assertDontSee('Modul kantine');
    }

    public function test_benutzerliste_zeigt_angelegt_und_zuletzt_angemeldet(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->forceFill(['zuletzt_angemeldet_am' => now()->subDays(3)])->save();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Zuletzt angemeldet')
            ->assertSee($user->zuletzt_angemeldet_am->format('d.m.Y H:i'))
            ->assertSee('noch nie'); // der Admin selbst (actingAs meldet nicht an)
    }

    public function test_module_koennen_aktionen_benennen(): void
    {
        Audit::benennen(['kantine.storniert' => 'Kantine: storniert']);

        $this->assertSame('Kantine: storniert', Audit::bezeichnung('kantine.storniert'));
        $this->assertSame('unbekannt.x', Audit::bezeichnung('unbekannt.x'));
        $this->assertArrayHasKey('kantine.storniert', Audit::bekannteAktionen());
    }

    public function test_nicht_admins_sehen_das_audit_nicht(): void
    {
        $this->admin(); // der erste Benutzer wird automatisch Admin

        $this->actingAs(User::factory()->create())
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }
}
