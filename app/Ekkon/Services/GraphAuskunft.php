<?php

namespace App\Ekkon\Services;

use Illuminate\Support\Facades\Http;

/**
 * Was sieht das verbundene Microsoft-Konto? Chats und Teams mit Kanälen –
 * jeweils mit der ID, die man in der Channel-Maske braucht (Auswahl-Dialog).
 *
 * Reine Leseabfragen, bewusst gedeckelt (Seitengrößen), damit der Dialog auch
 * in einem großen Tenant in wenigen Sekunden steht.
 */
class GraphAuskunft
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    private const TIMEOUT = 20;

    public function __construct(private readonly GraphKontoVerbindung $verbindung = new GraphKontoVerbindung) {}

    /**
     * @return array{chats: array<int, array<string, string>>, teams: array<int, array<string, mixed>>, fehler: array<int, string>}
     */
    public function zugriffe(): array
    {
        $token = $this->verbindung->accessToken();
        $fehler = [];

        return [
            'chats' => $this->chats($token, $fehler),
            'teams' => $this->teams($token, $fehler),
            'fehler' => $fehler,
        ];
    }

    /**
     * Chats des Kontos: Besprechungs- und Gruppenchats mit Titel, 1:1-Chats
     * mit dem Namen des Gegenübers. Neueste zuerst.
     *
     * @param  array<int, string>  $fehler
     * @return array<int, array<string, string>>
     */
    private function chats(string $token, array &$fehler): array
    {
        $liste = [];
        $url = self::GRAPH.'/me/chats?$expand=members&$top=50&$orderby=lastMessagePreview/createdDateTime desc';

        for ($seite = 0; $seite < 4 && $url !== null; $seite++) {
            $res = Http::withToken($token)->timeout(self::TIMEOUT)->get($url);
            if ($res->failed()) {
                $fehler[] = 'Chats: '.($res->json('error.message') ?: 'HTTP '.$res->status());
                break;
            }
            foreach ((array) $res->json('value', []) as $chat) {
                $mitglieder = array_values(array_filter(array_map(fn ($m) => (string) ($m['displayName'] ?? ''), (array) ($chat['members'] ?? []))));
                $typ = (string) ($chat['chatType'] ?? '');
                $titel = trim((string) ($chat['topic'] ?? ''));
                if ($titel === '') {
                    $titel = $typ === 'oneOnOne' ? implode(' ↔ ', $mitglieder) : implode(', ', array_slice($mitglieder, 0, 4)).(count($mitglieder) > 4 ? ' …' : '');
                }
                $liste[] = [
                    'id' => (string) ($chat['id'] ?? ''),
                    'titel' => $titel !== '' ? $titel : '(ohne Titel)',
                    'typ' => match ($typ) { 'meeting' => 'Besprechung', 'group' => 'Gruppe', 'oneOnOne' => '1:1', default => $typ },
                    'mitglieder' => (string) count($mitglieder),
                ];
            }
            $url = $res->json()['@odata.nextLink'] ?? null; // json('a.b') wuerde den Punkt als Pfad lesen
        }

        return $liste;
    }

    /**
     * Teams, in denen das Konto Mitglied ist, je mit Kanälen (ID fertig als
     * „Team-GUID/Kanal-ID", so wie die Maske sie erwartet).
     *
     * @param  array<int, string>  $fehler
     * @return array<int, array<string, mixed>>
     */
    private function teams(string $token, array &$fehler): array
    {
        $res = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/me/joinedTeams');
        if ($res->failed()) {
            $fehler[] = 'Teams: '.($res->json('error.message') ?: 'HTTP '.$res->status());

            return [];
        }

        $liste = [];
        foreach ((array) $res->json('value', []) as $team) {
            $teamId = (string) ($team['id'] ?? '');
            $kanaele = [];
            $k = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/teams/'.$teamId.'/channels', ['$select' => 'id,displayName,membershipType']);
            if ($k->failed()) {
                $fehler[] = 'Kanäle von „'.($team['displayName'] ?? $teamId).'": '.($k->json('error.message') ?: 'HTTP '.$k->status());
            } else {
                foreach ((array) $k->json('value', []) as $kanal) {
                    $kanaele[] = [
                        'name' => (string) ($kanal['displayName'] ?? ''),
                        'typ' => (string) ($kanal['membershipType'] ?? ''),
                        'id' => $teamId.'/'.(string) ($kanal['id'] ?? ''),
                    ];
                }
            }
            $liste[] = ['name' => (string) ($team['displayName'] ?? ''), 'id' => $teamId, 'kanaele' => $kanaele];
        }

        usort($liste, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $liste;
    }

}
