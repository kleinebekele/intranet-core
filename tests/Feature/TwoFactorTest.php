<?php

namespace Tests\Feature;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Support\Totp;
use App\Support\TwoFactorTrust;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function userMit2fa(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_enabled' => true])->save();

        return $user;
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

    public function test_ohne_2fa_bleibt_der_login_wie_immer(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_user_mit_2fa_wird_zur_abfrage_geleitet(): void
    {
        $this->actingAs($this->userMit2fa())->get('/dashboard')->assertRedirect('/two-factor');
    }

    public function test_abfrage_seite_verschickt_mail_code(): void
    {
        $this->actingAs($this->userMit2fa())->get('/two-factor')->assertOk()->assertSee('per E-Mail');

        Mail::assertSent(TwoFactorCodeMail::class);
    }

    public function test_richtiger_mail_code_schaltet_frei(): void
    {
        $this->actingAs($this->userMit2fa())->get('/two-factor');

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
        $this->actingAs($this->userMit2fa())->get('/two-factor');

        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->get('/dashboard')->assertRedirect('/two-factor');
    }

    public function test_bekanntes_geraet_ueberspringt_die_abfrage(): void
    {
        $user = $this->userMit2fa();

        app(TwoFactorTrust::class)->remember($user);
        $cookie = Cookie::queued('intranet_2fa_trusted')->getValue();

        $this->actingAs($user)
            ->withCookie('intranet_2fa_trusted', $cookie)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_gemerktes_geraet_ueberlebt_cache_leeren(): void
    {
        // Regression: früher lag der Trust-Token im App-Cache, sodass jeder
        // Deploy (optimize:clear -> cache:clear) alle gemerkten Geräte vergaß.
        $user = $this->userMit2fa();

        app(TwoFactorTrust::class)->remember($user);
        $cookie = Cookie::queued('intranet_2fa_trusted')->getValue();

        Cache::flush();

        $this->actingAs($user)
            ->withCookie('intranet_2fa_trusted', $cookie)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_geraet_merken_laesst_sich_per_config_abschalten(): void
    {
        config(['intranet.two_factor_remember_days' => 0]);

        $user = $this->userMit2fa();
        app(TwoFactorTrust::class)->remember($user);

        $this->assertNull(Cookie::queued('intranet_2fa_trusted'));
        $this->actingAs($user)->get('/two-factor')->assertOk()->assertDontSee('Tage merken');
    }

    public function test_totp_einrichten_und_damit_anmelden(): void
    {
        $user = $this->userMit2fa();
        $this->actingAs($user)->session(['two_factor_passed' => true]);

        $this->post('/profile/two-factor');
        $secret = session('totp_pending_secret');
        $this->assertNotNull($secret);

        $this->post('/profile/two-factor/confirm', ['code' => $this->totpCode($secret)]);
        $this->assertTrue($user->fresh()->hasTotp());

        // Neue Sitzung: Abfrage verlangt jetzt TOTP statt Mail-Code.
        $this->post('/logout');
        $this->actingAs($user->fresh());

        $this->get('/two-factor')->assertOk()->assertSee('Authenticator-App');
        Mail::assertNothingOutgoing();

        $this->post('/two-factor', ['code' => $this->totpCode($secret)])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_2fa_aktivieren_und_mit_passwort_wieder_deaktivieren(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/profile/two-factor/enable');
        $this->assertTrue($user->fresh()->two_factor_enabled);

        $this->session(['two_factor_passed' => true]);
        $this->delete('/profile/two-factor', ['password' => 'password']);
        $this->assertFalse($user->fresh()->two_factor_enabled);

        $this->get('/dashboard')->assertOk();
    }

    public function test_force_2fa_erzwingt_die_abfrage_fuer_alle(): void
    {
        config(['intranet.two_factor_forced' => true]);

        $user = User::factory()->create(); // hat 2FA selbst NICHT aktiviert
        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect('/two-factor');

        // Deaktivieren ist bei Zwang wirkungslos.
        $this->session(['two_factor_passed' => true]);
        $this->delete('/profile/two-factor', ['password' => 'password']);
        $this->get('/profile')->assertSee('verpflichtend');
    }

    public function test_admin_kann_totp_zuruecksetzen(): void
    {
        $admin = User::factory()->create(); // erster User wird automatisch Admin
        $user = $this->userMit2fa();
        $user->forceFill(['totp_secret' => Totp::generateSecret(), 'totp_confirmed_at' => now()])->save();

        $this->actingAs($admin)->post("/admin/users/{$user->id}/reset-totp");

        $user = $user->fresh();
        $this->assertFalse($user->hasTotp());
        $this->assertTrue($user->two_factor_enabled); // 2FA bleibt an, nur wieder per Mail
    }

    public function test_profil_seite_rendert_alle_zustaende(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Zustand 1: aus → Aktivieren-Button
        $this->get('/profile')->assertOk()->assertSee('2FA aktivieren');

        // Zustand 2: aktiv per Mail → TOTP anbieten
        $this->post('/profile/two-factor/enable');
        $this->session(['two_factor_passed' => true]);
        $this->get('/profile')->assertOk()->assertSee('Code per E-Mail')->assertSee('einrichten');

        // Zustand 3: TOTP-Einrichtung läuft → Secret sichtbar
        $this->post('/profile/two-factor');
        $this->get('/profile')->assertOk()->assertSee(session('totp_pending_secret'));

        // Zustand 4: TOTP aktiv → Entfernen-Formular
        $this->post('/profile/two-factor/confirm', ['code' => $this->totpCode(session('totp_pending_secret'))]);
        $this->get('/profile')->assertOk()->assertSee('Authenticator-App');
    }
}
