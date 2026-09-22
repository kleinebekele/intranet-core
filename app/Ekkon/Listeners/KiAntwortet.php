<?php

namespace App\Ekkon\Listeners;

use App\Ekkon\Events\TeamsNachrichtEmpfangen;
use App\Ekkon\Models\TeamsNachricht;
use App\Ekkon\Services\KiClient;
use App\Ekkon\Services\TeamsGraphClient;
use Throwable;

/**
 * Beantwortet eingehende Teams-Nachrichten mit der KI (DeutschlandGPT).
 *
 * Regeln:
 *  - nur, wenn die KI unter Ekkon → Teams-Chat eingeschaltet ist;
 *  - 1:1-Chats immer, Gruppen-/Besprechungschats nur bei @-Erwähnung des Bots
 *    (abschaltbar) – sonst redet er in jedes Gespräch hinein;
 *  - als Gedächtnis dienen die letzten Nachrichten desselben Chats aus
 *    ekkon_teams_nachrichten (Frage + gegebene Antwort), chronologisch.
 *
 * Scheitert die KI oder das Posten, bleibt die Nachricht mit „Fehler: …" in
 * der Liste stehen; der Nutzer bekommt eine kurze Entschuldigung, damit er
 * nicht ins Leere schreibt.
 */
class KiAntwortet
{
    private const VERLAUF = 12;

    public function __construct(
        private readonly KiClient $ki = new KiClient,
        private readonly TeamsGraphClient $teams = new TeamsGraphClient,
    ) {}

    public function handle(TeamsNachrichtEmpfangen $event): void
    {
        $n = $event->nachricht;

        if (! $this->ki->aktiv()) {
            return;
        }

        if ($n->chat_typ !== 'oneOnOne' && $this->ki->gruppenNurBeiErwaehnung() && ! $n->bot_erwaehnt) {
            $n->update(['verarbeitet_am' => now(), 'verarbeitung' => 'Gruppe ohne @-Erwähnung – keine Antwort']);

            return;
        }

        if (trim((string) $n->text) === '') {
            $n->update(['verarbeitet_am' => now(), 'verarbeitung' => 'ohne Text (nur Anhang/Bild) – keine Antwort']);

            return;
        }

        // „Schreibt gerade": Graph kennt für Benutzerkonten keinen Tipp-Indikator.
        // Ersatz: 👀 auf die Frage und sofort ein Platzhalter „…", der nachher
        // durch die Antwort ersetzt wird – an derselben Stelle, ohne zweite Nachricht.
        $this->teams->reagieren($n->chat_id, $n->nachricht_id, '👀');
        $platzhalter = null;
        try {
            $platzhalter = $this->teams->nachrichtAnlegen($n->chat_id, '<p><i>…</i></p>');
        } catch (Throwable $e) {
            $n->update(['verarbeitung' => 'Fehler beim Posten: '.mb_substr($e->getMessage(), 0, 200)]);

            return;
        }

        try {
            $antwort = $this->ki->antworte($this->verlauf($n));
        } catch (Throwable $e) {
            $n->update(['verarbeitung' => 'Fehler: '.mb_substr($e->getMessage(), 0, 200)]);
            $this->teams->nachrichtBearbeiten($platzhalter['chat'], $platzhalter['id'], '<p>Entschuldigung, ich kann gerade nicht antworten. Bitte später noch einmal versuchen.</p>');
            $this->teams->reagieren($n->chat_id, $n->nachricht_id, '👀', entfernen: true);

            return;
        }

        $fehler = $this->teams->nachrichtBearbeiten($platzhalter['chat'], $platzhalter['id'], $this->alsHtml($antwort['text']));
        if ($fehler !== null) {
            // Bearbeiten ging nicht – dann wenigstens als neue Nachricht.
            $fehler = $this->teams->nachrichtPosten($n->chat_id, $this->alsHtml($antwort['text']));
        }
        $this->teams->reagieren($n->chat_id, $n->nachricht_id, '👀', entfernen: true);
        if ($fehler !== null) {
            $n->update(['verarbeitung' => 'Fehler beim Posten: '.mb_substr($fehler, 0, 200), 'antwort' => $antwort['text']]);

            return;
        }

        $n->update([
            'verarbeitet_am' => now(),
            'verarbeitung' => 'KI '.$antwort['modell'].($antwort['tokens'] ? ' ('.$antwort['tokens'].' Tokens)' : ''),
            'antwort' => $antwort['text'],
        ]);
    }

    /**
     * Gesprächsverlauf desselben Chats: letzte Nachrichten mit ihren Antworten,
     * älteste zuerst, die aktuelle Nachricht zum Schluss.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function verlauf(TeamsNachricht $aktuell): array
    {
        $fruehere = TeamsNachricht::query()
            ->where('chat_id', $aktuell->chat_id)
            ->where('id', '<', $aktuell->id)
            ->latest('id')
            ->limit(self::VERLAUF)
            ->get()
            ->reverse();

        $verlauf = [];
        foreach ($fruehere as $m) {
            if (trim((string) $m->text) === '') {
                continue;
            }
            $verlauf[] = ['role' => 'user', 'content' => $this->mitName($m)];
            if (filled($m->antwort)) {
                $verlauf[] = ['role' => 'assistant', 'content' => (string) $m->antwort];
            }
        }
        $verlauf[] = ['role' => 'user', 'content' => $this->mitName($aktuell)];

        return $verlauf;
    }

    /** In Gruppen hilft der Name, wer spricht; im 1:1 ist er überflüssig. */
    private function mitName(TeamsNachricht $m): string
    {
        return $m->chat_typ === 'oneOnOne' || blank($m->von_name)
            ? (string) $m->text
            : $m->von_name.': '.$m->text;
    }

    /** Schlichtes Markdown der KI (Absätze, **fett**, Listen) als Teams-HTML. */
    private function alsHtml(string $text): string
    {
        $html = '';
        foreach (preg_split('/\r?\n/', trim($text)) ?: [] as $zeile) {
            $z = e(trim($zeile));
            $z = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $z) ?? $z;
            $z = preg_replace('/^#{1,6}\s+(.+)$/', '<b>$1</b>', $z) ?? $z;
            $z = preg_replace('/^[-*•]\s+/', '• ', $z) ?? $z;
            $html .= $z === '' ? '<br>' : $z.'<br>';
        }

        $html = preg_replace('/(<br>)+$/', '', $html) ?? $html;

        return '<p>'.preg_replace('/(<br>){3,}/', '<br><br>', $html).'</p>';
    }
}
