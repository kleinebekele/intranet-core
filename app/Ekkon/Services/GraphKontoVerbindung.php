<?php

namespace App\Ekkon\Services;

use App\Ekkon\Models\GraphKonto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Verbindet ein Microsoft-Konto mit dem Intranet, damit Ekkon in dessen Namen
 * Teams-Nachrichten posten und Dateien nach SharePoint legen kann.
 *
 * Nutzt dieselbe Entra-App wie die Anmeldung (MS_TENANT_ID/MS_CLIENT_ID/
 * MS_CLIENT_SECRET), aber einen eigenen Ablauf mit eigener Rückkehr-Adresse
 * (`module.ekkon.notifications.graph.callback`, muss in der App-Registrierung
 * als Umleitungs-URI stehen) und weiteren delegierten Berechtigungen:
 *
 *  - offline_access       → Refresh-Token, damit der Scheduler ohne Browser arbeitet
 *  - Chat.ReadWrite       → Nachrichten in Chats (auch Besprechungschats)
 *  - ChannelMessage.Send  → Nachrichten in Team-Kanäle
 *  - Sites.ReadWrite.All  → Datei in den SharePoint-Ordner hochladen
 *  - Files.ReadWrite      → ohne Ablage-URL: Datei ins OneDrive des Kontos, Freigabe an die Chat-Mitglieder
 *  - Team.ReadBasic.All, Channel.ReadBasic.All → Übersicht „Zugriffe des Kontos" (GraphAuskunft)
 *
 * Warum delegiert und nicht als App-Berechtigung: Nachrichten in Chats darf
 * eine App nur im Namen eines Nutzers senden; App-only gibt es dafür nicht.
 *
 * Der Access-Token lebt eine Stunde und liegt solange im Cache; danach wird er
 * mit dem Refresh-Token erneuert (das Microsoft dabei weiterdreht).
 */
class GraphKontoVerbindung
{
    private const SITZUNGSSCHLUESSEL = 'ekkon_graph_verbindung';

    private const CACHE = 'ekkon-graph-access-token';

    public const SCOPES = ['openid', 'profile', 'email', 'offline_access', 'User.Read', 'Chat.ReadWrite', 'ChannelMessage.Send', 'Sites.ReadWrite.All', 'Files.ReadWrite', 'Team.ReadBasic.All', 'Channel.ReadBasic.All'];

    /** Ist die Entra-App überhaupt konfiguriert? */
    public function moeglich(): bool
    {
        return $this->wert('tenant') !== null && $this->wert('client_id') !== null && $this->wert('client_secret') !== null;
    }

    public function umleitungsAdresse(): string
    {
        return route('module.ekkon.notifications.graph.callback');
    }

    /** Schritt 1: Adresse, zu der der Browser geschickt wird. */
    public function startUrl(Request $request): string
    {
        $state = Str::random(40);
        $pruefwert = Str::random(96);
        $request->session()->put(self::SITZUNGSSCHLUESSEL, ['state' => $state, 'pruefwert' => $pruefwert]);

        return $this->endpunkt('authorize').'?'.http_build_query([
            'client_id' => $this->wert('client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->umleitungsAdresse(),
            'response_mode' => 'query',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $pruefwert, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Schritt 2: Code einlösen, Konto merken.
     *
     * @throws RuntimeException mit lesbarer Meldung
     */
    public function abschliessen(Request $request, ?int $userId): GraphKonto
    {
        $merker = (array) $request->session()->pull(self::SITZUNGSSCHLUESSEL, []);

        if ($fehler = $request->query('error')) {
            throw new RuntimeException('Microsoft hat die Verbindung abgelehnt: '.($request->query('error_description') ?: $fehler));
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        if ($code === '' || $state === '' || ! isset($merker['state']) || ! hash_equals($merker['state'], $state)) {
            throw new RuntimeException('Die Rückkehr von Microsoft passt nicht zu dieser Sitzung. Bitte noch einmal „verbinden" klicken.');
        }

        $tokens = $this->tokenAnfrage([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->umleitungsAdresse(),
            'code_verifier' => (string) ($merker['pruefwert'] ?? ''),
        ]);

        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException('Microsoft hat kein Refresh-Token geliefert – fehlt die Berechtigung offline_access in der App-Registrierung?');
        }

        $ich = Http::withToken((string) $tokens['access_token'])->timeout(15)
            ->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'id,displayName,mail,userPrincipalName']);
        if ($ich->failed()) {
            throw new RuntimeException('Profil konnte nicht abgerufen werden (HTTP '.$ich->status().').');
        }

        // Es gibt genau ein Konto: alte Zeilen weg, damit „aktuelles()" eindeutig ist.
        GraphKonto::query()->delete();
        Cache::forget(self::CACHE);

        $konto = GraphKonto::create([
            'user_id' => $userId,
            'ms_id' => (string) $ich->json('id'),
            'email' => strtolower((string) ($ich->json('mail') ?: $ich->json('userPrincipalName'))),
            'name' => (string) ($ich->json('displayName') ?: $ich->json('userPrincipalName')),
            'refresh_token' => (string) $tokens['refresh_token'],
            'scopes' => (string) ($tokens['scope'] ?? implode(' ', self::SCOPES)),
            'verbunden_am' => now(),
        ]);

        Cache::put(self::CACHE, (string) $tokens['access_token'], now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 120)));

        return $konto;
    }

    public function trennen(): void
    {
        GraphKonto::query()->delete();
        Cache::forget(self::CACHE);
    }

    /**
     * Gültiger Access-Token – aus dem Cache oder frisch erneuert.
     *
     * @throws RuntimeException wenn kein Konto verbunden ist oder das Refresh-Token nicht mehr gilt
     */
    public function accessToken(): string
    {
        $konto = GraphKonto::aktuelles();
        if ($konto === null) {
            throw new RuntimeException('Kein Microsoft-Konto verbunden (Ekkon → Benachrichtigungen → Teams-Channels).');
        }

        $token = Cache::get(self::CACHE);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        try {
            $tokens = $this->tokenAnfrage([
                'grant_type' => 'refresh_token',
                'refresh_token' => (string) $konto->refresh_token,
                'scope' => implode(' ', self::SCOPES),
            ]);
        } catch (RuntimeException $e) {
            $konto->update(['letzter_fehler' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }

        $konto->update([
            'refresh_token' => (string) ($tokens['refresh_token'] ?? $konto->refresh_token),
            'zuletzt_benutzt_am' => now(),
            'letzter_fehler' => null,
        ]);

        $token = (string) $tokens['access_token'];
        Cache::put(self::CACHE, $token, now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 120)));

        return $token;
    }

    /**
     * @param  array<string, string>  $felder
     * @return array<string, mixed>
     */
    private function tokenAnfrage(array $felder): array
    {
        $antwort = Http::asForm()->timeout(15)->post($this->endpunkt('token'), $felder + [
            'client_id' => $this->wert('client_id'),
            'client_secret' => $this->wert('client_secret'),
        ]);

        if ($antwort->failed()) {
            throw new RuntimeException('Microsoft-Token abgelehnt: '.($antwort->json('error_description') ?: 'HTTP '.$antwort->status()));
        }

        $tokens = (array) $antwort->json();
        if (empty($tokens['access_token'])) {
            throw new RuntimeException('Microsoft hat kein Zugriffs-Token geliefert.');
        }

        return $tokens;
    }

    private function endpunkt(string $name): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode((string) $this->wert('tenant')).'/oauth2/v2.0/'.$name;
    }

    private function wert(string $name): ?string
    {
        $wert = config('services.microsoft.'.$name);
        $wert = is_string($wert) ? trim($wert) : null;

        return $wert === '' ? null : $wert;
    }
}
