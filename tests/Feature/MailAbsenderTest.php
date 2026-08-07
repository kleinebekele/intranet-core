<?php

namespace Tests\Feature;

use App\Mail\Vorlagen\VorlagenMailer;
use App\Models\MailAbsender;
use App\Models\MailOutbox;
use App\Models\User;
use App\Notifications\WelcomeNewUser;
use App\Support\Mailausloeser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul + Auslöser im Maillog und der eigenständige Absender je Kombination.
 */
class MailAbsenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function mailer(): VorlagenMailer
    {
        return app(VorlagenMailer::class);
    }

    public function test_senden_schreibt_modul_und_ausloeser(): void
    {
        $this->mailer()->senden(
            'einladung',
            'e@example.org',
            ['name' => 'Anna', 'link' => 'x'],
            quelle: 'Willkommensgruß',
            modul: 'Core',
        );

        $eintrag = MailOutbox::sole();
        $this->assertSame('Core', $eintrag->modul);
        $this->assertSame('Willkommensgruß', $eintrag->quelle);
    }

    /**
     * Eine Core-Notification, die über die Vorlage läuft, landet mit modul
     * „Core" und dem sprechenden Auslöser im Log – nicht mehr als bloße
     * Mailable-Klasse. Das beweist zugleich: der Header gewinnt über den
     * Klassennamen.
     */
    public function test_core_notification_landet_mit_sprechendem_ausloeser(): void
    {
        $user = User::factory()->create(['email' => 'neu@example.org', 'name' => 'Neu Ling']);
        $user->notify(new WelcomeNewUser('token-123'));

        $eintrag = MailOutbox::sole();
        $this->assertSame('Core', $eintrag->modul);
        $this->assertSame('Einladung (Zugang anlegen)', $eintrag->quelle);
    }

    public function test_hinterlegter_absender_gewinnt_und_landet_in_der_nachricht(): void
    {
        MailAbsender::create([
            'modul' => 'Core',
            'ausloeser' => 'Einladung (Zugang anlegen)',
            'absender_name' => 'Empfang',
            'absender_mail' => 'empfang@example.org',
            'antwort_an' => 'antwort@example.org',
        ]);

        $user = User::factory()->create(['email' => 'neu@example.org', 'name' => 'Neu Ling']);
        $user->notify(new WelcomeNewUser('token-123'));

        $email = MailOutbox::sole()->alsEmail();

        $von = $email->getFrom();
        $this->assertNotEmpty($von);
        $this->assertSame('empfang@example.org', $von[0]->getAddress());
        $this->assertSame('Empfang', $von[0]->getName());

        $antwort = $email->getReplyTo();
        $this->assertNotEmpty($antwort);
        $this->assertSame('antwort@example.org', $antwort[0]->getAddress());
    }

    public function test_absender_greift_nur_bei_exakter_kombination(): void
    {
        $zeile = MailAbsender::create([
            'modul' => 'Aftersales-Portal',
            'ausloeser' => 'Zahlungserinnerung',
            'absender_mail' => 'buchhaltung@example.org',
        ]);

        $this->assertTrue($zeile->is(MailAbsender::fuer('Aftersales-Portal', 'Zahlungserinnerung')));
        $this->assertNull(MailAbsender::fuer('Aftersales-Portal', 'Versandbestätigung'));
        $this->assertNull(MailAbsender::fuer('Core', 'Zahlungserinnerung'));
        $this->assertNull(MailAbsender::fuer(null, 'Zahlungserinnerung'));
        $this->assertNull(MailAbsender::fuer('Aftersales-Portal', null));
    }

    /** Die Core-Vorlagen melden sich als Auslöser an – vor dem ersten Versand. */
    public function test_core_vorlagen_sind_als_ausloeser_angemeldet(): void
    {
        $alle = Mailausloeser::alle();

        $this->assertArrayHasKey('Core|Einladung (Zugang anlegen)', $alle);
        $this->assertSame(
            ['modul' => 'Core', 'ausloeser' => 'Einladung (Zugang anlegen)'],
            $alle['Core|Einladung (Zugang anlegen)'],
        );
    }

    public function test_maillog_zeigt_die_modul_spalte(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mail.index'))
            ->assertOk()
            ->assertSee('Modul')
            ->assertSee('Absender je Auslöser');
    }

    public function test_konfig_seite_zeigt_bekannte_ausloeser(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mail.absender'))
            ->assertOk()
            ->assertSee('Einladung (Zugang anlegen)')
            ->assertSee('Absender je Auslöser');
    }

    public function test_speichern_legt_zeile_an_und_loescht_leere(): void
    {
        // Anlegen.
        $this->actingAs($this->admin())
            ->put(route('admin.mail.absender.speichern'), [
                'zeilen' => [
                    ['modul' => 'Core', 'ausloeser' => 'Einladung (Zugang anlegen)', 'absender_mail' => 'empfang@example.org'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mail_absender', [
            'modul' => 'Core',
            'ausloeser' => 'Einladung (Zugang anlegen)',
            'absender_mail' => 'empfang@example.org',
        ]);

        // Leeren → Zeile verschwindet wieder.
        $this->actingAs($this->admin())
            ->put(route('admin.mail.absender.speichern'), [
                'zeilen' => [
                    ['modul' => 'Core', 'ausloeser' => 'Einladung (Zugang anlegen)', 'absender_mail' => ''],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('mail_absender', [
            'modul' => 'Core',
            'ausloeser' => 'Einladung (Zugang anlegen)',
        ]);
    }
}
