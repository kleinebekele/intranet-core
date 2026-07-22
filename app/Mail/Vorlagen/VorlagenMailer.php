<?php

namespace App\Mail\Vorlagen;

use App\Models\MailVorlage;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
    /** Interner Header, über den der Auslöser zum Ausgangskorb wandert. */
    public const QUELLE_HEADER = 'X-Intranet-Quelle';

    /**
     * Interner Header für eine freie Referenz, mit der ein Modul seine Mail im
     * Ausgangskorb wiederfindet (z. B. „newsletter:12:834"). Landet in der
     * Spalte `mail_outbox.referenz`.
     */
    public const REFERENZ_HEADER = 'X-Intranet-Referenz';

    public function __construct(private VorlagenRegister $register) {}

    /**
     * Vorschau erzeugen, ohne zu verschicken – für den Editor.
     *
     * @param  array<string, string|int>  $werte
     * @param  array<string, string|int>  $textWerte
     * @return array{betreff: string, html: string, text: string}
     */
    public function rendern(string $schluessel, array $werte, array $textWerte = []): array
    {
        $definition = $this->register->finden($schluessel);

        if (! $definition) {
            throw new \InvalidArgumentException("Unbekannte Mailvorlage „{$schluessel}\".");
        }

        return $this->rendernMit(MailVorlage::find($schluessel), $definition, $werte, $textWerte);
    }

    /**
     * Wie {@see rendern()}, aber mit einer ausdrücklich übergebenen (ggf. noch
     * ungespeicherten) Fassung – das braucht die Live-Vorschau im Editor.
     *
     * @param  MailVorlage|null  $fassung  null = Standard verwenden
     * @param  array<string, string|int>  $werte
     * @param  array<string, string|int>  $textWerte  Werte, die in der TEXTFASSUNG
     *                                                anders lauten. Nötig, sobald
     *                                                ein Platzhalter HTML enthält
     *                                                (Newsletter-Bausteine) – im
     *                                                Klartext hätten Tags nichts
     *                                                verloren. Das Logo macht es
     *                                                seit jeher genauso.
     * @return array{betreff: string, html: string, text: string}
     */
    public function rendernMit(?MailVorlage $fassung, VorlagenDefinition $definition, array $werte, array $textWerte = []): array
    {
        $titel = Setting::get('haupttitel', config('app.name', 'Intranet'));
        $rahmenWerte = ['titel' => $titel, 'jahr' => date('Y'), 'logo' => $this->logoBild($titel)];

        // Titel und Jahr darf der Editor zum Ausprobieren überschreiben ($werte
        // gewinnt). Das Logo NICHT: dessen Wert ist ein fertiges <img>-Tag, kein
        // Text, den man sinnvoll eintippen könnte.
        $alle = $werte + $rahmenWerte;
        $alle['logo'] = $rahmenWerte['logo'];

        $betreff = $this->ersetzen($this->wert($fassung?->betreff, $definition->betreff ?? ''), $alle);
        $htmlInhalt = $this->ersetzen($this->wert($fassung?->html, $definition->html), $alle);
        // In der Textfassung hat ein Bild nichts verloren.
        $alleText = ['logo' => ''] + $textWerte + $alle;
        $textInhalt = $this->ersetzen($this->wert($fassung?->text, $definition->text), $alleText);

        // Wird ein RAHMEN selbst gerendert (Vorschau im Editor), darf er nicht
        // noch einmal eingerahmt werden – sonst steckt er in sich selbst. Sein
        // `{{ inhalt }}` kommt dann aus den übergebenen Werten (Beispieltext).
        if ($definition->istRahmen()) {
            return ['betreff' => $betreff, 'html' => $htmlInhalt, 'text' => $textInhalt];
        }

        $rahmen = $this->rahmen($definition->rahmen);

        $html = $this->ersetzen($rahmen['html'], $rahmenWerte + ['inhalt' => $htmlInhalt]);
        $text = $this->ersetzen($rahmen['text'], ['logo' => ''] + $rahmenWerte + ['inhalt' => $textInhalt]);

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
     * @param  array<string, string|int>  $textWerte  s. {@see rendernMit()}
     * @param  string|null  $quelle  Name des Auslösers fürs Maillog (z. B.
     *                               „Newsletter"). Eine über {@see Mail::html()}
     *                               verschickte Mail hat sonst keine Klasse, an
     *                               der der Ausgangskorb den Auslöser erkennen
     *                               könnte – dann steht dort „—".
     * @param  string|null  $referenz  Freie Referenz, mit der ein Modul die Mail
     *                                 später im Ausgangskorb wiederfindet.
     */
    public function senden(string $schluessel, string $an, array $werte, array $textWerte = [], ?string $quelle = null, ?string $referenz = null): void
    {
        $fertig = $this->rendern($schluessel, $werte, $textWerte);

        Mail::html($fertig['html'], function ($nachricht) use ($an, $fertig, $quelle, $referenz) {
            $nachricht->to($an)->subject($fertig['betreff'])->text($fertig['text']);
            self::quelleMarkieren($nachricht, $quelle, $referenz);
        });
    }

    /**
     * Auslöser und (optional) Referenz als interne Header vermerken, die der
     * Ausgangskorb ausliest und wieder entfernt (siehe {@see \App\Listeners\MailInDieOutbox}).
     *
     * Öffentlich, damit auch Module, die {@see Mail::html()} direkt aufrufen
     * (statt über eine Vorlage), ihren Auslöser benennen können.
     */
    public static function quelleMarkieren(\Illuminate\Mail\Message $nachricht, ?string $quelle, ?string $referenz = null): void
    {
        $headers = $nachricht->getSymfonyMessage()->getHeaders();

        if ($quelle !== null && $quelle !== '') {
            $headers->addTextHeader(self::QUELLE_HEADER, $quelle);
        }

        if ($referenz !== null && $referenz !== '') {
            $headers->addTextHeader(self::REFERENZ_HEADER, $referenz);
        }
    }

    /**
     * Das Logo aus den Einstellungen als fertiges <img>-Tag – oder ein leerer
     * String, wenn keins hinterlegt ist (dann bleibt im Rahmen einfach nichts).
     *
     * Anders als die Web-Komponente `<x-marken-logo>`, die bewusst einen
     * wurzelrelativen Pfad ausgibt (das Intranet ist über mehrere Adressen
     * erreichbar), braucht eine Mail zwingend eine ABSOLUTE URL – im Postfach
     * gibt es keine Seite, gegen die ein relativer Pfad auflösen könnte.
     * `url()` nimmt dafür den Host der laufenden Anfrage und fällt außerhalb
     * einer solchen (Scheduler, Queue) auf `APP_URL` zurück.
     */
    private function logoBild(string $titel): string
    {
        $pfad = Setting::get('logo');

        if (! $pfad) {
            return '';
        }

        $url = url(parse_url(Storage::disk('public')->url($pfad), PHP_URL_PATH));
        $stempel = substr(md5($pfad), 0, 8);

        return sprintf(
            '<img src="%s?v=%s" alt="%s" height="36" style="display:block;height:36px;width:auto;max-width:180px;border:0;">',
            e($url),
            $stempel,
            e($titel),
        );
    }

    /**
     * Einen Rahmen holen – gespeicherte Fassung oder Standard.
     *
     * Module dürfen eigene Rahmen anmelden (der Newsletter tut das). Ist der
     * gewünschte Rahmen nicht da – etwa weil das Modul gerade entfernt wurde,
     * eine gespeicherte Vorlage aber noch darauf verweist –, gilt der Rahmen des
     * Core. Lieber schlichter aussehen als gar nicht verschicken.
     *
     * @return array{html: string, text: string}
     */
    private function rahmen(string $schluessel): array
    {
        $definition = $this->register->finden($schluessel)
            ?? $this->register->finden(VorlagenDefinition::RAHMEN);

        $gespeichert = MailVorlage::find($definition->schluessel);

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
