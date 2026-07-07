<?php

namespace Tests\Feature;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['intranet.two_factor' => true]);
        Mail::fake();
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    /** Aktuellen TOTP-Code für ein Secret berechnen (über die interne hotp-Methode). */
    private function totpCode(string $secret): string
    {
        $ref = new ReflectionClass(Totp::class);

        return $ref->getMethod('hotp')->invoke(
            null,
            $ref->getMethod('base32Decode')->invoke(null, $secret),
            (int) floor(time() / 30),
        );
    }

    public function test_login_leitet_zur_zwei_faktor_abfrage(): void
    {
        $user = $this->user();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect('/two-factor');
    }

    public function test_geschuetzte_seiten_sind_ohne_zweiten_faktor_gesperrt(): void
    {
        $response = $this->actingAs($this->user())->get('/dashboard');

        $response->assertRedirect('/two-factor');
    }

    public function test_abfrage_seite_verschickt_mail_code(): void
    {
        $this->actingAs($this->user())->get('/two-factor')->assertOk()->assertSee('per E-Mail');

        Mail::assertSent(TwoFactorCodeMail::class);
    }

    public function test_richtiger_mail_code_schaltet_frei(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/two-factor');

        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->post('/two-factor', ['code' => $code])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_falscher_mail_code_wird_abgelehnt(): void
    {
        $this->actingAs($this->user())->get('/two-factor');

        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->get('/dashboard')->assertRedirect('/two-factor');
    }

    public function test_totp_einrichten_und_damit_anmelden(): void
    {
        $user = $this->user();
        $this->actingAs($user)->session(['two_factor_passed' => true]);

        // Einrichten: Secret erzeugen, mit gültigem Code bestätigen.
        $this->post('/profile/two-factor');
        $secret = session('totp_pending_secret');
        $this->assertNotNull($secret);

        $this->post('/profile/two-factor/confirm', ['code' => $this->totpCode($secret)]);
        $this->assertTrue($user->fresh()->hasTotp());

        // Neuer Login: Abfrage verlangt jetzt TOTP statt Mail-Code.
        $this->post('/logout');
        $this->actingAs($user->fresh());

        $this->get('/two-factor')->assertOk()->assertSee('Authenticator-App');
        Mail::assertNothingOutgoing();

        $this->post('/two-factor', ['code' => $this->totpCode($secret)])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_admin_kann_totp_zuruecksetzen(): void
    {
        $admin = $this->user(); // erster User wird automatisch Admin
        $user = User::factory()->create();
        $user->forceFill(['totp_secret' => Totp::generateSecret(), 'totp_confirmed_at' => now()])->save();

        $this->actingAs($admin)->session(['two_factor_passed' => true]);

        $this->post("/admin/users/{$user->id}/reset-totp");

        $this->assertFalse($user->fresh()->hasTotp());
    }

    public function test_profil_seite_rendert_alle_totp_zustaende(): void
    {
        $user = $this->user();
        $this->actingAs($user)->session(['two_factor_passed' => true]);

        // Zustand 1: kein TOTP → Einrichten-Button
        $this->get('/profile')->assertOk()->assertSee('Zwei-Faktor-Authentifizierung')->assertSee('einrichten');

        // Zustand 2: Einrichtung läuft → Secret + QR + Bestätigen
        $this->post('/profile/two-factor');
        $this->get('/profile')->assertOk()->assertSee(session('totp_pending_secret'))->assertSee('aktivieren');

        // Zustand 3: aktiv → Entfernen-Formular
        $this->post('/profile/two-factor/confirm', ['code' => $this->totpCode(session('totp_pending_secret'))]);
        $this->get('/profile')->assertOk()->assertSee('aktiv seit');
    }

    public function test_ohne_two_factor_konfiguration_bleibt_alles_beim_alten(): void
    {
        config(['intranet.two_factor' => false]);

        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }
}
