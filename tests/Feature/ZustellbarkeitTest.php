<?php

namespace Tests\Feature;

use App\Models\MailOutbox;
use App\Models\User;
use App\Notifications\WelcomeNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Künstliche Adressen (`…@schueler.intern`) dürfen den Server nie verlassen.
 *
 * Geprüft wird an beiden Engstellen: beim Einliefern in den Ausgangskorb und
 * beim Ausliefern. Und zwar auch dann, wenn der Ausgangskorb abgeschaltet ist –
 * genau dort wäre die Lücke, weil der Notausgang sonst alles sofort rauslässt.
 */
class ZustellbarkeitTest extends TestCase
{
    use RefreshDatabase;

    private function mailAn(string ...$empfaenger): void
    {
        Mail::raw('Testinhalt', function ($nachricht) use ($empfaenger) {
            $nachricht->to($empfaenger)->subject('Test');
        });
    }

    public function test_kuenstliche_adresse_landet_nicht_im_ausgangskorb(): void
    {
        $this->mailAn('schueler-4711@schueler.intern');

        $this->assertSame(0, MailOutbox::count());
    }

    public function test_echte_adresse_landet_im_ausgangskorb(): void
    {
        $this->mailAn('echt@example.org');

        $this->assertSame(1, MailOutbox::count());
    }

    /**
     * Der gefährlichste Fall: Ohne Ausgangskorb geht sonst alles sofort raus –
     * die Prüfung muss VOR diesem Notausgang greifen.
     */
    public function test_greift_auch_bei_abgeschaltetem_ausgangskorb(): void
    {
        config(['mail.outbox.aktiv' => false]);

        // Bewusst KEIN Mail::fake(): Das ersetzt den Mailer und umgeht damit den
        // Listener – der Test wäre wirkungslos (genau so ist er hier zuerst
        // geschrieben worden und blieb bei abgeschalteter Sperre grün).
        // Im Test läuft der array-Transport, der Verschicktes einsammelt.
        $transport = Mail::mailer()->getSymfonyTransport();

        $this->mailAn('schueler-4711@schueler.intern');
        $this->assertCount(0, $transport->messages(), 'Künstliche Adresse wurde tatsächlich verschickt.');

        $this->mailAn('echt@example.org');
        $this->assertCount(1, $transport->messages(), 'Echte Adresse muss ohne Ausgangskorb sofort rausgehen.');
    }

    /** Eine Rundmail darf nicht an einem einzigen Schüler ohne Adresse scheitern. */
    public function test_gemischte_empfaenger_werden_bereinigt(): void
    {
        $this->mailAn('echt@example.org', 'schueler-4711@schueler.intern');

        $eintrag = MailOutbox::sole();

        $this->assertSame(['echt@example.org'], $eintrag->an);
    }

    public function test_endungen_sind_konfigurierbar(): void
    {
        config(['mail.unzustellbare_endungen' => ['.test-intern']]);

        $this->mailAn('jemand@haus.test-intern');
        $this->assertSame(0, MailOutbox::count());

        // `.intern` ist jetzt NICHT mehr gesperrt – die Liste ersetzt den Standard.
        $this->mailAn('schueler-4711@schueler.intern');
        $this->assertSame(1, MailOutbox::count());
    }

    /** Zeilen aus der Zeit vor dieser Prüfung dürfen nicht doch noch rausgehen. */
    public function test_ausliefern_ueberspringt_unzustellbare_zeile(): void
    {
        config(['mail.unzustellbare_endungen' => []]); // Einliefern erlauben
        $this->mailAn('schueler-4711@schueler.intern');
        config(['mail.unzustellbare_endungen' => ['.intern']]);

        $this->artisan('mail:ausliefern')->assertSuccessful();

        $eintrag = MailOutbox::sole();
        $this->assertSame(MailOutbox::FEHLGESCHLAGEN, $eintrag->status);
        $this->assertStringContainsString('Kein zustellbarer Empfänger', (string) $eintrag->fehler);
    }

    /** Benutzer mit künstlicher Adresse bekommen auch keine Einladung. */
    public function test_notification_an_kuenstliche_adresse_geht_nicht_raus(): void
    {
        $user = User::factory()->create(['email' => 'schueler-4711@schueler.intern']);

        $user->notify(new WelcomeNewUser('token123'));

        $this->assertSame(0, MailOutbox::count());
    }
}
