<?php

namespace Tests\Feature;

use App\Models\MailKonto;
use App\Models\MailOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Ausgangskorb gegen einen Provider, der Verbindungen zählt und drosselt
 * (Waldorf-Newsletter „Inforum" 23.09.2026: 421 too many connections).
 */
class MailAusliefernTest extends TestCase
{
    use RefreshDatabase;

    /** Wie oft wurde ein SMTP-Transport (= eine Verbindung) gebaut? */
    private int $transporte = 0;

    /** Soll der Server mit 421 abweisen? */
    private bool $abweisen = false;

    /** Zahl der tatsächlich angenommenen Mails. */
    private int $angenommen = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.outbox.stundenlimit' => 0]);

        $test = $this;
        Mail::extend('smtp', function () use ($test) {
            $test->transporte++;

            return new class($test) extends AbstractTransport
            {
                public function __construct(private MailAusliefernTest $test)
                {
                    parent::__construct();
                }

                protected function doSend(SentMessage $message): void
                {
                    $this->test->zustellen();
                }

                public function __toString(): string
                {
                    return 'test://';
                }
            };
        });
    }

    public function zustellen(): void
    {
        if ($this->abweisen) {
            throw new TransportException('421 4.7.0 Error: too many connections', 421);
        }

        $this->angenommen++;
    }

    private function konto(): MailKonto
    {
        return MailKonto::create([
            'bezeichnung' => 'Newsletter',
            'absender_mail' => 'newsletter@example.org',
            'host' => 'smtp.example.org',
            'port' => 587,
            'verschluesselung' => 'tls',
            'aktiv' => true,
        ]);
    }

    private function einreihen(MailKonto $konto, int $anzahl): void
    {
        for ($i = 1; $i <= $anzahl; $i++) {
            MailOutbox::create([
                'status' => MailOutbox::WARTEND,
                'mailer' => $konto->mailerName(),
                'betreff' => 'Inforum',
                'an' => ["eltern{$i}@example.org"],
                'nachricht' => MailOutbox::verpacken(
                    (new Email)->from('newsletter@example.org')->to("eltern{$i}@example.org")->subject('Inforum')->text('Hallo')
                ),
            ]);
        }
    }

    public function test_alle_mails_eines_kontos_gehen_ueber_eine_verbindung(): void
    {
        $this->einreihen($this->konto(), 10);

        $this->artisan('mail:ausliefern')->assertSuccessful();

        $this->assertSame(10, $this->angenommen);
        $this->assertSame(1, $this->transporte);
        $this->assertSame(10, MailOutbox::where('status', MailOutbox::VERSENDET)->count());
    }

    public function test_421_stellt_den_rest_des_laufs_zurueck_und_wartet(): void
    {
        $this->einreihen($this->konto(), 5);
        $this->abweisen = true;

        $this->artisan('mail:ausliefern')->assertSuccessful();

        // Nur die erste Mail hat einen Versuch verbraucht, die übrigen blieben unberührt.
        $this->assertSame(1, MailOutbox::where('versuche', 1)->count());
        $this->assertSame(4, MailOutbox::where('versuche', 0)->count());
        $this->assertSame(5, MailOutbox::where('status', MailOutbox::WARTEND)->count());

        $erste = MailOutbox::where('versuche', 1)->first();
        $this->assertTrue($erste->naechster_versuch_am->between(now()->addMinutes(4), now()->addMinutes(6)));

        // Nächste Minute: die gescheiterte wartet noch, die übrigen gehen raus.
        $this->abweisen = false;
        $this->travel(1)->minutes();
        $this->artisan('mail:ausliefern')->assertSuccessful();

        $this->assertSame(4, $this->angenommen);
        $this->assertSame(MailOutbox::WARTEND, $erste->fresh()->status);

        // Nach Ablauf der Wartezeit folgt auch sie.
        $this->travel(5)->minutes();
        $this->artisan('mail:ausliefern')->assertSuccessful();

        $this->assertSame(MailOutbox::VERSENDET, $erste->fresh()->status);
    }

    public function test_endgueltig_gescheitert_erst_nach_allen_wartezeiten(): void
    {
        $this->einreihen($this->konto(), 1);
        $this->abweisen = true;
        $mail = MailOutbox::first();

        foreach (MailOutbox::WARTEZEITEN as $minuten) {
            $this->artisan('mail:ausliefern')->assertSuccessful();
            $this->assertSame(MailOutbox::WARTEND, $mail->fresh()->status);
            $this->travel($minuten)->minutes();
        }

        $this->artisan('mail:ausliefern')->assertSuccessful();

        $this->assertSame(MailOutbox::FEHLGESCHLAGEN, $mail->fresh()->status);
        $this->assertSame(MailOutbox::MAX_VERSUCHE, $mail->fresh()->versuche);
    }
}
