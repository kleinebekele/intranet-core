<?php

namespace Tests\Feature\Auth;

use App\Models\MicrosoftAnmeldung;
use App\Models\Role;
use App\Models\User;
use App\Support\Microsoft\MicrosoftSso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Anmeldung mit dem Microsoft-Konto.
 *
 * Microsoft wird komplett gefälscht (Http::fake): Der Token-Endpunkt liefert
 * ein id_token, dessen Nutzdaten-Teil wir selbst zusammensetzen – die Signatur
 * prüft der Ablauf bewusst nicht (siehe MicrosoftSso), maßgeblich ist die
 * Graph-Antwort auf /me.
 */
class MicrosoftSsoTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-1111-1111-1111-111111111111';

    private const CLIENT = '22222222-2222-2222-2222-222222222222';

    private const GRUPPE = '33333333-3333-3333-3333-333333333333';

    private const OID = '44444444-4444-4444-4444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.microsoft', [
            'tenant' => self::TENANT,
            'client_id' => self::CLIENT,
            'client_secret' => 'geheim',
            'gruppen' => self::GRUPPE,
            'neue_rollen' => 'staff',
        ]);
    }

    public function test_knopf_erscheint_nur_wenn_eingerichtet(): void
    {
        $this->get('/login')->assertOk()->assertSee('Mit Microsoft anmelden');

        config()->set('services.microsoft.client_secret', null);

        $this->get('/login')->assertOk()->assertDontSee('Mit Microsoft anmelden');
    }

    public function test_ohne_einrichtung_gibt_es_die_routen_nicht(): void
    {
        config()->set('services.microsoft.client_id', null);

        $this->get('/auth/microsoft')->assertNotFound();
    }

    public function test_start_leitet_zu_microsoft_und_merkt_sich_den_state(): void
    {
        $antwort = $this->get('/auth/microsoft');

        $ziel = $antwort->headers->get('Location');

        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/authorize?',
            $ziel
        );
        $this->assertStringContainsString('code_challenge_method=S256', $ziel);
        $this->assertStringContainsString('client_id='.self::CLIENT, $ziel);

        $this->assertNotEmpty(session('microsoft_sso.state'));
    }

    public function test_bestehender_benutzer_meldet_sich_an(): void
    {
        $benutzer = User::factory()->create(['email' => 'anna@firma.de']);

        $this->microsoftAntwortet('anna@firma.de');

        $antwort = $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $antwort->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($benutzer->fresh());

        // Beim ersten Mal wird die Microsoft-Kennung am Konto vermerkt.
        $this->assertSame(self::OID, $benutzer->fresh()->microsoft_id);
        $this->assertNotNull($benutzer->fresh()->microsoft_angemeldet_am);

        // Und die 2FA-Abfrage ist erledigt: Microsoft hat sie schon verlangt.
        $this->assertTrue(session('two_factor_passed'));
        $this->assertTrue(session(MicrosoftSso::ANGEMELDET_UEBER));

        $this->assertDatabaseHas('microsoft_anmeldungen', [
            'email' => 'anna@firma.de',
            'ergebnis' => 'angemeldet',
        ]);
    }

    public function test_grossschreibung_der_adresse_ist_egal(): void
    {
        $benutzer = User::factory()->create(['email' => 'Anna@Firma.de']);

        $this->microsoftAntwortet('ANNA@FIRMA.DE');

        $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $this->assertAuthenticatedAs($benutzer->fresh());
    }

    public function test_unbekannter_aus_der_gruppe_wird_angelegt(): void
    {
        Role::firstOrCreate(['role_id' => 'user'], ['name' => 'Benutzer']);

        $this->microsoftAntwortet('neu@firma.de', gruppen: [self::GRUPPE]);

        $antwort = $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $antwort->assertRedirect(route('dashboard', absolute: false));

        $benutzer = User::where('email', 'neu@firma.de')->firstOrFail();
        $this->assertAuthenticatedAs($benutzer);
        $this->assertSame(self::OID, $benutzer->microsoft_id);
        $this->assertNotNull($benutzer->email_verified_at);
        $this->assertTrue($benutzer->roles()->where('roles.role_id', 'staff')->exists());

        $this->assertDatabaseHas('microsoft_anmeldungen', ['ergebnis' => 'neu_angelegt']);
    }

    public function test_unbekannter_ohne_gruppe_wird_abgewiesen(): void
    {
        $this->microsoftAntwortet('fremd@firma.de', gruppen: ['99999999-9999-9999-9999-999999999999']);

        $antwort = $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $antwort->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'fremd@firma.de']);
        $this->assertDatabaseHas('microsoft_anmeldungen', ['ergebnis' => 'keine_gruppe']);
    }

    public function test_ohne_hinterlegte_gruppe_wird_niemand_angelegt(): void
    {
        config()->set('services.microsoft.gruppen', '');

        $this->microsoftAntwortet('fremd@firma.de');

        $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'fremd@firma.de']);
        $this->assertDatabaseHas('microsoft_anmeldungen', ['ergebnis' => 'kein_konto']);
    }

    public function test_fehlender_gruppen_anspruch_meldet_einen_einrichtungsfehler(): void
    {
        // Kein groups im Token UND die Nachfrage bei Graph scheitert (fehlende
        // Berechtigung) – das ist ein Einrichtungsfehler, kein "darf nicht".
        $this->microsoftAntwortet('neu@firma.de', gruppen: null, checkMemberGroupsStatus: 403);

        $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $this->assertGuest();

        $eintrag = MicrosoftAnmeldung::latest('id')->first();
        $this->assertSame('fehler', $eintrag->ergebnis);
        $this->assertStringContainsString('groups', $eintrag->meldung);
    }

    public function test_gruppe_wird_notfalls_bei_graph_nachgefragt(): void
    {
        Role::firstOrCreate(['role_id' => 'user'], ['name' => 'Benutzer']);

        $this->microsoftAntwortet('neu@firma.de', gruppen: null, checkMemberGroups: [self::GRUPPE]);

        $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $this->assertAuthenticatedAs(User::where('email', 'neu@firma.de')->firstOrFail());
    }

    public function test_gesperrtes_konto_kommt_auch_ueber_microsoft_nicht_herein(): void
    {
        $benutzer = User::factory()->create(['email' => 'anna@firma.de']);
        $benutzer->sperren('Ausgeschieden');

        $this->microsoftAntwortet('anna@firma.de');

        $antwort = $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $antwort->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('microsoft_anmeldungen', ['ergebnis' => 'gesperrt']);
    }

    public function test_falscher_state_wird_abgewiesen(): void
    {
        User::factory()->create(['email' => 'anna@firma.de']);

        $this->microsoftAntwortet('anna@firma.de');

        $antwort = $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=ein-anderer');

        $antwort->assertRedirect(route('login'));
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_falscher_nonce_wird_abgewiesen(): void
    {
        User::factory()->create(['email' => 'anna@firma.de']);

        $this->microsoftAntwortet('anna@firma.de', nonce: 'ein-fremder-nonce');

        $antwort = $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $antwort->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_token_fuer_eine_andere_anwendung_wird_abgewiesen(): void
    {
        User::factory()->create(['email' => 'anna@firma.de']);

        $this->microsoftAntwortet('anna@firma.de', aud: 'eine-andere-anwendung');

        $this->mitSitzung()->get('/auth/microsoft/callback?code=abc&state=der-state');

        $this->assertGuest();
    }

    public function test_protokoll_ist_nur_fuer_admins_sichtbar(): void
    {
        // Achtung: Der allererste Benutzer wird im Core automatisch Admin
        // (User::booted) – der Nicht-Admin darf also nicht der erste sein.
        $admin = User::factory()->create();
        $normal = User::factory()->create();
        $normal->forceFill(['is_admin' => false])->save();

        $this->actingAs($normal)
            ->get('/admin/microsoft-sso')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/microsoft-sso')
            ->assertOk()
            ->assertSee('Microsoft-SSO');
    }

    /** Sitzung so vorbereiten, als wäre der Benutzer gerade zu Microsoft geschickt worden. */
    private function mitSitzung(): self
    {
        return $this->withSession(['microsoft_sso' => [
            'state' => 'der-state',
            'nonce' => 'der-nonce',
            'pruefwert' => 'der-pruefwert',
        ]]);
    }

    /**
     * Alle Microsoft-Aufrufe fälschen.
     *
     * @param  array<int, string>|null  $gruppen  groups-Anspruch im Token (null = keiner)
     * @param  array<int, string>  $checkMemberGroups  Antwort der Graph-Nachfrage
     */
    private function microsoftAntwortet(
        string $email,
        ?array $gruppen = null,
        array $checkMemberGroups = [],
        int $checkMemberGroupsStatus = 200,
        string $nonce = 'der-nonce',
        ?string $aud = null,
    ): void {
        $angaben = [
            'aud' => $aud ?? self::CLIENT,
            'tid' => self::TENANT,
            'oid' => self::OID,
            'nonce' => $nonce,
        ];

        if ($gruppen !== null) {
            $angaben['groups'] = $gruppen;
        }

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'ein-access-token',
                'id_token' => $this->idToken($angaben),
                'token_type' => 'Bearer',
            ]),
            'graph.microsoft.com/v1.0/me/checkMemberGroups' => Http::response(
                ['value' => $checkMemberGroups],
                $checkMemberGroupsStatus
            ),
            'graph.microsoft.com/v1.0/me*' => Http::response([
                'id' => self::OID,
                'displayName' => 'Anna Muster',
                'mail' => $email,
                'userPrincipalName' => $email,
            ]),
        ]);
    }

    /** Ein id_token bauen: Kopf und Signatur sind Attrappe, die Nutzdaten zählen. */
    private function idToken(array $angaben): string
    {
        $teil = fn (array $daten) => rtrim(strtr(base64_encode(json_encode($daten)), '+/', '-_'), '=');

        return $teil(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$teil($angaben).'.attrappe';
    }
}
