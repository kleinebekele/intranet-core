<?php

namespace App\Mail;

use App\Mail\Vorlagen\VorlagenMailer;
use App\Mail\Vorlagen\VorlagenRegister;
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

        $this->subject($fertig['betreff'])
            ->html($fertig['html'])
            ->text('mail.klartext', ['text' => $fertig['text']]);

        // Modul + Auslöser fürs Maillog aus der Definition ableiten, damit die
        // Core-Mail lesbar einsortiert wird (statt bloß als „VorlagenMail").
        if ($definition = app(VorlagenRegister::class)->finden($this->schluessel)) {
            $this->withSymfonyMessage(function ($message) use ($definition): void {
                $message->getHeaders()->addTextHeader(VorlagenMailer::MODUL_HEADER, $definition->modul);
                $message->getHeaders()->addTextHeader(VorlagenMailer::QUELLE_HEADER, $definition->ausloeser());
            });
        }

        return $this;
    }
}
