<?php

namespace App\Mail\Vorlagen;

use App\Models\MailVorlage;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

/**
 * Baut aus einer Vorlage die fertige Mail und verschickt sie.
 *
 * Zusammenspiel:
 *  1. Betreff/HTML/Text kommen aus der DB, wenn dort etwas gespeichert ist –
 *     sonst aus dem Standard der {@see VorlagenDefinition}.
 *  2. Die Platzhalter der Mail werden eingesetzt.
 *  3. Das Ergebnis wird in den Rahmen (`_rahmen`) gelegt.
 *  4. Verschickt wird HTML UND Text (multipart/alternative).
 *
 * Platzhalter werden per Textersetzung eingesetzt, NICHT über Blade gerendert:
 * Wer die Vorlage im Backend bearbeitet, darf damit keinen Code ausführen
 * können. `{{ name }}` und `{{name}}` (mit/ohne Leerzeichen) gelten beide.
 */
class VorlagenMailer
{
    public function __construct(private VorlagenRegister $register) {}

    /**
     * Vorschau erzeugen, ohne zu verschicken – für den Editor.
     *
     * @param  array<string, string|int>  $werte
     * @return array{betreff: string, html: string, text: string}
     */
    public function rendern(string $schluessel, array $werte): array
    {
        $definition = $this->register->finden($schluessel);

        if (! $definition) {
            throw new \InvalidArgumentException("Unbekannte Mailvorlage „{$schluessel}\".");
        }

        return $this->rendernMit(MailVorlage::find($schluessel), $definition, $werte);
    }

    /**
     * Wie {@see rendern()}, aber mit einer ausdrücklich übergebenen (ggf. noch
     * ungespeicherten) Fassung – das braucht die Live-Vorschau im Editor.
     *
     * @param  MailVorlage|null  $fassung  null = Standard verwenden
     * @param  array<string, string|int>  $werte
     * @return array{betreff: string, html: string, text: string}
     */
    public function rendernMit(?MailVorlage $fassung, VorlagenDefinition $definition, array $werte): array
    {
        $rahmen = $this->rahmen();

        $titel = Setting::get('haupttitel', config('app.name', 'Intranet'));
        $rahmenWerte = ['titel' => $titel, 'jahr' => date('Y')];

        $betreff = $this->ersetzen($this->wert($fassung?->betreff, $definition->betreff ?? ''), $werte + $rahmenWerte);
        $htmlInhalt = $this->ersetzen($this->wert($fassung?->html, $definition->html), $werte + $rahmenWerte);
        $textInhalt = $this->ersetzen($this->wert($fassung?->text, $definition->text), $werte + $rahmenWerte);

        $html = $this->ersetzen($rahmen['html'], $rahmenWerte + ['inhalt' => $htmlInhalt]);
        $text = $this->ersetzen($rahmen['text'], $rahmenWerte + ['inhalt' => $textInhalt]);

        return ['betreff' => $betreff, 'html' => $html, 'text' => $text];
    }

    /** Gespeicherter Wert, wenn er gesetzt (nicht null/leer) ist – sonst der Standard. */
    private function wert(?string $gespeichert, string $standard): string
    {
        return ($gespeichert !== null && $gespeichert !== '') ? $gespeichert : $standard;
    }

    /**
     * Eine Vorlagen-Mail an eine Adresse verschicken.
     *
     * @param  array<string, string|int>  $werte
     */
    public function senden(string $schluessel, string $an, array $werte): void
    {
        $fertig = $this->rendern($schluessel, $werte);

        Mail::html($fertig['html'], function ($nachricht) use ($an, $fertig) {
            $nachricht->to($an)->subject($fertig['betreff'])->text($fertig['text']);
        });
    }

    /**
     * Den Rahmen holen – gespeicherte Fassung oder Standard.
     *
     * @return array{html: string, text: string}
     */
    private function rahmen(): array
    {
        $definition = $this->register->finden(VorlagenDefinition::RAHMEN);
        $gespeichert = MailVorlage::find(VorlagenDefinition::RAHMEN);

        return [
            'html' => $gespeichert->html ?? $definition->html,
            'text' => $gespeichert->text ?? $definition->text,
        ];
    }

    /**
     * Platzhalter ersetzen. Erlaubt `{{ name }}` und `{{name}}`.
     *
     * @param  array<string, string|int>  $werte
     */
    private function ersetzen(string $vorlage, array $werte): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($treffer) use ($werte) {
            // Unbekannte Platzhalter bleiben stehen, statt zu verschwinden –
            // so fällt beim Testen auf, wenn ein Wert fehlt.
            return array_key_exists($treffer[1], $werte)
                ? (string) $werte[$treffer[1]]
                : $treffer[0];
        }, $vorlage);
    }
}
