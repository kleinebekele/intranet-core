<?php

namespace App\Ekkon\Services;

use App\Models\Setting;
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
 */
class KiClient
{
    public const URL = 'ekkon.ki.url';

    public const SCHLUESSEL = 'ekkon.ki.schluessel';

    public const MODELL = 'ekkon.ki.modell';

    public const SYSTEM = 'ekkon.ki.system';

    public const AKTIV = 'ekkon.ki.aktiv';

    public const GRUPPEN_NUR_ERWAEHNT = 'ekkon.ki.gruppen_nur_erwaehnt';

    public const STANDARD_URL = 'https://api.deutschlandgpt.de/v2';

    public const STANDARD_SYSTEM = 'Du bist der Assistent des Intranets der Firma. Antworte auf Deutsch, kurz und hilfreich. Du bekommst Nachrichten aus Microsoft Teams; antworte in schlichtem Text ohne Markdown-Überschriften.';

    private const TIMEOUT = 90;

    public function aktiv(): bool
    {
        return (bool) Setting::get(self::AKTIV, false) && $this->schluessel() !== '' && $this->modell() !== '';
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
    public function antworte(array $verlauf): array
    {
        if ($this->schluessel() === '') {
            throw new RuntimeException('Kein API-Schlüssel hinterlegt.');
        }

        $res = Http::withToken($this->schluessel())->timeout(self::TIMEOUT)->acceptJson()
            ->post($this->url().'/chat/completions', [
                'model' => $this->modell(),
                'messages' => array_merge([['role' => 'system', 'content' => $this->systemPrompt()]], $verlauf),
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

    private function fehlertext(\Illuminate\Http\Client\Response $res): string
    {
        return 'HTTP '.$res->status().' '.mb_substr((string) ($res->json('error.message') ?: $res->body()), 0, 200);
    }
}
