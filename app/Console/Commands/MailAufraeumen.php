<?php

namespace App\Console\Commands;

use App\Models\MailOutbox;
use Illuminate\Console\Command;

/**
 * Hält das Maillog schlank:
 *  - Mails, die länger als 10 Tage auf „fehlgeschlagen" stehen, werden verworfen
 *    (gezählt ab dem letzten Fehlschlag, also `updated_at`).
 *  - Verworfene Mails werden 30 Tage nach ihrem Eingang endgültig gelöscht.
 *
 * Versendete Mails bleiben unberührt.
 */
class MailAufraeumen extends Command
{
    protected $signature = 'mail:aufraeumen';

    protected $description = 'Verwirft alte fehlgeschlagene Mails und löscht verworfene aus dem Maillog';

    public function handle(): int
    {
        $verworfen = MailOutbox::where('status', MailOutbox::FEHLGESCHLAGEN)
            ->where('updated_at', '<', now()->subDays(MailOutbox::VERWERFEN_NACH_TAGEN))
            ->update([
                'status' => MailOutbox::VERWORFEN,
                'verworfen_am' => now(),
                'updated_at' => now(),
            ]);

        $geloescht = MailOutbox::where('status', MailOutbox::VERWORFEN)
            ->where('created_at', '<', now()->subDays(MailOutbox::ENTFERNEN_NACH_TAGEN))
            ->delete();

        $this->info("{$verworfen} verworfen, {$geloescht} gelöscht.");

        return self::SUCCESS;
    }
}
