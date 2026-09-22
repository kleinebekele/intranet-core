<?php

namespace App\Ekkon\Services;

use App\Ekkon\Models\TeamsChannel;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Postet eine Nachricht direkt über Microsoft Graph in einen Teams-Chat oder
 * -Kanal – im Namen des verbundenen Kontos (GraphKontoVerbindung).
 *
 * Gegenüber dem Workflow-Weg (TeamsWebhookClient) kann dieser Weg eine Datei
 * so anhängen, wie Teams es beim manuellen Teilen tut: als Dateikarte, die
 * sich direkt in Teams öffnen und bearbeiten lässt. Dafür wird die Datei
 * ins OneDrive des Bots gelegt (Ordner Intranet-Anhaenge, Organisationslink)
 * und als „reference"-Anhang an die Nachricht gehängt.
 *
 * Ziel-ID (`chat_id`):
 *  - Chat / Besprechungschat: `19:…@thread.v2`  → POST /chats/{id}/messages
 *  - Team-Kanal: `<Team-GUID>/19:…@thread.tacv2` → POST /teams/{team}/channels/{kanal}/messages
 *
 * ⚠️ Wie der Webhook-Client WIRFT DIESE KLASSE NIE – Fehler kommen als Text zurück.
 */
class TeamsGraphClient
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    private const TIMEOUT = 30;

    /** Ordner im OneDrive des Bots für alle Anhänge. */
    private const ONEDRIVE_ORDNER = 'Intranet-Anhaenge';

    public function __construct(private readonly GraphKontoVerbindung $verbindung = new GraphKontoVerbindung) {}

    /** Drive-ID des OneDrive des verbundenen Kontos (einen Tag gemerkt). */
    private function eigenesDrive(string $token): string
    {
        return Cache::remember('ekkon-graph-eigenes-drive', now()->addDay(), function () use ($token): string {
            $res = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/me/drive', ['$select' => 'id']);
            if ($res->failed()) {
                throw new \RuntimeException($this->fehler('OneDrive des Kontos nicht lesbar (Berechtigung Files.ReadWrite?)', $res));
            }

            return (string) $res->json('id');
        });
    }

    /**
     * Datei im OneDrive des Bots für die ganze Organisation freigeben
     * („Jeder in der Organisation mit dem Link kann bearbeiten") – so wie Teams
     * es beim Teilen im Chat macht. Damit sieht jeder Chat-Teilnehmer die
     * Dateikarte und öffnet sie in Teams, ohne Einzelfreigaben.
     *
     * @param  array{id: string, driveId: string}  $anhang
     */
    private function organisationsLink(string $token, array $anhang): string
    {
        $res = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()
            ->post(self::GRAPH.'/drives/'.$anhang['driveId'].'/items/'.$anhang['id'].'/createLink', [
                'type' => 'edit',
                'scope' => 'organization',
            ]);

        if ($res->failed()) {
            throw new \RuntimeException($this->fehler('Organisationslink konnte nicht erstellt werden', $res));
        }

        $link = (string) $res->json('link.webUrl');
        if ($link === '') {
            throw new \RuntimeException('Organisationslink ohne Adresse.');
        }

        return $link;
    }

    /**
     * @param  array<string, mixed>  $daten  Faktenliste unter dem Text
     * @param  array{name: string, inhalt: string}|null  $datei  rohe Bytes
     * @param  string|null  $html  fertige HTML-Fassung des Textes (sonst wird $text umgesetzt)
     * @return string|null null = gepostet, sonst Fehlertext
     */
    public function sende(TeamsChannel $channel, string $titel, string $text, array $daten = [], ?array $datei = null, ?string $html = null): ?string
    {
        $ziel = trim((string) $channel->chat_id);
        if ($ziel === '') {
            return 'Channel "'.$channel->name.'" hat keine Chat-/Kanal-ID.';
        }

        try {
            $token = $this->verbindung->accessToken();

            // Ziel „Person": E-Mail-Adresse statt 19:…-ID → 1:1-Chat zwischen dem
            // verbundenen Konto und der Person (Graph liefert den bestehenden).
            $person = TeamsChannel::istPerson($ziel) ? $ziel : null;
            if ($person !== null) {
                $ziel = $this->einzelchat($token, $person);
            }

            $anhang = null;
            if ($datei !== null && ($datei['inhalt'] ?? '') !== '') {
                $anhang = $this->hochladen($token, (string) $datei['name'], (string) $datei['inhalt']);
                // Organisationslink – wie beim Teilen in Teams: Jeder im Tenant mit
                // dem Link (also alle Chat-Teilnehmer) sieht die Dateikarte.
                // Die Karte zeigt auf den Freigabelink (traegt das Freigabe-Token) – die
                // direkte Dateiadresse liefert anderen nur „Zugriff verweigert".
                $anhang['webUrl'] = $this->organisationsLink($token, $anhang);
            }

            $body = $this->nachricht($titel, $text, $daten, $html, $anhang);

            $res = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()->post($this->nachrichtenEndpunkt($ziel), $body);

            if ($res->failed()) {
                return $this->fehler('Nachricht abgelehnt', $res);
            }

            return null;
        } catch (Throwable $e) {
            Log::warning('Teams-Graph fehlgeschlagen', ['fehler' => $e->getMessage()]);

            return mb_substr($e->getMessage(), 0, 300);
        }
    }

    /**
     * Freie Nachricht (HTML) in einen Chat/Kanal – z. B. die Antwort des Bots
     * auf eine eingegangene Teams-Nachricht (TeamsNachrichtEmpfangen).
     *
     * @return string|null null = gepostet, sonst Fehlertext
     */
    public function nachrichtPosten(string $ziel, string $html): ?string
    {
        try {
            $token = $this->verbindung->accessToken();
            if (TeamsChannel::istPerson($ziel)) {
                $ziel = $this->einzelchat($token, $ziel);
            }
            $res = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()
                ->post($this->nachrichtenEndpunkt($ziel), ['body' => ['contentType' => 'html', 'content' => $html]]);

            return $res->failed() ? $this->fehler('Nachricht abgelehnt', $res) : null;
        } catch (Throwable $e) {
            return mb_substr($e->getMessage(), 0, 300);
        }
    }

    /**
     * Nachricht anlegen und ihre ID zurückgeben – für Platzhalter, die später
     * per nachrichtBearbeiten() ersetzt werden („Helper schreibt …").
     *
     * @return array{chat: string, id: string} aufgelöstes Ziel + Nachrichten-ID
     *
     * @throws \RuntimeException
     */
    public function nachrichtAnlegen(string $ziel, string $html): array
    {
        $token = $this->verbindung->accessToken();
        if (TeamsChannel::istPerson($ziel)) {
            $ziel = $this->einzelchat($token, $ziel);
        }
        $res = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()
            ->post($this->nachrichtenEndpunkt($ziel), ['body' => ['contentType' => 'html', 'content' => $html]]);
        if ($res->failed()) {
            throw new \RuntimeException($this->fehler('Nachricht abgelehnt', $res));
        }

        return ['chat' => $ziel, 'id' => (string) $res->json('id')];
    }

    /** Eigene Nachricht nachträglich ersetzen (Platzhalter → Antwort). */
    public function nachrichtBearbeiten(string $ziel, string $nachrichtId, string $html): ?string
    {
        try {
            $token = $this->verbindung->accessToken();
            $res = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()
                ->patch($this->nachrichtenEndpunkt($ziel).'/'.rawurlencode($nachrichtId), ['body' => ['contentType' => 'html', 'content' => $html]]);

            return $res->failed() ? $this->fehler('Bearbeiten abgelehnt', $res) : null;
        } catch (Throwable $e) {
            return mb_substr($e->getMessage(), 0, 300);
        }
    }

    /**
     * Reaktion (Emoji) auf eine fremde Nachricht setzen oder entfernen – rein
     * kosmetisch („gesehen"), Fehler werden nur geloggt.
     */
    public function reagieren(string $ziel, string $nachrichtId, string $emoji, bool $entfernen = false): void
    {
        try {
            $token = $this->verbindung->accessToken();
            $res = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()
                ->post($this->nachrichtenEndpunkt($ziel).'/'.rawurlencode($nachrichtId).'/'.($entfernen ? 'unsetReaction' : 'setReaction'), ['reactionType' => $emoji]);
            if ($res->failed()) {
                Log::info('Teams-Reaktion nicht gesetzt', ['fehler' => $this->fehler('Reaktion', $res)]);
            }
        } catch (Throwable $e) {
            Log::info('Teams-Reaktion nicht gesetzt', ['fehler' => $e->getMessage()]);
        }
    }

    // ── Datei ins OneDrive ─────────────────────────────────────────────

    /**
     * Datei ins OneDrive des Bots legen (Ordner Intranet-Anhaenge). Gleicher
     * Name = Microsoft hängt eine Nummer an (conflictBehavior=rename), nichts
     * wird überschrieben.
     *
     * @return array{id: string, driveId: string, webUrl: string, name: string, eTag: string}
     */
    private function hochladen(string $token, string $name, string $inhalt): array
    {
        $driveId = $this->eigenesDrive($token);
        $adresse = self::GRAPH.'/drives/'.$driveId.'/root:/'.rawurlencode(self::ONEDRIVE_ORDNER).'/'.rawurlencode($name).':/content';

        $res = Http::withToken($token)->timeout(60)
            ->withBody($inhalt, 'application/octet-stream')
            ->put($adresse.'?@microsoft.graph.conflictBehavior=rename');

        if ($res->failed()) {
            throw new \RuntimeException($this->fehler('Upload ins OneDrive abgelehnt', $res));
        }

        return [
            'id' => (string) $res->json('id'),
            'driveId' => $driveId,
            'webUrl' => (string) $res->json('webUrl'),
            'name' => (string) $res->json('name'),
            'eTag' => (string) $res->json('eTag'),
        ];
    }

    /**
     * 1:1-Chat zwischen dem verbundenen Konto und der Person. Graph legt ihn an
     * oder gibt den bestehenden zurück; die ID wird einen Tag gemerkt.
     */
    private function einzelchat(string $token, string $email): string
    {
        $cacheKey = 'ekkon-graph-einzelchat-'.md5(mb_strtolower($email));
        $gemerkt = Cache::get($cacheKey);
        if (is_string($gemerkt) && $gemerkt !== '') {
            return $gemerkt;
        }

        $konto = \App\Ekkon\Models\GraphKonto::aktuelles();
        $ich = (string) ($konto?->ms_id ?? '');

        $person = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get(self::GRAPH.'/users/'.rawurlencode($email), ['$select' => 'id']);
        if ($person->failed()) {
            throw new \RuntimeException($this->fehler('Person '.$email.' nicht im Tenant gefunden', $person));
        }

        $mitglied = fn (string $id) => [
            '@odata.type' => '#microsoft.graph.aadUserConversationMember',
            'roles' => ['owner'],
            'user@odata.bind' => self::GRAPH."/users('".$id."')",
        ];

        $chat = Http::withToken($token)->timeout(self::TIMEOUT)->asJson()->post(self::GRAPH.'/chats', [
            'chatType' => 'oneOnOne',
            'members' => [$mitglied($ich), $mitglied((string) $person->json('id'))],
        ]);
        if ($chat->failed()) {
            throw new \RuntimeException($this->fehler('Chat mit '.$email.' konnte nicht angelegt werden', $chat));
        }

        $id = (string) $chat->json('id');
        Cache::put($cacheKey, $id, now()->addDay());

        return $id;
    }

    // ── Nachricht ────────────────────────────────────────────────────────

    private function nachrichtenEndpunkt(string $ziel): string
    {
        if (str_contains($ziel, '/')) {
            [$team, $kanal] = explode('/', $ziel, 2);

            return self::GRAPH.'/teams/'.rawurlencode(trim($team)).'/channels/'.rawurlencode(trim($kanal)).'/messages';
        }

        return self::GRAPH.'/chats/'.rawurlencode($ziel).'/messages';
    }

    /**
     * @param  array<string, mixed>  $daten
     * @param  array{id: string, webUrl: string, name: string, eTag: string}|null  $anhang
     * @return array<string, mixed>
     */
    private function nachricht(string $titel, string $text, array $daten, ?string $html, ?array $anhang): array
    {
        $inhalt = '<p><b>'.e($titel).'</b></p>';

        if ($anhang !== null) {
            // Mit Datei bleibt die Nachricht kurz: Titel, Fakten, Dateikarte. Der
            // ganze Text steckt im Dokument – so war es gewünscht.
            $inhalt .= $this->fakten($daten);
            $id = $this->anhangId($anhang['eTag']);
            $inhalt .= '<attachment id="'.$id.'"></attachment>';

            return [
                'body' => ['contentType' => 'html', 'content' => $inhalt],
                'attachments' => [[
                    'id' => $id,
                    'contentType' => 'reference',
                    'contentUrl' => $anhang['webUrl'],
                    'name' => $anhang['name'],
                ]],
            ];
        }

        $inhalt .= filled($html) ? $this->htmlBereinigen((string) $html) : $this->textZuHtml($text);
        $inhalt .= $this->fakten($daten);

        return ['body' => ['contentType' => 'html', 'content' => $inhalt]];
    }

    /**
     * Die Anhang-ID muss die GUID des Drive-Items sein – sie steckt im eTag
     * ("{GUID},1"). Ohne sie zeigt Teams die Karte nicht an.
     */
    private function anhangId(string $eTag): string
    {
        return preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $eTag, $m)
            ? strtolower($m[1])
            : strtolower((string) \Illuminate\Support\Str::uuid());
    }

    /** Das kleine Markdown der Karten (fett, Listen, Links) als HTML. */
    private function textZuHtml(string $text): string
    {
        $zeilen = preg_split('/\r?\n/', trim($text)) ?: [];
        $html = '';
        foreach ($zeilen as $zeile) {
            $z = e(trim($zeile));
            $z = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $z) ?? $z;
            $z = preg_replace('/\[([^\]]+)\]\((https?:[^)\s]+)\)/', '<a href="$2">$1</a>', $z) ?? $z;
            $z = preg_replace('/^[-*•]\s+/', '• ', $z) ?? $z;
            $html .= $z === '' ? '<br>' : $z.'<br>';
        }

        return '<p>'.preg_replace('/(<br>){3,}/', '<br><br>', $html).'</p>';
    }

    /** Teams rendert ein eingeschränktes HTML: Skripte/Styles raus, Rest durchreichen. */
    private function htmlBereinigen(string $html): string
    {
        $html = preg_replace('~<(script|style)\b.*?</\1>~is', '', $html) ?? $html;
        if (preg_match('~<body[^>]*>(.*)</body>~is', $html, $m)) {
            $html = $m[1];
        }

        return trim($html);
    }

    /** @param  array<string, mixed>  $daten */
    private function fakten(array $daten): string
    {
        if ($daten === []) {
            return '';
        }
        $zeilen = [];
        foreach ($daten as $name => $wert) {
            $w = is_scalar($wert) ? (string) $wert : json_encode($wert, JSON_UNESCAPED_UNICODE);
            $w = mb_substr((string) $w, 0, 300);
            $w = preg_match('~^https?://~', $w) ? '<a href="'.e($w).'">'.e($w).'</a>' : e($w);
            $zeilen[] = '<b>'.e((string) $name).':</b> '.$w;
        }

        return '<p>'.implode('<br>', $zeilen).'</p>';
    }

    private function fehler(string $was, Response $res): string
    {
        $detail = $res->json('error.message') ?: mb_substr($res->body(), 0, 200);

        return $was.' (HTTP '.$res->status().'): '.$detail;
    }
}
