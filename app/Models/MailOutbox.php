<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Mime\Email;

/**
 * Eine E-Mail im Ausgangskorb – vor dem Versand Auftrag, danach Protokolleintrag.
 *
 * Siehe \App\Listeners\MailInDieOutbox (füllt) und
 * \App\Console\Commands\MailAusliefern (leert).
 */
class MailOutbox extends Model
{
    protected $table = 'mail_outbox';

    public const WARTEND = 'wartend';

    public const VERSENDET = 'versendet';

    public const FEHLGESCHLAGEN = 'fehlgeschlagen';

    /** Gescheitert und abgehakt – von Hand oder automatisch, siehe `mail:aufraeumen`. */
    public const VERWORFEN = 'verworfen';

    /** So lange darf eine Mail auf „fehlgeschlagen" stehen, dann wird sie verworfen. */
    public const VERWERFEN_NACH_TAGEN = 10;

    /** Verworfene Mails verschwinden so viele Tage nach ihrem Eingang aus dem Log. */
    public const ENTFERNEN_NACH_TAGEN = 30;

    /**
     * Wartezeit in Minuten nach dem 1., 2., 3. … Fehlschlag. Ein Fehlschlag mehr
     * als Einträge hier = endgültig gescheitert. Die Abstände überbrücken eine
     * vorübergehende Sperre des Providers, statt die Versuche im Minutentakt
     * zu verbrennen.
     */
    public const WARTEZEITEN = [5, 15, 60];

    /** Ab so vielen vergeblichen Versuchen gilt eine Mail als gescheitert. */
    public const MAX_VERSUCHE = 4;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'an' => 'array',
            'versendet_am' => 'datetime',
            'naechster_versuch_am' => 'datetime',
            'verworfen_am' => 'datetime',
        ];
    }

    /**
     * Absender aus dem From-Kopf der gespeicherten Nachricht, z. B.
     * „Schulbüro <buero@example.org>" – null, wenn die Nachricht nicht lesbar ist.
     */
    public function absender(): ?string
    {
        try {
            $from = $this->alsEmail()->getFrom()[0] ?? null;
        } catch (\Throwable) {
            return null;
        }

        return $from?->toString();
    }

    /** Darf man die Mail verwerfen? Nur, wenn sie gescheitert ist oder mit Fehler wartet. */
    public function verwerfbar(): bool
    {
        return $this->status === self::FEHLGESCHLAGEN
            || ($this->status === self::WARTEND && filled($this->fehler));
    }

    public function verwerfen(): void
    {
        $this->update([
            'status' => self::VERWORFEN,
            'verworfen_am' => now(),
            'naechster_versuch_am' => null,
        ]);
    }

    /**
     * Offene Posten, deren Wartezeit abgelaufen ist – eilige zuerst, sonst in
     * der Reihenfolge des Eingangs.
     */
    public function scopeAbzuarbeiten(Builder $query): Builder
    {
        return $query->where('status', self::WARTEND)
            ->where(fn (Builder $q) => $q->whereNull('naechster_versuch_am')
                ->orWhere('naechster_versuch_am', '<=', now()))
            ->orderByDesc('prioritaet')
            ->orderBy('id');
    }

    /**
     * Die gespeicherte Nachricht wieder zu einem versendbaren Objekt machen.
     *
     * Bewusst NICHT `nachricht()`: Eloquent würde eine Methode mit dem Namen
     * einer Spalte als Accessor/Relation missdeuten.
     */
    public function alsEmail(): Email
    {
        $objekt = unserialize(base64_decode($this->nachricht));

        if (! $objekt instanceof Email) {
            throw new \RuntimeException('Gespeicherte Nachricht ist keine gültige E-Mail.');
        }

        return $objekt;
    }

    /** Eine Symfony-Nachricht für die Ablage in der Spalte `nachricht` verpacken. */
    public static function verpacken(Email $email): string
    {
        return base64_encode(serialize($email));
    }
}
