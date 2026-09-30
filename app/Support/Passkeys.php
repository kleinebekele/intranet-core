<?php

namespace App\Support;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Passkeys (WebAuthn) – Anmeldung per Face ID, Touch ID, Windows Hello oder
 * Sicherheitsschlüssel.
 *
 * Ablauf in beiden Richtungen gleich: Der Server gibt eine Zufalls-Challenge
 * aus (in der Sitzung gemerkt), der Browser lässt sie vom Gerät unterschreiben,
 * der Server prüft die Unterschrift. Das Gerät verlangt dabei Fingerabdruck,
 * Gesicht oder PIN – deshalb zählt ein Passkey als beide Faktoren.
 *
 * Bewusst ohne Fremdpaket: Wir verlangen keine Hersteller-Bescheinigung
 * (attestation "none"). Dann liefert der Browser den öffentlichen Schlüssel
 * fertig als SPKI (getPublicKey()), und für die Prüfung reicht OpenSSL – kein
 * CBOR, keine Zertifikatsketten. Eine neue Composer-Abhängigkeit im Core würde
 * außerdem auf den Instanzen den git pull blockieren.
 *
 * Die Relying-Party-ID ist der Host aus APP_URL (nicht der Host der Anfrage:
 * hinter einem Proxy weicht der ab). Passkeys hängen fest an dieser Domain;
 * wechselt sie, müssen alle neu angelegt werden.
 */
class Passkeys
{
    private const CHALLENGE_ANLEGEN = 'passkey.challenge.anlegen';

    private const CHALLENGE_ANMELDEN = 'passkey.challenge.anmelden';

    /** Millisekunden, die das Gerät für die Bestätigung hat. */
    private const TIMEOUT = 120_000;

    /** COSE-Algorithmen, die wir annehmen: ES256 und RS256 (beide SHA-256). */
    private const ALGORITHMEN = [-7, -257];

    private const FLAG_ANWESEND = 0x01;

    private const FLAG_VERIFIZIERT = 0x04;

    private const FLAG_SCHLUESSEL_DABEI = 0x40;

    public function rpId(): string
    {
        return (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /** Optionen für navigator.credentials.create() – einen neuen Passkey anlegen. */
    public function anlegenOptionen(Request $request, User $user): array
    {
        $challenge = random_bytes(32);
        $request->session()->put(self::CHALLENGE_ANLEGEN, $this->base64url($challenge));

        return [
            'challenge' => $this->base64url($challenge),
            'rp' => ['id' => $this->rpId(), 'name' => (string) config('app.name', 'Intranet')],
            'user' => [
                'id' => $this->base64url((string) $user->getKey()),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => array_map(
                fn (int $alg) => ['type' => 'public-key', 'alg' => $alg],
                self::ALGORITHMEN,
            ),
            'authenticatorSelection' => [
                // Auffindbar: Die Anmeldung klappt ohne E-Mail-Eingabe.
                'residentKey' => 'required',
                'requireResidentKey' => true,
                'userVerification' => 'required',
            ],
            'attestation' => 'none',
            'timeout' => self::TIMEOUT,
            // Dasselbe Gerät nicht zweimal anlegen.
            'excludeCredentials' => $user->passkeys()->pluck('credential_id')
                ->map(fn (string $id) => ['type' => 'public-key', 'id' => $id])
                ->all(),
        ];
    }

    /**
     * Antwort des Browsers prüfen und den Passkey speichern.
     *
     * @throws PasskeyFehler
     */
    public function anlegen(Request $request, User $user, array $antwort, string $name): Passkey
    {
        $challenge = $request->session()->pull(self::CHALLENGE_ANLEGEN);

        if (! is_string($challenge)) {
            throw new PasskeyFehler('Keine offene Anfrage – bitte erneut versuchen.');
        }

        $this->clientDataPruefen($antwort, 'webauthn.create', $challenge);

        $authData = $this->authDataPruefen($antwort);

        if (! ($authData['flags'] & self::FLAG_SCHLUESSEL_DABEI) || strlen($authData['roh']) < 55) {
            throw new PasskeyFehler('Das Gerät hat keinen Schlüssel mitgeschickt.');
        }

        // Ab Byte 37: AAGUID (16), Länge der Credential-ID (2), Credential-ID.
        $aaguid = substr($authData['roh'], 37, 16);
        $laenge = unpack('n', substr($authData['roh'], 53, 2))[1];
        $credentialId = substr($authData['roh'], 55, $laenge);

        if (strlen($credentialId) !== $laenge || $credentialId !== $this->binaer($antwort['id'] ?? null)) {
            throw new PasskeyFehler('Die Kennung des Passkeys ist widersprüchlich.');
        }

        if (! in_array((int) ($antwort['publicKeyAlgorithm'] ?? 0), self::ALGORITHMEN, true)) {
            throw new PasskeyFehler('Das Gerät nutzt ein Verfahren, das hier nicht unterstützt wird.');
        }

        $pem = $this->pem($this->binaer($antwort['publicKey'] ?? null));

        if (openssl_pkey_get_public($pem) === false) {
            throw new PasskeyFehler('Der öffentliche Schlüssel ist unlesbar.');
        }

        $credentialIdText = $this->base64url($credentialId);

        if (Passkey::query()->where('credential_id', $credentialIdText)->exists()) {
            throw new PasskeyFehler('Dieser Passkey ist bereits hinterlegt.');
        }

        $passkey = new Passkey([
            'name' => $name,
            'credential_id' => $credentialIdText,
            'public_key' => $pem,
            'sign_count' => $authData['zaehler'],
            'aaguid' => $this->aaguid($aaguid),
        ]);
        $passkey->user()->associate($user);
        $passkey->save();

        return $passkey;
    }

    /** Optionen für navigator.credentials.get() – ohne Benutzerangabe, das Gerät bietet an. */
    public function anmeldenOptionen(Request $request): array
    {
        $challenge = random_bytes(32);
        $request->session()->put(self::CHALLENGE_ANMELDEN, $this->base64url($challenge));

        return [
            'challenge' => $this->base64url($challenge),
            'rpId' => $this->rpId(),
            'userVerification' => 'required',
            'timeout' => self::TIMEOUT,
        ];
    }

    /**
     * Unterschrift prüfen und den passenden Passkey liefern.
     *
     * @throws PasskeyFehler
     */
    public function anmelden(Request $request, array $antwort): Passkey
    {
        $challenge = $request->session()->pull(self::CHALLENGE_ANMELDEN);

        if (! is_string($challenge)) {
            throw new PasskeyFehler('Keine offene Anfrage – bitte erneut versuchen.');
        }

        $id = $this->base64url($this->binaer($antwort['id'] ?? null));
        $passkey = Passkey::query()->with('user')->where('credential_id', $id)->first();

        if ($passkey === null) {
            throw new PasskeyFehler('Dieser Passkey ist hier nicht (mehr) hinterlegt.');
        }

        // Liefert das Gerät den Benutzer mit, muss er zum Passkey passen.
        $userHandle = $antwort['userHandle'] ?? null;
        if (is_string($userHandle) && $userHandle !== ''
            && $this->binaer($userHandle) !== (string) $passkey->user_id) {
            throw new PasskeyFehler('Passkey und Benutzer passen nicht zusammen.');
        }

        $clientDataJson = $this->clientDataPruefen($antwort, 'webauthn.get', $challenge);
        $authData = $this->authDataPruefen($antwort);

        $signiert = $authData['roh'].hash('sha256', $clientDataJson, true);
        $signatur = $this->binaer($antwort['signature'] ?? null);

        if (openssl_verify($signiert, $signatur, $passkey->public_key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new PasskeyFehler('Die Unterschrift des Geräts ist ungültig.');
        }

        // Zähler: Synchronisierte Passkeys (iCloud, Google, Windows) melden
        // immer 0. Zählt ein Gerät, darf der Wert nie zurückgehen – sonst ist
        // der Schlüssel womöglich kopiert worden.
        $zaehler = $authData['zaehler'];
        if (($zaehler !== 0 || $passkey->sign_count !== 0) && $zaehler <= $passkey->sign_count) {
            throw new PasskeyFehler('Der Zähler des Passkeys ist zurückgesprungen.');
        }

        $passkey->forceFill([
            'sign_count' => $zaehler,
            'zuletzt_benutzt_am' => now(),
        ])->save();

        return $passkey;
    }

    /**
     * clientDataJSON prüfen: richtiger Vorgang, unsere Challenge, unsere Adresse.
     *
     * @return string die Rohdaten (für die Signaturprüfung)
     */
    private function clientDataPruefen(array $antwort, string $typ, string $challenge): string
    {
        $json = $this->binaer($antwort['clientDataJSON'] ?? null);
        $daten = json_decode($json, true);

        if (! is_array($daten) || ($daten['type'] ?? null) !== $typ) {
            throw new PasskeyFehler('Unerwartete Antwort des Browsers.');
        }

        if (! hash_equals($challenge, (string) ($daten['challenge'] ?? ''))) {
            throw new PasskeyFehler('Die Anfrage ist abgelaufen – bitte erneut versuchen.');
        }

        $herkunft = (string) ($daten['origin'] ?? '');
        $host = parse_url($herkunft, PHP_URL_HOST);
        $schema = parse_url($herkunft, PHP_URL_SCHEME);

        if ($host !== $this->rpId() || ($schema !== 'https' && $host !== 'localhost')) {
            throw new PasskeyFehler('Die Anfrage kam von einer fremden Adresse ('.$herkunft.').');
        }

        return $json;
    }

    /**
     * authenticatorData prüfen: für unsere Domain, Benutzer anwesend und
     * verifiziert (Gesicht/Finger/PIN).
     *
     * @return array{roh: string, flags: int, zaehler: int}
     */
    private function authDataPruefen(array $antwort): array
    {
        $roh = $this->binaer($antwort['authenticatorData'] ?? null);

        if (strlen($roh) < 37) {
            throw new PasskeyFehler('Unvollständige Antwort des Geräts.');
        }

        if (! hash_equals(hash('sha256', $this->rpId(), true), substr($roh, 0, 32))) {
            throw new PasskeyFehler('Der Passkey gehört zu einer anderen Domain.');
        }

        $flags = ord($roh[32]);

        if (! ($flags & self::FLAG_ANWESEND) || ! ($flags & self::FLAG_VERIFIZIERT)) {
            throw new PasskeyFehler('Das Gerät hat die Person nicht bestätigt (Gesicht, Finger oder PIN).');
        }

        return [
            'roh' => $roh,
            'flags' => $flags,
            'zaehler' => unpack('N', substr($roh, 33, 4))[1],
        ];
    }

    private function binaer(mixed $base64url): string
    {
        if (! is_string($base64url) || $base64url === '') {
            throw new PasskeyFehler('Unvollständige Antwort des Browsers.');
        }

        $binaer = base64_decode(strtr($base64url, '-_', '+/'), true);

        if ($binaer === false) {
            throw new PasskeyFehler('Unlesbare Antwort des Browsers.');
        }

        return $binaer;
    }

    private function base64url(string $binaer): string
    {
        return rtrim(strtr(base64_encode($binaer), '+/', '-_'), '=');
    }

    private function pem(string $spki): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function aaguid(string $binaer): ?string
    {
        $hex = bin2hex($binaer);

        if (strlen($hex) !== 32 || trim($hex, '0') === '') {
            return null;
        }

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
