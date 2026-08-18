<?php

namespace App\Support\Microsoft;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Der Anmelde-Ablauf mit dem Microsoft-Konto (Entra ID / Microsoft 365).
 *
 * Bewusst ohne Fremdpaket: Es geht um genau einen Anbieter und den ganz
 * normalen OpenID-Connect-Ablauf (Authorization Code + PKCE), den Laravels
 * HTTP-Client in wenigen Zeilen erledigt. Das erspart eine Abhängigkeit, die
 * bei jedem Laravel-Sprung erst wieder nachziehen müsste.
 *
 * Ablauf:
 *  1. startUrl()  – schickt den Benutzer zu Microsoft; state, nonce und der
 *                   PKCE-Prüfwert bleiben so lange in seiner Sitzung.
 *  2. profil()    – nimmt den zurückgereichten Code, tauscht ihn im direkten
 *                   Server-zu-Server-Aufruf gegen Tokens und fragt damit
 *                   Microsoft Graph, wer das eigentlich war.
 *
 * Zur Prüfung der Tokens: Die Signatur des id_token wird NICHT geprüft, und
 * das ist Absicht. Der Token kommt nicht über den Browser, sondern über eine
 * eigene TLS-Verbindung direkt vom Token-Endpunkt, für die wir uns mit dem
 * Client-Secret ausgewiesen haben (OpenID Connect Core, 3.1.3.7 erlaubt genau
 * dafür den Verzicht). Die maßgebliche Identitätsquelle ist ohnehin nicht der
 * Token, sondern die Graph-Antwort auf /me – abgefragt mit dem gerade
 * erhaltenen Access-Token. Empfänger, Tenant und nonce werden trotzdem geprüft.
 */
class MicrosoftSso
{
    /** Wo state, nonce und PKCE-Prüfwert zwischen den beiden Schritten liegen. */
    private const SITZUNGSSCHLUESSEL = 'microsoft_sso';

    /** Merker in der Sitzung: Diese Anmeldung kam über Microsoft. */
    public const ANGEMELDET_UEBER = 'microsoft_sso_angemeldet';

    public function tenant(): ?string
    {
        return $this->wert('tenant');
    }

    public function clientId(): ?string
    {
        return $this->wert('client_id');
    }

    /** Ist SSO überhaupt eingerichtet? Ohne das erscheint kein Knopf. */
    public function aktiv(): bool
    {
        return $this->tenant() !== null
            && $this->clientId() !== null
            && $this->wert('client_secret') !== null;
    }

    /**
     * Die Gruppen, deren Mitglieder automatisch ein Konto bekommen.
     *
     * @return array<int, string>
     */
    public function gruppen(): array
    {
        return $this->liste($this->wert('gruppen'));
    }

    /**
     * Rollen für automatisch angelegte Konten.
     *
     * @return array<int, string>
     */
    public function neueRollen(): array
    {
        return $this->liste($this->wert('neue_rollen'));
    }

    /**
     * Dürfen unbekannte Anmelder überhaupt angelegt werden? Nur wenn eine
     * Gruppe hinterlegt ist – sonst stünde die Tür dem ganzen Tenant offen.
     */
    public function darfAnlegen(): bool
    {
        return $this->gruppen() !== [];
    }

    /**
     * Die Berechtigungen, die wir bei Microsoft anfragen.
     *
     * User.Read reicht für Name und Adresse. Die Gruppen kommen normalerweise
     * als Anspruch groups im id_token (App-Registrierung, Tokenkonfiguration).
     * Wer das nicht einrichten will, ergänzt MS_SCOPES um GroupMember.Read.All –
     * dann fragen wir die Gruppen bei Bedarf direkt bei Graph nach.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        $eigene = $this->liste((string) env('MS_SCOPES', ''), ' ');

        return $eigene !== [] ? $eigene : ['openid', 'profile', 'email', 'User.Read'];
    }

    /** Die Adresse, die im Entra-Portal als Umleitungs-URI eingetragen sein muss. */
    public function umleitungsAdresse(): string
    {
        return route('auth.microsoft.callback');
    }

    /**
     * Schritt 1: Wohin der Browser geschickt wird.
     * Legt state, nonce und den PKCE-Prüfwert in der Sitzung ab.
     */
    public function startUrl(Request $request): string
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $pruefwert = Str::random(96);

        $request->session()->put(self::SITZUNGSSCHLUESSEL, [
            'state' => $state,
            'nonce' => $nonce,
            'pruefwert' => $pruefwert,
        ]);

        $anfrage = http_build_query([
            'client_id' => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri' => $this->umleitungsAdresse(),
            'response_mode' => 'query',
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->pkceAbleitung($pruefwert),
            'code_challenge_method' => 'S256',
            // Damit man das Konto wechseln kann und nicht stillschweigend mit
            // dem am Rechner angemeldeten Windows-Konto hereinkommt.
            'prompt' => 'select_account',
        ]);

        return $this->endpunkt('authorize').'?'.$anfrage;
    }

    /**
     * Schritt 2: Den zurückgereichten Code einlösen und feststellen, wer da kommt.
     *
     * @throws MicrosoftSsoFehler
     */
    public function profil(Request $request): MicrosoftProfil
    {
        $merker = (array) $request->session()->pull(self::SITZUNGSSCHLUESSEL, []);

        if ($fehler = $request->query('error')) {
            throw new MicrosoftSsoFehler(
                'Microsoft hat die Anmeldung abgelehnt: '.($request->query('error_description') ?: $fehler)
            );
        }

        $code = $request->query('code');
        $state = $request->query('state');

        if (! is_string($code) || $code === '') {
            throw new MicrosoftSsoFehler('Microsoft hat keinen Anmelde-Code zurückgegeben.');
        }

        // Schutz vor untergeschobenen Anmeldungen: Der zurückkommende state
        // muss der sein, den wir eben erst in DIESE Sitzung gelegt haben.
        if (! is_string($state) || ! isset($merker['state']) || ! hash_equals($merker['state'], $state)) {
            throw new MicrosoftSsoFehler(
                'Die Anmeldung passt nicht zu dieser Sitzung. Bitte noch einmal von vorn beginnen.'
            );
        }

        $tokens = $this->tokenTausch($code, (string) ($merker['pruefwert'] ?? ''));

        $angaben = $this->tokenInhalt($tokens['id_token'] ?? null);

        $this->tokenPruefen($angaben, (string) ($merker['nonce'] ?? ''));

        $konto = $this->grafAbfragen((string) ($tokens['access_token'] ?? ''));

        $email = strtolower(trim((string) ($konto['mail'] ?? $konto['userPrincipalName'] ?? '')));
        $id = (string) ($konto['id'] ?? $angaben['oid'] ?? '');

        if ($id === '' || $email === '') {
            throw new MicrosoftSsoFehler('Microsoft hat kein vollständiges Benutzerprofil geliefert.');
        }

        [$gruppen, $bekannt] = $this->gruppenErmitteln($angaben, (string) ($tokens['access_token'] ?? ''));

        return new MicrosoftProfil(
            id: $id,
            email: $email,
            name: trim((string) ($konto['displayName'] ?? '')) ?: $email,
            gruppen: $gruppen,
            gruppenBekannt: $bekannt,
        );
    }

    /**
     * Code gegen Tokens tauschen (Server zu Server, mit Client-Secret und PKCE).
     *
     * @return array<string, mixed>
     */
    private function tokenTausch(string $code, string $pruefwert): array
    {
        $antwort = Http::asForm()
            ->timeout(15)
            ->post($this->endpunkt('token'), [
                'client_id' => $this->clientId(),
                'client_secret' => $this->wert('client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->umleitungsAdresse(),
                'code_verifier' => $pruefwert,
                'scope' => implode(' ', $this->scopes()),
            ]);

        if ($antwort->failed()) {
            throw new MicrosoftSsoFehler(
                'Microsoft hat den Anmelde-Code nicht angenommen: '
                .($antwort->json('error_description') ?: 'HTTP '.$antwort->status())
            );
        }

        return (array) $antwort->json();
    }

    /**
     * Den Nutzdaten-Teil eines Tokens auslesen (ohne Signaturprüfung, siehe
     * Klassenkommentar).
     *
     * @return array<string, mixed>
     */
    private function tokenInhalt(mixed $token): array
    {
        if (! is_string($token) || substr_count($token, '.') !== 2) {
            throw new MicrosoftSsoFehler('Microsoft hat keinen verwertbaren Anmelde-Nachweis geliefert.');
        }

        $teil = explode('.', $token)[1];
        $roh = base64_decode(strtr($teil, '-_', '+/'), true);
        $inhalt = is_string($roh) ? json_decode($roh, true) : null;

        if (! is_array($inhalt)) {
            throw new MicrosoftSsoFehler('Der Anmelde-Nachweis von Microsoft war nicht lesbar.');
        }

        return $inhalt;
    }

    /**
     * Gehört der Nachweis zu unserer Anwendung, unserem Tenant und genau
     * dieser Anmeldung?
     *
     * @param  array<string, mixed>  $angaben
     */
    private function tokenPruefen(array $angaben, string $nonce): void
    {
        if ($nonce === '' || ! is_string($angaben['nonce'] ?? null) || ! hash_equals($nonce, $angaben['nonce'])) {
            throw new MicrosoftSsoFehler(
                'Der Anmelde-Nachweis gehört nicht zu dieser Anmeldung. Bitte noch einmal versuchen.'
            );
        }

        if (($angaben['aud'] ?? null) !== $this->clientId()) {
            throw new MicrosoftSsoFehler('Der Anmelde-Nachweis wurde für eine andere Anwendung ausgestellt.');
        }

        // Nur vergleichen, wenn ein konkreter Tenant konfiguriert ist
        // (common/organizations lassen bewusst mehrere zu).
        $tenant = (string) $this->tenant();
        if (! in_array($tenant, ['common', 'organizations', 'consumers'], true)
            && isset($angaben['tid'])
            && strcasecmp((string) $angaben['tid'], $tenant) !== 0) {
            throw new MicrosoftSsoFehler('Das Konto gehört nicht zu diesem Microsoft-365-Tenant.');
        }
    }

    /**
     * Wer ist das? Antwort von Microsoft Graph – die maßgebliche Quelle.
     *
     * @return array<string, mixed>
     */
    private function grafAbfragen(string $accessToken): array
    {
        if ($accessToken === '') {
            throw new MicrosoftSsoFehler('Microsoft hat kein Zugriffs-Token geliefert.');
        }

        $antwort = Http::withToken($accessToken)
            ->timeout(15)
            ->get('https://graph.microsoft.com/v1.0/me', [
                '$select' => 'id,displayName,mail,userPrincipalName',
            ]);

        if ($antwort->failed()) {
            throw new MicrosoftSsoFehler(
                'Das Benutzerprofil konnte nicht bei Microsoft abgerufen werden (HTTP '.$antwort->status().').'
            );
        }

        return (array) $antwort->json();
    }

    /**
     * Die Gruppen des Anmelders.
     *
     * Erster Weg: der Anspruch groups im id_token (App-Registrierung,
     * Tokenkonfiguration). Zweiter Weg, falls der fehlt oder Microsoft wegen
     * sehr vieler Gruppen nur einen Verweis schickt: direkt bei Graph
     * nachfragen. Klappt beides nicht, sagen wir das ehrlich
     * (gruppenBekannt = false), statt jemanden stillschweigend abzuweisen.
     *
     * @param  array<string, mixed>  $angaben
     * @return array{0: array<int, string>, 1: bool}
     */
    private function gruppenErmitteln(array $angaben, string $accessToken): array
    {
        $erlaubte = $this->gruppen();

        if ($erlaubte === []) {
            return [[], true]; // Ohne Filter braucht niemand Gruppen.
        }

        $ausToken = $angaben['groups'] ?? null;
        $ueberlauf = isset($angaben['_claim_names']) || isset($angaben['_claim_sources']);

        if (is_array($ausToken) && ! $ueberlauf) {
            return [array_values(array_map('strval', $ausToken)), true];
        }

        // Rückfallweg: Microsoft selbst fragen, ob das Konto in einer der
        // konfigurierten Gruppen ist. Braucht die Berechtigung
        // GroupMember.Read.All (siehe scopes()).
        $antwort = Http::withToken($accessToken)
            ->timeout(15)
            ->post('https://graph.microsoft.com/v1.0/me/checkMemberGroups', [
                'groupIds' => array_values($erlaubte),
            ]);

        if ($antwort->failed()) {
            return [[], false];
        }

        return [array_values(array_map('strval', (array) $antwort->json('value', []))), true];
    }

    private function endpunkt(string $name): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode((string) $this->tenant()).'/oauth2/v2.0/'.$name;
    }

    /** Der aus dem PKCE-Prüfwert abgeleitete Wert, den Microsoft zu sehen bekommt. */
    private function pkceAbleitung(string $pruefwert): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $pruefwert, true)), '+/', '-_'), '=');
    }

    private function wert(string $name): ?string
    {
        $wert = config('services.microsoft.'.$name);
        $wert = is_string($wert) ? trim($wert) : null;

        return $wert === '' ? null : $wert;
    }

    /**
     * Eine Komma- (oder Leerzeichen-) getrennte Einstellung als Liste.
     *
     * @return array<int, string>
     */
    private function liste(?string $roh, string $trenner = ','): array
    {
        if ($roh === null || trim($roh) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode($trenner, $roh)),
            fn (string $teil) => $teil !== '',
        ));
    }
}
