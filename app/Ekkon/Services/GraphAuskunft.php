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

    private const MAX_SEITEN = 5;

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
     * Unterordner eines Ordners in einer Bibliothek – für das Aufklappen auf
     * der Zugriffe-Seite (per Fetch, Ebene für Ebene).
     *
     * @param  string  $pfad  Pfad im Drive, leer = Wurzel
     * @return array<int, array{name: string, pfad: string, url: string}>
     */
    public function unterordner(string $driveId, string $pfad): array
    {
        $token = $this->verbindung->accessToken();
        $pfad = trim($pfad, '/');
        $adresse = $pfad === ''
            ? self::GRAPH.'/drives/'.rawurlencode($driveId).'/root/children'
            : self::GRAPH.'/drives/'.rawurlencode($driveId).'/root:/'.implode('/', array_map('rawurlencode', explode('/', $pfad))).':/children';

        $res = Http::withToken($token)->timeout(self::TIMEOUT)->get($adresse, ['$select' => 'name,folder,webUrl', '$top' => 200]);
        if ($res->failed()) {
            throw new \RuntimeException('Ordner nicht lesbar: '.($res->json('error.message') ?: 'HTTP '.$res->status()));
        }

        $liste = [];
        foreach ((array) $res->json('value', []) as $item) {
            if (! isset($item['folder'])) {
                continue;
            }
            $name = (string) ($item['name'] ?? '');
            $liste[] = [
                'name' => $name,
                'pfad' => ($pfad === '' ? '' : $pfad.'/').$name,
                'url' => rawurldecode((string) ($item['webUrl'] ?? '')),
            ];
        }

        usort($liste, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $liste;
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

    /**
     * SharePoint-Sites, die das Konto sieht, je mit den Bibliotheken (deren
     * Adresse ist genau die „Ablage-URL" der Maske, ggf. plus Unterordner).
     *
     * @param  array<int, string>  $fehler
     * @return array<int, array<string, mixed>>
     */
    private function sites(string $token, array &$fehler): array
    {
        $liste = [];
        $url = self::GRAPH.'/sites?search=*&$select=id,displayName,name,webUrl&$top=200';

        // Alle Sites, seitenweise – ohne Bibliotheken: Die kommen erst beim
        // Aufklappen (bibliotheken()), sonst wären es hunderte Aufrufe.
        for ($seite = 0; $seite < self::MAX_SEITEN && $url !== null; $seite++) {
            $res = Http::withToken($token)->timeout(self::TIMEOUT)->get($url);
            if ($res->failed()) {
                $fehler[] = 'Sites: '.($res->json('error.message') ?: 'HTTP '.$res->status());
                break;
            }
            foreach ((array) $res->json('value', []) as $site) {
                $liste[] = [
                    'id' => (string) ($site['id'] ?? ''),
                    'name' => (string) ($site['displayName'] ?? $site['name'] ?? ''),
                    'url' => (string) ($site['webUrl'] ?? ''),
                ];
            }
            $url = $res->json()['@odata.nextLink'] ?? null; // json('a.b') wuerde den Punkt als Pfad lesen
        }

        // Gruppengebundene Sites (Teams) fehlen in der Suche häufig – über die
        // Teams des Kontos nachholen (Team-ID = Gruppen-ID; braucht nur
        // Team.ReadBasic.All + Sites.*, keine Verzeichnis-Berechtigung).
        $gruppen = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/me/joinedTeams');
        if ($gruppen->successful()) {
            foreach ((array) $gruppen->json('value', []) as $g) {
                $site = Http::withToken($token)->timeout(self::TIMEOUT)
                    ->get(self::GRAPH.'/groups/'.rawurlencode((string) $g['id']).'/sites/root', ['$select' => 'id,displayName,name,webUrl']);
                if ($site->successful() && filled($site->json('id'))) {
                    $liste[] = [
                        'id' => (string) $site->json('id'),
                        'name' => (string) ($site->json('displayName') ?: $site->json('name') ?: $g['displayName']),
                        'url' => (string) $site->json('webUrl'),
                    ];
                }
            }
        } else {
            $fehler[] = 'Gruppen-Sites: '.($gruppen->json('error.message') ?: 'HTTP '.$gruppen->status());
        }

        return $this->sitesBereinigen($liste);
    }

    /**
     * Gezielte Site-Suche (Filtertext im Dialog) – findet auch, was die
     * Sternchen-Suche verschweigt.
     *
     * @return array<int, array{id: string, name: string, url: string}>
     */
    public function sitesSuchen(string $text): array
    {
        $token = $this->verbindung->accessToken();
        $res = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get(self::GRAPH.'/sites', ['search' => $text, '$select' => 'id,displayName,name,webUrl', '$top' => 50]);
        if ($res->failed()) {
            throw new \RuntimeException('Site-Suche: '.($res->json('error.message') ?: 'HTTP '.$res->status()));
        }

        $liste = [];
        foreach ((array) $res->json('value', []) as $site) {
            $liste[] = [
                'id' => (string) ($site['id'] ?? ''),
                'name' => (string) ($site['displayName'] ?? $site['name'] ?? ''),
                'url' => (string) ($site['webUrl'] ?? ''),
            ];
        }

        return $this->sitesBereinigen($liste);
    }

    /**
     * Doppelte (gleiche ID oder Adresse) entfernen, sortieren.
     *
     * @param  array<int, array{id: string, name: string, url: string}>  $liste
     * @return array<int, array{id: string, name: string, url: string}>
     */
    private function sitesBereinigen(array $liste): array
    {
        $gesehen = [];
        $liste = array_values(array_filter($liste, function (array $s) use (&$gesehen): bool {
            $k = mb_strtolower(rtrim($s['url'], '/')) ?: $s['id'];
            if ($k === '' || isset($gesehen[$k])) {
                return false;
            }
            $gesehen[$k] = true;

            return true;
        }));
        usort($liste, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $liste;
    }

    /**
     * Dateiordner eines Teamskanals (`filesFolder`): Drive + Ordner-Item + Adresse.
     * Damit braucht ein Kanal-Ziel keine eigene Ablage-URL – Graph kennt den Ordner.
     *
     * @param  string  $kanal  „Team-GUID/19:…@thread.tacv2"
     * @return array{driveId: string, itemId: string, url: string}
     */
    public function kanalOrdner(string $kanal): array
    {
        if (! str_contains($kanal, '/')) {
            throw new \RuntimeException('Kein Teamskanal (Form Team-GUID/Kanal-ID erwartet).');
        }
        [$team, $kanalId] = explode('/', trim($kanal), 2);

        $token = $this->verbindung->accessToken();
        $res = Http::withToken($token)->timeout(self::TIMEOUT)
            ->get(self::GRAPH.'/teams/'.rawurlencode(trim($team)).'/channels/'.rawurlencode(trim($kanalId)).'/filesFolder');
        if ($res->failed()) {
            throw new \RuntimeException('Kanalordner nicht lesbar: '.($res->json('error.message') ?: 'HTTP '.$res->status()));
        }

        return [
            'driveId' => (string) $res->json('parentReference.driveId'),
            'itemId' => (string) $res->json('id'),
            'url' => rawurldecode((string) $res->json('webUrl')),
        ];
    }

    /**
     * Bibliotheken (Drives) einer Site – beim Aufklappen im Dialog.
     *
     * @return array<int, array{id: string, name: string, url: string}>
     */
    public function bibliotheken(string $siteId): array
    {
        $token = $this->verbindung->accessToken();
        $d = Http::withToken($token)->timeout(self::TIMEOUT)->get(self::GRAPH.'/sites/'.rawurlencode($siteId).'/drives', ['$select' => 'id,name,webUrl']);
        if ($d->failed()) {
            throw new \RuntimeException('Bibliotheken nicht lesbar: '.($d->json('error.message') ?: 'HTTP '.$d->status()));
        }

        $liste = [];
        foreach ((array) $d->json('value', []) as $drive) {
            $liste[] = ['id' => (string) ($drive['id'] ?? ''), 'name' => (string) ($drive['name'] ?? ''), 'url' => rawurldecode((string) ($drive['webUrl'] ?? ''))];
        }

        return $liste;
    }
}
