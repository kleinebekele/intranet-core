<?php

namespace App\Ekkon\Services;

use App\Ekkon\Events\TeamsNachrichtEmpfangen;
use App\Ekkon\Models\GraphKonto;
use App\Ekkon\Models\TeamsChatStand;
use App\Ekkon\Models\TeamsNachricht;
use App\Ekkon\Support\HtmlText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Holt neue Teams-Nachrichten an das verbundene (Bot-)Konto ab.
 *
 * Eine Runde = ein Aufruf von runde(): erst die Chatliste mit der jeweils
 * letzten Nachricht (EIN Graph-Aufruf), dann nur für Chats, in denen seit dem
 * gemerkten Stand etwas Neues von jemand anderem kam, die Nachrichten selbst.
 * So kostet Stillstand eine Anfrage pro Runde, egal wie viele Chats es gibt.
 *
 * Eigene Nachrichten des Bots werden übersprungen (sonst antwortet er sich
 * selbst), Systemnachrichten (Mitglied hinzugefügt …) ebenfalls.
 *
 * Bewusst kein Ekkon-Task: Der Lauscher läuft als Dauerdienst
 * (`php artisan teams:lauschen`, systemd) mit wenigen Sekunden Abstand.
 */
class TeamsLauscher
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    private const TIMEOUT = 20;

    /** Beim allerersten Blick auf einen Chat: nur so weit zurück. */
    private const ERSTER_BLICK_MINUTEN = 10;

    public function __construct(private readonly GraphKontoVerbindung $verbindung = new GraphKontoVerbindung) {}

    /**
     * @return array{chats: int, neu: int, fehler: array<int, string>}
     */
    public function runde(): array
    {
        $konto = GraphKonto::aktuelles();
        if ($konto === null) {
            return ['chats' => 0, 'neu' => 0, 'fehler' => ['Kein Microsoft-Konto verbunden.']];
        }

        $token = $this->verbindung->accessToken();
        $res = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/me/chats', [
            '$expand' => 'lastMessagePreview',
            '$top' => 50,
            '$orderby' => 'lastMessagePreview/createdDateTime desc',
        ]);
        if ($res->failed()) {
            throw new \RuntimeException('Chatliste: '.($res->json('error.message') ?: 'HTTP '.$res->status()));
        }

        $neu = 0;
        $fehler = [];
        $chats = (array) $res->json('value', []);

        foreach ($chats as $chat) {
            $chatId = (string) ($chat['id'] ?? '');
            $vorschau = $chat['lastMessagePreview'] ?? null;
            if ($chatId === '' || ! is_array($vorschau)) {
                continue;
            }

            $letzteAm = $this->zeit($vorschau['createdDateTime'] ?? null);
            if ($letzteAm === null) {
                continue;
            }

            $stand = TeamsChatStand::find($chatId);
            $gesehenBis = $stand?->zuletzt_gesehen_am;

            // Nichts Neues seit dem letzten Blick → nichts zu tun. (Auch eine
            // eigene Antwort des Bots schiebt den Stand vor; nachrichtenHolen
            // lässt seine Nachrichten ohnehin aus.)
            if ($gesehenBis !== null && ! $letzteAm->gt($gesehenBis)) {
                continue;
            }

            try {
                $neu += $this->nachrichtenHolen($token, $konto, $chat, $gesehenBis ?? now()->subMinutes(self::ERSTER_BLICK_MINUTEN));
            } catch (Throwable $e) {
                $fehler[] = 'Chat '.$chatId.': '.mb_substr($e->getMessage(), 0, 200);
                Log::warning('Teams-Lauscher: Chat nicht lesbar', ['chat' => $chatId, 'fehler' => $e->getMessage()]);
                continue;
            }

            TeamsChatStand::updateOrCreate(['chat_id' => $chatId], [
                'titel' => $this->chatTitel($chat, $konto),
                'zuletzt_gesehen_am' => $letzteAm,
            ]);

            // Gelesen: Der Bot hat den Chat jetzt „gesehen" – so bleibt sein
            // Teams-Postfach leer, wenn jemand mit seinem Konto hineinschaut.
            (new TeamsGraphClient($this->verbindung))->alsGelesenMarkieren($chatId);
        }

        return ['chats' => count($chats), 'neu' => $neu, 'fehler' => $fehler];
    }

    /**
     * Nachrichten eines Chats seit $seit ablegen (neueste zuerst von Graph, wir
     * drehen um, damit die Ereignisse in Sendereihenfolge feuern).
     *
     * @param  array<string, mixed>  $chat
     */
    private function nachrichtenHolen(string $token, GraphKonto $konto, array $chat, Carbon $seit): int
    {
        $chatId = (string) $chat['id'];
        $res = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get(self::GRAPH.'/chats/'.rawurlencode($chatId).'/messages', ['$top' => 50]);
        if ($res->failed()) {
            throw new \RuntimeException($res->json('error.message') ?: 'HTTP '.$res->status());
        }

        $neu = 0;
        foreach (array_reverse((array) $res->json('value', [])) as $m) {
            $am = $this->zeit($m['createdDateTime'] ?? null);
            $vonId = (string) ($m['from']['user']['id'] ?? '');
            if ($am === null || ! $am->gt($seit)) {
                continue;
            }
            if (($m['messageType'] ?? '') !== 'message' || $vonId === '' || $vonId === $konto->ms_id) {
                continue;
            }
            if (! empty($m['deletedDateTime'])) {
                continue;
            }

            $html = (string) ($m['body']['content'] ?? '');
            $istHtml = ($m['body']['contentType'] ?? '') === 'html';

            $nachricht = TeamsNachricht::firstOrCreate(['nachricht_id' => (string) $m['id']], [
                'chat_id' => $chatId,
                'chat_titel' => $this->chatTitel($chat, $konto),
                'chat_typ' => (string) ($chat['chatType'] ?? ''),
                'von_id' => $vonId,
                'von_name' => (string) ($m['from']['user']['displayName'] ?? ''),
                'text' => $istHtml ? HtmlText::zuText($html) : $html,
                'html' => $istHtml ? $html : null,
                'anhaenge' => array_values(array_map(fn ($a) => [
                    'name' => (string) ($a['name'] ?? ''),
                    'url' => (string) ($a['contentUrl'] ?? ''),
                    'typ' => (string) ($a['contentType'] ?? ''),
                ], (array) ($m['attachments'] ?? []))),
                // @-Erwähnung des Bots (für „in Gruppen nur auf Ansprache antworten").
                'bot_erwaehnt' => collect((array) ($m['mentions'] ?? []))
                    ->contains(fn ($mn) => (string) ($mn['mentioned']['user']['id'] ?? '') === $konto->ms_id),
                'gesendet_am' => $am,
            ]);

            if (! $nachricht->wasRecentlyCreated) {
                continue;
            }
            $neu++;

            try {
                TeamsNachrichtEmpfangen::dispatch($nachricht);
            } catch (Throwable $e) {
                Log::warning('Teams-Lauscher: Verarbeitung fehlgeschlagen', ['nachricht' => $nachricht->id, 'fehler' => $e->getMessage()]);
                $nachricht->update(['verarbeitung' => 'Fehler: '.mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        return $neu;
    }

    /** @param  array<string, mixed>  $chat */
    private function chatTitel(array $chat, GraphKonto $konto): string
    {
        $titel = trim((string) ($chat['topic'] ?? ''));
        if ($titel !== '') {
            return mb_substr($titel, 0, 255);
        }
        $vorschau = $chat['lastMessagePreview']['from']['user'] ?? null;
        $name = (string) ($vorschau['displayName'] ?? '');
        if ($name !== '' && ($vorschau['id'] ?? '') !== $konto->ms_id) {
            return $name;
        }

        return match ($chat['chatType'] ?? '') { 'oneOnOne' => '1:1-Chat', 'group' => 'Gruppenchat', 'meeting' => 'Besprechung', default => 'Chat' };
    }

    private function zeit(mixed $wert): ?Carbon
    {
        try {
            return filled($wert) ? Carbon::parse((string) $wert) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
