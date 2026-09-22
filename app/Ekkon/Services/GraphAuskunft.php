<?php

namespace App\Ekkon\Services;

use Illuminate\Support\Facades\Http;

/**
 * Was sieht das verbundene Microsoft-Konto? Chats, Teams mit Kanälen und
 * SharePoint-Sites – jeweils mit der ID bzw. Adresse, die man in der
 * Channel-Maske braucht. Erspart das Suchen nach 19:…-IDs und Ordner-URLs.
 *
 * Reine Leseabfragen, bewusst gedeckelt (Seitengrößen, max. Sites), damit
 * die Seite auch in einem großen Tenant in wenigen Sekunden steht.
 */
class GraphAuskunft
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    private const TIMEOUT = 20;

    private const MAX_SITES = 60;

    public function __construct(private readonly GraphKontoVerbindung $verbindung = new GraphKontoVerbindung) {}

    /**
     * @return array{chats: array<int, array<string, string>>, teams: array<int, array<string, mixed>>, sites: array<int, array<string, mixed>>, fehler: array<int, string>}
     */
    public function zugriffe(): array
    {
        $token = $this->verbindung->accessToken();
        $fehler = [];

        return [
            'chats' => $this->chats($token, $fehler),
            'teams' => $this->teams($token, $fehler),
            'sites' => $this->sites($token, $fehler),
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
            $url = $res->json('@odata.nextLink');
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

    /**
     * SharePoint-Sites, die das Konto sieht, je mit den Bibliotheken (deren
     * Adresse ist genau die „Ablage-URL" der Maske, ggf. plus Unterordner).
     *
     * @param  array<int, string>  $fehler
     * @return array<int, array<string, mixed>>
     */
    private function sites(string $token, array &$fehler): array
    {
        $res = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/sites', ['search' => '*', '$select' => 'id,displayName,name,webUrl', '$top' => self::MAX_SITES]);
        if ($res->failed()) {
            $fehler[] = 'Sites: '.($res->json('error.message') ?: 'HTTP '.$res->status());

            return [];
        }

        $liste = [];
        foreach (array_slice((array) $res->json('value', []), 0, self::MAX_SITES) as $site) {
            $siteId = (string) ($site['id'] ?? '');
            $bibliotheken = [];
            $d = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/sites/'.$siteId.'/drives', ['$select' => 'id,name,webUrl']);
            if ($d->successful()) {
                foreach ((array) $d->json('value', []) as $drive) {
                    $bibliotheken[] = ['name' => (string) ($drive['name'] ?? ''), 'url' => rawurldecode((string) ($drive['webUrl'] ?? ''))];
                }
            }
            $liste[] = [
                'name' => (string) ($site['displayName'] ?? $site['name'] ?? ''),
                'url' => (string) ($site['webUrl'] ?? ''),
                'bibliotheken' => $bibliotheken,
            ];
        }

        usort($liste, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $liste;
    }
}
