<?php

namespace Tests\Feature;

use App\Mail\Vorlagen\VorlagenMailer;
use App\Models\MailOutbox;
use App\Models\MailVorlage;
use App\Models\User;
use App\Notifications\WelcomeNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bearbeitbare Mailvorlagen: Standard aus dem Code, Anpassung aus der DB,
 * beides in einen Rahmen gelegt und als HTML + Text verschickt.
 */
class MailVorlagenTest extends TestCase
{
    use RefreshDatabase;

    private function mailer(): VorlagenMailer
    {
        return app(VorlagenMailer::class);
    }

    public function test_standardvorlage_wird_gerendert_und_gerahmt(): void
    {
        $fertig = $this->mailer()->rendern('einladung', [
            'name' => 'Anna Beispiel',
            'link' => 'https://example.test/setzen',
        ]);

        $this->assertStringContainsString('Anna Beispiel', $fertig['html']);
        $this->assertStringContainsString('https://example.test/setzen', $fertig['html']);
        // Der Rahmen muss drumherum sein (Fußzeile mit Jahr).
        $this->assertStringContainsString((string) date('Y'), $fertig['html']);
        // Und der Textteil ebenfalls befüllt.
        $this->assertStringContainsString('Anna Beispiel', $fertig['text']);
        $this->assertStringContainsString('https://example.test/setzen', $fertig['text']);
    }

    public function test_gespeicherte_fassung_schlaegt_den_standard(): void
    {
        MailVorlage::create([
            'schluessel' => 'einladung',
            'betreff' => 'Mein eigener Betreff',
            'html' => '<p>Servus {{ name }}</p>',
            'text' => 'Servus {{ name }}',
        ]);

        $fertig = $this->mailer()->rendern('einladung', ['name' => 'Anna', 'link' => 'x']);

        $this->assertSame('Mein eigener Betreff', $fertig['betreff']);
        $this->assertStringContainsString('Servus Anna', $fertig['html']);
        $this->assertStringNotContainsString('Passwort jetzt festlegen', $fertig['html']);
    }

    /** Platzhalter werden ersetzt, aber niemals als Code ausgeführt. */
    public function test_platzhalter_werden_nicht_als_code_ausgefuehrt(): void
    {
        MailVorlage::create([
            'schluessel' => 'einladung',
            'betreff' => 'Test',
            'html' => '<p>{{ name }}</p>',
            'text' => '{{ name }}',
        ]);

        // Ein Name, der wie Blade/PHP aussieht, muss wörtlich erscheinen –
        // nicht ausgewertet werden.
        $fertig = $this->mailer()->rendern('einladung', [
            'name' => '{{ 7*7 }} <?php echo 12345; ?>',
            'link' => 'x',
        ]);

        // Der PHP-Tag steht wörtlich da (nicht ausgeführt) …
        $this->assertStringContainsString('<?php echo 12345; ?>', $fertig['html']);
        // … und die Blade-Rechnung wurde nicht gerechnet.
        $this->assertStringNotContainsString('49', $fertig['html']);
    }

    public function test_unbekannter_platzhalter_bleibt_stehen(): void
    {
        MailVorlage::create([
            'schluessel' => 'einladung',
            'betreff' => 'Test',
            'html' => '<p>{{ gibtsnicht }}</p>',
            'text' => '{{ gibtsnicht }}',
        ]);

        $fertig = $this->mailer()->rendern('einladung', ['name' => 'A', 'link' => 'x']);

        // Sichtbar stehen lassen, damit ein fehlender Wert beim Testen auffällt.
        $this->assertStringContainsString('{{ gibtsnicht }}', $fertig['html']);
    }

    public function test_senden_landet_im_ausgangskorb_mit_html_und_text(): void
    {
        $this->mailer()->senden('einladung', 'empfaenger@example.org', [
            'name' => 'Anna',
            'link' => 'https://example.test/x',
        ]);

        $this->assertSame(1, MailOutbox::count());
        $eintrag = MailOutbox::sole();
        $this->assertContains('empfaenger@example.org', $eintrag->an);
    }

    /** Die Einladungs-Notification benutzt die Vorlage. */
    public function test_welcome_notification_nutzt_die_vorlage(): void
    {
        MailVorlage::create([
            'schluessel' => 'einladung',
            'betreff' => 'Angepasste Einladung',
            'html' => '<p>Hallo {{ name }}, Link: {{ link }}</p>',
            'text' => 'Hallo {{ name }}',
        ]);

        $user = User::factory()->create(['email' => 'neu@example.org', 'name' => 'Neu Ling']);
        $user->notify(new WelcomeNewUser('token-123'));

        $eintrag = MailOutbox::sole();
        $this->assertSame('Angepasste Einladung', $eintrag->betreff);
    }

    public function test_kuenstliche_adresse_bekommt_auch_ueber_vorlage_nichts(): void
    {
        $this->mailer()->senden('einladung', 'schueler-1@schueler.intern', ['name' => 'S', 'link' => 'x']);

        $this->assertSame(0, MailOutbox::count());
    }
}
