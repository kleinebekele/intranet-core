<?php

namespace App\Ekkon\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Anbindung an eine OpenAI-kompatible Chat-API – gedacht für DeutschlandGPT
 * (https://api.deutschlandgpt.de/v2, Bearer-Token), läuft aber mit jedem
 * Anbieter, der /chat/completions und /models nach OpenAI-Muster anbietet.
 *
 * Einstellungen liegen in der `settings`-Tabelle (Ekkon → Teams-Chat), nicht
 * in der .env: Schlüssel und Modell sind Betriebsentscheidungen, die ohne
 * Deploy wechseln können. Der Schlüssel wird verschlüsselt abgelegt.
 *
 * Spezialanwendungen (Custom GPTs) lassen sich laut OpenAPI-Spezifikation NICHT
 * direkt befragen – /chat/completions kennt nur `model`. Deshalb: Anweisungen und
 * Modell der Spezialanwendung per GET /custom-gpts/{id} übernehmen, Kontextwissen
 * über einen Dokumentenordner (POST /document-folders/{id}/search) selbst
 * nachschlagen und mitgeben (RAG). Ergebnis wie in der Spezialanwendung, nur
 * über dokumentierte Endpunkte (Stand 22.09.2026).
 */
class KiClient
{
    public const URL = 'ekkon.ki.url';

    public const SCHLUESSEL = 'ekkon.ki.schluessel';

    public const MODELL = 'ekkon.ki.modell';

    public const SYSTEM = 'ekkon.ki.system';

    public const AKTIV = 'ekkon.ki.aktiv';

    public const GRUPPEN_NUR_ERWAEHNT = 'ekkon.ki.gruppen_nur_erwaehnt';

    /** Spezialanwendung (Custom GPT) der DeutschlandGPT-Plattform: liefert Anweisungen + Modell. */
    public const SPEZIALANWENDUNG = 'ekkon.ki.spezialanwendung';

    /** Dokumentenordner der Plattform, der vor jeder Antwort semantisch durchsucht wird. */
    public const ORDNER = 'ekkon.ki.ordner';

    /** Intranet-Wissensquellen (Wiki …, s. App\Ekkon\Support\Wissensquellen) mitgeben. */
    public const WISSENSQUELLEN = 'ekkon.ki.wissensquellen';

    private const TREFFER = 6;

    private const WISSEN_ZEICHEN = 12000;

    public const STANDARD_URL = 'https://api.deutschlandgpt.de/v2';

    public const STANDARD_SYSTEM = 'Du bist der Assistent des Intranets der Firma. Antworte auf Deutsch, kurz und hilfreich. Du bekommst Nachrichten aus Microsoft Teams; antworte in schlichtem Text ohne Markdown-Überschriften.';

    private const TIMEOUT = 90;

    public function aktiv(): bool
    {
        // Modell kann auch aus der Spezialanwendung kommen.
        return (bool) Setting::get(self::AKTIV, false) && $this->schluessel() !== ''
            && ($this->modell() !== '' || $this->spezialanwendung() !== '');
    }

    public function url(): string
    {
        return rtrim((string) Setting::get(self::URL, self::STANDARD_URL), '/');
    }

    public function modell(): string
    {
        return (string) Setting::get(self::MODELL, '');
    }

    public function systemPrompt(): string
    {
        return (string) Setting::get(self::SYSTEM, self::STANDARD_SYSTEM);
    }

    public function gruppenNurBeiErwaehnung(): bool
    {
        return (bool) Setting::get(self::GRUPPEN_NUR_ERWAEHNT, true);
    }

    public function schluessel(): string
    {
        $wert = (string) Setting::get(self::SCHLUESSEL, '');
        if ($wert === '') {
            return '';
        }
        try {
            return Crypt::decryptString($wert);
        } catch (Throwable) {
            return '';
        }
    }

    public function schluesselSpeichern(string $klartext): void
    {
        Setting::set(self::SCHLUESSEL, $klartext === '' ? '' : Crypt::encryptString($klartext));
    }

    /**
     * Verfügbare Modelle beim Anbieter (für die Auswahl in der Maske).
     *
     * @return array<int, string>
     */
    public function modelle(): array
    {
        $res = Http::withToken($this->schluessel())->timeout(20)->acceptJson()->get($this->url().'/models');
        if ($res->failed()) {
            throw new RuntimeException('Modelle nicht abrufbar: '.$this->fehlertext($res));
        }

        $ids = array_values(array_filter(array_map(fn ($m) => (string) ($m['id'] ?? ''), (array) $res->json('data', []))));
        sort($ids, SORT_NATURAL | SORT_FLAG_CASE);

        return $ids;
    }

    /**
     * Antwort auf einen Gesprächsverlauf.
     *
     * @param  array<int, array{role: string, content: string}>  $verlauf  ohne System-Nachricht, chronologisch
     * @return array{text: string, modell: string, tokens: int}
     */
    public function antworte(array $verlauf, ?\App\Models\User $benutzer = null): array
    {
        if ($this->schluessel() === '') {
            throw new RuntimeException('Kein API-Schlüssel hinterlegt.');
        }

        // Spezialanwendung: Anweisungen und Modell übernehmen (die API kann sie
        // nicht direkt befragen – s. Klassenkommentar). Eigener Systemprompt
        // kommt als Zusatz dahinter (Teams-Hinweise), die Anweisungen führen.
        $modell = $this->modell();
        $system = $this->systemPrompt();
        $sa = $this->spezialanwendungLaden();
        if ($sa !== null) {
            if (trim((string) ($sa['instructions'] ?? '')) !== '') {
                $system = trim($sa['instructions'])."\n\n".$system;
            }
            if (($sa['modell'] ?? '') !== '') {
                $modell = $sa['modell'];
            }
        }

        // Kontextwissen: Dokumentenordner semantisch nach der aktuellen Frage
        // durchsuchen und die Treffer als Wissen mitgeben (RAG über die API).
        $wissen = $this->wissenSuchen($this->letzteFrage($verlauf), $benutzer);
        if ($wissen !== '') {
            $system .= "\n\nNutze für die Antwort vorrangig folgendes Wissen aus den Dokumenten der Firma; fehlt dort etwas, sag es ehrlich:\n\n".$wissen;
        }

        $res = Http::withToken($this->schluessel())->timeout(self::TIMEOUT)->acceptJson()
            ->post($this->url().'/chat/completions', [
                'model' => $modell,
                'messages' => array_merge([['role' => 'system', 'content' => $system]], $verlauf),
                'temperature' => 0.3,
            ]);

        if ($res->failed()) {
            throw new RuntimeException('KI-Anfrage abgelehnt: '.$this->fehlertext($res));
        }

        $text = trim((string) ($res->json('choices.0.message.content') ?? ''));
        if ($text === '') {
            throw new RuntimeException('KI hat keine Antwort geliefert.');
        }

        return [
            'text' => $text,
            'modell' => (string) ($res->json('model') ?: $this->modell()),
            'tokens' => (int) ($res->json('usage.total_tokens') ?? 0),
        ];
    }

    // ── Spezialanwendung (Custom GPT) ────────────────────────────────────

    public function spezialanwendung(): string
    {
        return (string) Setting::get(self::SPEZIALANWENDUNG, '');
    }

    /**
     * Spezialanwendungen, die der API-Key sehen darf (Custom-GPT-Berechtigungen im Dashboard).
     *
     * @return array<int, array{id: string, name: string, beschreibung: string}>
     */
    public function spezialanwendungen(): array
    {
        $res = $this->get('/custom-gpts');
        if ($res->failed()) {
            throw new RuntimeException('Spezialanwendungen nicht abrufbar: '.$this->fehlertext($res));
        }

        $liste = [];
        foreach ((array) $res->json('data', []) as $g) {
            $liste[] = ['id' => (string) ($g['id'] ?? ''), 'name' => (string) ($g['name'] ?? ''), 'beschreibung' => (string) ($g['description'] ?? '')];
        }
        usort($liste, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $liste;
    }

    /**
     * Anweisungen und Modell der eingestellten Spezialanwendung (10 Minuten gemerkt).
     *
     * @return array{name: string, instructions: string, modell: string}|null
     */
    private function spezialanwendungLaden(): ?array
    {
        $id = $this->spezialanwendung();
        if ($id === '') {
            return null;
        }

        return Cache::remember('ekkon-ki-spezialanwendung-'.$id, now()->addMinutes(10), function () use ($id): array {
            $res = $this->get('/custom-gpts/'.rawurlencode($id));
            if ($res->failed()) {
                throw new RuntimeException('Spezialanwendung nicht lesbar: '.$this->fehlertext($res));
            }

            return [
                'name' => (string) ($res->json('name') ?? ''),
                'instructions' => (string) ($res->json('instructions') ?? ''),
                'modell' => $this->modellAusId((string) ($res->json('modelId') ?? '')),
            ];
        });
    }

    /**
     * Die Spezialanwendung verweist per UUID auf ein Modell; Chat Completions
     * wollen die Modell-Kennung (z. B. claude-4.5-sonnet). Über /models/all
     * zuordnen – ohne Treffer bleibt das in der Maske gewählte Modell.
     */
    private function modellAusId(string $uuid): string
    {
        if ($uuid === '') {
            return '';
        }
        $res = $this->get('/models/all');
        if ($res->failed()) {
            return '';
        }
        foreach ((array) $res->json('data', []) as $m) {
            foreach (['uuid', 'registryId', 'registry_id', 'modelId', 'model_id'] as $feld) {
                if (strcasecmp((string) ($m[$feld] ?? ''), $uuid) === 0) {
                    return (string) ($m['id'] ?? '');
                }
            }
            if (strcasecmp((string) ($m['id'] ?? ''), $uuid) === 0) {
                return $uuid;
            }
        }

        return '';
    }

    // ── Dokumentenordner (Kontextwissen) ─────────────────────────────────

    public function ordner(): string
    {
        return (string) Setting::get(self::ORDNER, '');
    }

    /**
     * Dokumentenordner, die der API-Key sehen darf.
     *
     * @return array<int, array{id: string, name: string, dateien: int}>
     */
    public function dokumentenordner(): array
    {
        $res = $this->get('/document-folders');
        if ($res->failed()) {
            throw new RuntimeException('Dokumentenordner nicht abrufbar: '.$this->fehlertext($res));
        }

        $liste = [];
        foreach ((array) $res->json('data', []) as $o) {
            $liste[] = ['id' => (string) ($o['id'] ?? ''), 'name' => (string) ($o['name'] ?? ''), 'dateien' => (int) ($o['fileCount'] ?? 0)];
        }
        usort($liste, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $liste;
    }

    /** Sind Intranet-Wissensquellen (z. B. das Wiki) für die KI eingeschaltet? */
    public function wissensquellenAktiv(): bool
    {
        return (bool) Setting::get(self::WISSENSQUELLEN, false);
    }

    /**
     * Kontextwissen zur Frage: Treffer aus dem DeutschlandGPT-Dokumentenordner
     * und aus den Intranet-Wissensquellen (Wiki …), als Textblock mit
     * Herkunftsangaben. Leer, wenn nichts eingestellt oder nichts gefunden.
     * Der Benutzer bestimmt, was die Intranet-Quellen preisgeben dürfen.
     */
    private function wissenSuchen(string $frage, ?\App\Models\User $benutzer): string
    {
        if (trim($frage) === '') {
            return '';
        }

        $stuecke = [];
        $zeichen = 0;
        $aufnehmen = function (string $quelle, string $text) use (&$stuecke, &$zeichen): bool {
            $text = trim($text);
            if ($text === '') {
                return true;
            }
            if ($zeichen + mb_strlen($text) > self::WISSEN_ZEICHEN) {
                return false;
            }
            $zeichen += mb_strlen($text);
            $stuecke[] = '[Quelle: '.$quelle."]\n".$text;

            return true;
        };

        // Intranet zuerst: Das Wiki ist die hauseigene Wahrheit, der Ordner ergänzt.
        if ($this->wissensquellenAktiv() && \App\Ekkon\Support\Wissensquellen::verfuegbar()) {
            foreach (\App\Ekkon\Support\Wissensquellen::suchen($frage, $benutzer, self::TREFFER) as $t) {
                if (! $aufnehmen($t['quelle'], $t['text'])) {
                    break;
                }
            }
        }

        $ordner = $this->ordner();
        if ($ordner !== '') {
            $res = Http::withToken($this->schluessel())->timeout(30)->acceptJson()
                ->post($this->url().'/document-folders/'.rawurlencode($ordner).'/search', ['query' => mb_substr($frage, 0, 1000), 'limit' => self::TREFFER]);
            if ($res->failed()) {
                throw new RuntimeException('Dokumentensuche fehlgeschlagen: '.$this->fehlertext($res));
            }
            foreach ((array) ($res->json('data') ?? $res->json('results') ?? $res->json()) as $t) {
                if (! $aufnehmen((string) ($t['file_name'] ?? 'Dokument'), (string) ($t['chunk_content'] ?? ''))) {
                    break;
                }
            }
        }

        return implode("\n\n", $stuecke);
    }

    /** Die jüngste Nutzerfrage im Verlauf – Suchbegriff für das Kontextwissen. */
    private function letzteFrage(array $verlauf): string
    {
        for ($i = count($verlauf) - 1; $i >= 0; $i--) {
            if (($verlauf[$i]['role'] ?? '') === 'user') {
                return (string) $verlauf[$i]['content'];
            }
        }

        return '';
    }

    private function get(string $pfad): \Illuminate\Http\Client\Response
    {
        return Http::withToken($this->schluessel())->timeout(20)->acceptJson()->get($this->url().$pfad);
    }

    private function fehlertext(\Illuminate\Http\Client\Response $res): string
    {
        return 'HTTP '.$res->status().' '.mb_substr((string) ($res->json('error.message') ?: $res->body()), 0, 200);
    }
}
