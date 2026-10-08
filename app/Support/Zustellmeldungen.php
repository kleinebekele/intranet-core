<?php

namespace App\Support;

use App\Models\MailOutbox;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Rückmeldungen zur Zustellung einer bereits versendeten Mail.
 *
 * Der Core weiß nur, dass der Mailserver eine Mail angenommen hat („versendet").
 * Wie es danach weiterging, kann nur der Mailserver selbst sagen. Ein Modul, das
 * solche Rückmeldungen bekommt (z. B. aus dem Log des eigenen Versandservers),
 * trägt sie hier ein:
 *
 *   Zustellmeldungen::eintragen('2FD26664', 'a@b.de', MailOutbox::VERZOEGERT, '554 … (DIAL)', $zeit);
 *
 * Zugeordnet wird über `message_id` – bei SMTP-Versand steht dort die Kennung,
 * unter der der Server die Mail eingereiht hat („queued as 2FD26664"). Solche
 * Kennungen wiederholen sich irgendwann, deshalb zählen nur Mails der letzten
 * Tage. Je Empfänger gilt die jüngste Meldung; zusammengefasst gewinnt das
 * Schlechteste (abgewiesen vor verzögert vor zugestellt).
 */
class Zustellmeldungen
{
    /** Nur Mails, die höchstens so lange her versendet wurden, kommen in Frage. */
    public const FENSTER_TAGE = 14;

    private const RANG = [MailOutbox::ZUGESTELLT => 0, MailOutbox::VERZOEGERT => 1, MailOutbox::ABGEWIESEN => 2];

    /** @return bool true, wenn eine Mail im Ausgangskorb dazu gefunden wurde */
    public static function eintragen(string $kennung, string $empfaenger, string $status, ?string $grund, CarbonInterface $zeit): bool
    {
        $kennung = trim($kennung, " <>\t");
        if ($kennung === '' || ! isset(self::RANG[$status])) {
            return false;
        }

        $mail = MailOutbox::query()
            ->where('message_id', $kennung)
            ->where('versendet_am', '>=', $zeit->copy()->subDays(self::FENSTER_TAGE))
            ->latest('id')
            ->first();

        if ($mail === null) {
            return false;
        }

        $empfaenger = mb_strtolower(trim($empfaenger));
        $je = $mail->zustellung_empfaenger ?? [];
        $bisher = $je[$empfaenger] ?? null;

        // Ältere Meldung als die schon bekannte (z. B. beim Nachliefern) ändert nichts.
        if ($bisher !== null && isset($bisher['am']) && Carbon::parse($bisher['am'])->greaterThan($zeit)) {
            return true;
        }

        $je[$empfaenger] = ['status' => $status, 'grund' => $grund, 'am' => $zeit->toIso8601String()];

        $schlechteste = collect($je)->sortByDesc(fn ($e) => self::RANG[$e['status']] ?? 0)->first();

        $mail->update([
            'zustellung_empfaenger' => $je,
            'zustellung' => $schlechteste['status'],
            'zustellung_grund' => $schlechteste['status'] === MailOutbox::ZUGESTELLT ? null : $schlechteste['grund'],
            'zustellung_am' => $zeit,
        ]);

        return true;
    }
}
