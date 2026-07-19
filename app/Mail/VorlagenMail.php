<?php

namespace App\Mail;

use App\Mail\Vorlagen\VorlagenMailer;
use Illuminate\Mail\Mailable;

/**
 * Eine aus einer Vorlage gebaute Mail.
 *
 * So können auch Notifications die bearbeitbaren Vorlagen nutzen: Ihre
 * `toMail()` gibt einfach eine solche Mailable zurück. Der Versand läuft dann
 * wie jede andere Mail über den Ausgangskorb und die Zustellbarkeitsprüfung.
 */
class VorlagenMail extends Mailable
{
    /**
     * @param  array<string, string|int>  $werte
     */
    public function __construct(
        public string $schluessel,
        public array $werte,
    ) {}

    public function build(): self
    {
        $fertig = app(VorlagenMailer::class)->rendern($this->schluessel, $this->werte);

        return $this->subject($fertig['betreff'])
            ->html($fertig['html'])
            ->text('mail.klartext', ['text' => $fertig['text']]);
    }
}
