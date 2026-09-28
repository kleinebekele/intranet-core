<?php

namespace Tests\Feature;

use App\Models\MailOutbox;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Maillog: Tooltip an der Empfängeradresse mit Benutzer und Rollen.
 */
class MailOutboxAnsichtTest extends TestCase
{
    use RefreshDatabase;

    public function test_empfaenger_zeigt_benutzer_und_rollen(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $eltern = User::factory()->create(['name' => 'Erika Muster', 'email' => 'erika@example.org']);
        Role::create(['role_id' => 'eltern-klasse-3', 'name' => 'Eltern Klasse 3']);
        $eltern->roles()->attach('eltern-klasse-3');

        MailOutbox::create([
            'status' => MailOutbox::FEHLGESCHLAGEN,
            'betreff' => 'Inforum',
            'an' => ['Erika@Example.org', 'fremd@example.org'],
            'fehler' => '421 too many connections',
            'nachricht' => MailOutbox::verpacken((new Email)->from('a@example.org')->to('erika@example.org')->text('x')),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.mail.index'))
            ->assertOk()
            ->assertSee(route('admin.users.edit', $eltern), false)
            ->assertSee('Erika Muster')
            ->assertSee('Eltern Klasse 3')
            ->assertSee('Kein Benutzer mit dieser Adresse.')
            ->assertSee('a@example.org')
            ->assertSee('über Standard-Mailer')
            ->assertSee(route('admin.mail.verwerfen', MailOutbox::first()), false);
    }

    private function mail(string $status, array $werte = []): MailOutbox
    {
        return MailOutbox::create($werte + [
            'status' => $status,
            'betreff' => 'Test',
            'an' => ['x@example.org'],
            'nachricht' => MailOutbox::verpacken((new Email)->from('a@example.org')->to('x@example.org')->text('x')),
        ]);
    }

    public function test_suche_und_filter(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $konto = \App\Models\MailKonto::create([
            'bezeichnung' => 'Newsletter', 'absender_mail' => 'news@example.org',
            'host' => 'smtp.example.org', 'port' => 587, 'verschluesselung' => 'tls', 'aktiv' => true,
        ]);

        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Inforum September', 'modul' => 'Newsletter', 'mailer' => $konto->mailerName(), 'an' => ['eltern@example.org']]);
        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Passwort zurücksetzen', 'modul' => null, 'an' => ['lehrer@example.org']]);

        $seite = fn (array $filter) => $this->actingAs($admin)->get(route('admin.mail.index', $filter))->assertOk();

        $seite(['suche' => 'inforum'])->assertSee('Inforum September')->assertDontSee('Passwort zurücksetzen');
        $seite(['suche' => 'lehrer@'])->assertSee('Passwort zurücksetzen')->assertDontSee('Inforum September');
        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Speiseplan', 'modul' => 'Kantine']);

        $seite(['modul' => ['Core']])->assertSee('Passwort zurücksetzen')->assertDontSee('Inforum September');
        $seite(['modul' => ['Newsletter']])->assertSee('Inforum September')->assertDontSee('Passwort zurücksetzen');
        $seite(['modul' => ['Core', 'Kantine']])->assertSee('Passwort zurücksetzen')->assertSee('Speiseplan')
            ->assertDontSee('Inforum September');

        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Ausgabe Oktober', 'quelle' => 'Newsletter', 'modul' => null]);
        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Code 123', 'quelle' => 'App\\Mail\\TwoFactorCodeMail']);

        $seite(['ausloeser' => ['Newsletter']])->assertSee('Ausgabe Oktober')->assertDontSee('Code 123');
        $seite(['ausloeser' => ['Newsletter', 'App\\Mail\\TwoFactorCodeMail']])->assertSee('Ausgabe Oktober')->assertSee('Code 123')
            ->assertDontSee('Speiseplan');
        $seite([])->assertSee('value="App\\Mail\\TwoFactorCodeMail"', false)->assertSee('TwoFactorCodeMail');

        // Auswahl zeigt nur Module mit Mails.
        $seite([])->assertSee('value="Kantine"', false)->assertDontSee('value="Wiki"', false);
        $seite(['absender' => $konto->mailerName()])->assertSee('Inforum September')->assertDontSee('Passwort zurücksetzen');
        $seite(['absender' => 'standard'])->assertSee('Passwort zurücksetzen')->assertDontSee('Inforum September');
    }

    public function test_sortierung_offene_oben_dann_versandzeit_absteigend(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        // Eingangsreihenfolge bewusst anders als die Versandreihenfolge.
        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Zweitneuester Versand', 'versendet_am' => now()->subHour()]);
        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Neuester Versand', 'versendet_am' => now()]);
        $this->mail(MailOutbox::FEHLGESCHLAGEN, ['betreff' => 'Offen gescheitert']);
        $this->mail(MailOutbox::VERSENDET, ['betreff' => 'Aeltester Versand', 'versendet_am' => now()->subDay()]);
        $this->mail(MailOutbox::WARTEND, ['betreff' => 'Offen wartend']);

        $this->actingAs($admin)->get(route('admin.mail.index'))->assertOk()->assertSeeInOrder([
            'Offen wartend', 'Offen gescheitert', 'Neuester Versand', 'Zweitneuester Versand', 'Aeltester Versand',
        ]);
    }

    public function test_verworfene_nur_unter_verworfen(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->mail(MailOutbox::VERWORFEN, ['betreff' => 'Abgehakte Mail']);
        $this->mail(MailOutbox::FEHLGESCHLAGEN, ['betreff' => 'Offene Mail']);

        $this->actingAs($admin)->get(route('admin.mail.index'))
            ->assertSee('Offene Mail')->assertDontSee('Abgehakte Mail');
        $this->actingAs($admin)->get(route('admin.mail.index', ['status' => 'verworfen']))
            ->assertSee('Abgehakte Mail')->assertDontSee('Offene Mail');
    }

    public function test_verwerfen_von_hand(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $gescheitert = $this->mail(MailOutbox::FEHLGESCHLAGEN, ['fehler' => '550 unbekannt']);
        $versendet = $this->mail(MailOutbox::VERSENDET);

        $this->actingAs($admin)->post(route('admin.mail.verwerfen', $gescheitert))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.mail.verwerfen', $versendet))->assertSessionHasErrors();

        $this->assertSame(MailOutbox::VERWORFEN, $gescheitert->fresh()->status);
        $this->assertNotNull($gescheitert->fresh()->verworfen_am);
        $this->assertSame(MailOutbox::VERSENDET, $versendet->fresh()->status);

        // Erneut holt sie zurück in die Warteschlange.
        $this->actingAs($admin)->post(route('admin.mail.erneut', $gescheitert))->assertRedirect();
        $this->assertSame(MailOutbox::WARTEND, $gescheitert->fresh()->status);
        $this->assertNull($gescheitert->fresh()->verworfen_am);
    }

    public function test_aufraeumen_verwirft_nach_10_und_loescht_nach_30_tagen(): void
    {
        $this->travel(-31)->days();
        $uralt = $this->mail(MailOutbox::FEHLGESCHLAGEN);
        $alterVersand = $this->mail(MailOutbox::VERSENDET);
        $this->travel(20)->days();
        $elfTage = $this->mail(MailOutbox::FEHLGESCHLAGEN);
        $this->travelBack();
        $frisch = $this->mail(MailOutbox::FEHLGESCHLAGEN);

        $this->artisan('mail:aufraeumen')->assertSuccessful();

        // Über 30 Tage alt: erst verworfen, im selben Lauf gelöscht.
        $this->assertNull($uralt->fresh());
        $this->assertSame(MailOutbox::VERWORFEN, $elfTage->fresh()->status);
        $this->assertSame(MailOutbox::FEHLGESCHLAGEN, $frisch->fresh()->status);
        $this->assertNotNull($alterVersand->fresh());
    }
}
