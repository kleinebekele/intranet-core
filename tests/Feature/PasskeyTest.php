<?php

namespace Tests\Feature;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

/**
 * Passkeys: Das "Gerät" wird hier mit einem OpenSSL-Schlüsselpaar nachgestellt
 * – es baut authenticatorData und clientDataJSON so, wie ein Browser sie
 * liefert, und unterschreibt wie ein echter Authenticator (ES256).
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    private const HERKUNFT = 'https://intranet.test';

    private OpenSSLAsymmetricKey $schluessel;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['app.url' => self::HERKUNFT]);

        // Fester Testschlüssel (P-256): openssl_pkey_new() braucht unter
        // Windows eine openssl.cnf und scheitert sonst still.
        $this->schluessel = openssl_pkey_get_private(self::TESTSCHLUESSEL);
    }

    private const TESTSCHLUESSEL = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgrUwoeuGBqVZRUMLQ
        W88hCw/KX3WTfReBryZEn5OaP0uhRANCAAT29lsAGNccp6v8gvkTQSbHZL5FtxU+
        tCPwLhUJ0osqivfYsw//SNdbNAQu7nMtejSZTHWwwW4IkXP79Os8a1ur
        -----END PRIVATE KEY-----
        PEM;

    private static function b64(string $binaer): string
    {
        return rtrim(strtr(base64_encode($binaer), '+/', '-_'), '=');
    }

    private function pem(): string
    {
        return openssl_pkey_get_details($this->schluessel)['key'];
    }

    private function spki(): string
    {
        return base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $this->pem()));
    }

    private function authData(int $flags = 0x05, int $zaehler = 0, ?string $credentialId = null): string
    {
        $daten = hash('sha256', 'intranet.test', true).chr($flags).pack('N', $zaehler);

        if ($credentialId !== null) {
            $daten .= str_repeat("\x11", 16).pack('n', strlen($credentialId)).$credentialId;
        }

        return $daten;
    }

    private function clientData(string $typ, string $challenge, string $herkunft = self::HERKUNFT): string
    {
        return json_encode(['type' => $typ, 'challenge' => $challenge, 'origin' => $herkunft]);
    }

    private function passkeyFuer(User $user, string $credentialId = 'geraet-1', int $zaehler = 0): Passkey
    {
        $passkey = new Passkey([
            'name' => 'Testgerät',
            'credential_id' => self::b64($credentialId),
            'public_key' => $this->pem(),
            'sign_count' => $zaehler,
        ]);
        $passkey->user()->associate($user);
        $passkey->save();

        return $passkey;
    }

    /** Eine Anmelde-Antwort bauen, wie navigator.credentials.get() sie liefert. */
    private function anmeldeAntwort(string $challenge, array $abweichend = []): array
    {
        $o = $abweichend + [
            'credentialId' => 'geraet-1',
            'flags' => 0x05,
            'zaehler' => 0,
            'herkunft' => self::HERKUNFT,
            'userHandle' => null,
        ];

        $authData = $this->authData($o['flags'], $o['zaehler']);
        $clientData = $this->clientData('webauthn.get', $challenge, $o['herkunft']);
        openssl_sign($authData.hash('sha256', $clientData, true), $signatur, $this->schluessel, OPENSSL_ALGO_SHA256);

        return [
            'id' => self::b64($o['credentialId']),
            'clientDataJSON' => self::b64($clientData),
            'authenticatorData' => self::b64($authData),
            'signature' => self::b64($signatur),
            'userHandle' => $o['userHandle'],
        ];
    }

    private function challengeHolen(): string
    {
        return $this->postJson('/auth/passkey/optionen')
            ->assertOk()
            ->assertJsonPath('rpId', 'intranet.test')
            ->json('challenge');
    }

    public function test_anmeldung_mit_passkey_ueberspringt_die_zwei_faktor_abfrage(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_enabled' => true])->save();
        $this->passkeyFuer($user);

        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen(), ['userHandle' => self::b64((string) $user->id)]),
        ])->assertOk()->assertJsonPath('weiter', url('/dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
        $this->assertNotNull($user->passkeys()->first()->zuletzt_benutzt_am);
        $this->assertDatabaseHas('audit_log', ['aktion' => 'anmeldung', 'beschreibung' => 'Mit Passkey.']);
    }

    public function test_falsche_unterschrift_wird_abgewiesen(): void
    {
        $this->passkeyFuer(User::factory()->create());

        $antwort = $this->anmeldeAntwort($this->challengeHolen());
        $antwort['signature'] = self::b64(str_repeat("\0", 70));

        $this->postJson('/auth/passkey', ['antwort' => $antwort])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_challenge_gilt_nur_einmal(): void
    {
        $this->passkeyFuer(User::factory()->create());

        $antwort = $this->anmeldeAntwort($this->challengeHolen());

        $this->postJson('/auth/passkey', ['antwort' => $antwort])->assertOk();
        auth()->logout();

        $this->postJson('/auth/passkey', ['antwort' => $antwort])->assertStatus(422);
    }

    public function test_fremde_herkunft_wird_abgewiesen(): void
    {
        $this->passkeyFuer(User::factory()->create());

        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen(), ['herkunft' => 'https://boese.example']),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_ohne_gesicht_finger_oder_pin_wird_abgewiesen(): void
    {
        $this->passkeyFuer(User::factory()->create());

        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen(), ['flags' => 0x01]),
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_zurueckspringender_zaehler_wird_abgewiesen(): void
    {
        $this->passkeyFuer(User::factory()->create(), zaehler: 10);

        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen(), ['zaehler' => 10]),
        ])->assertStatus(422);

        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen(), ['zaehler' => 11]),
        ])->assertOk();
    }

    public function test_gesperrtes_konto_kommt_auch_mit_passkey_nicht_herein(): void
    {
        $user = User::factory()->create();
        $user->sperren('Test');
        $this->passkeyFuer($user);

        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen()),
        ])->assertStatus(422)->assertJsonPath('meldung', trans('auth.gesperrt'));

        $this->assertGuest();
    }

    public function test_passkey_anlegen_verlangt_das_passwort(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/profile/passkeys/optionen', ['password' => 'falsch'])
            ->assertStatus(422)->assertJsonPath('meldung', 'Das Passwort stimmt nicht.');
    }

    public function test_passkey_anlegen_und_danach_damit_anmelden(): void
    {
        $user = User::factory()->create();

        $optionen = $this->actingAs($user)
            ->postJson('/profile/passkeys/optionen', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('rp.id', 'intranet.test')
            ->assertJsonPath('authenticatorSelection.userVerification', 'required')
            ->json();

        $credentialId = random_bytes(16);

        $this->postJson('/profile/passkeys', [
            'name' => 'iPhone',
            'antwort' => [
                'id' => self::b64($credentialId),
                'clientDataJSON' => self::b64($this->clientData('webauthn.create', $optionen['challenge'])),
                'authenticatorData' => self::b64($this->authData(0x45, 0, $credentialId)),
                'publicKey' => self::b64($this->spki()),
                'publicKeyAlgorithm' => -7,
            ],
        ])->assertOk();

        $passkey = $user->passkeys()->sole();
        $this->assertSame('iPhone', $passkey->name);
        $this->assertSame(self::b64($credentialId), $passkey->credential_id);
        $this->assertDatabaseHas('audit_log', ['aktion' => 'passkey.angelegt']);

        // Derselbe Schlüssel taugt jetzt zur Anmeldung.
        auth()->logout();
        $this->postJson('/auth/passkey', [
            'antwort' => $this->anmeldeAntwort($this->challengeHolen(), ['credentialId' => $credentialId]),
        ])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_fremden_passkey_kann_niemand_entfernen(): void
    {
        $passkey = $this->passkeyFuer(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->delete('/profile/passkeys/'.$passkey->id)
            ->assertNotFound();

        $this->assertModelExists($passkey);
    }

    public function test_eigenen_passkey_entfernen(): void
    {
        $user = User::factory()->create();
        $passkey = $this->passkeyFuer($user);

        $this->actingAs($user)->delete('/profile/passkeys/'.$passkey->id)
            ->assertRedirect(route('profile.edit').'#passkeys');

        $this->assertModelMissing($passkey);
    }

    public function test_anmeldeseite_und_profil_zeigen_passkeys(): void
    {
        $this->get('/login')->assertOk()->assertSee('Mit Passkey anmelden')->assertSee('username webauthn', false);

        $this->actingAs(User::factory()->create())->get('/profile')->assertOk()->assertSee('Passkey auf diesem Gerät anlegen');
    }
}
