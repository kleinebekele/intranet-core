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

    /** Ab so vielen vergeblichen Versuchen gilt eine Mail als gescheitert. */
    public const MAX_VERSUCHE = 3;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'an' => 'array',
            'versendet_am' => 'datetime',
        ];
    }

    /** Offene Posten, eilige zuerst, sonst in der Reihenfolge des Eingangs. */
    public function scopeAbzuarbeiten(Builder $query): Builder
    {
        return $query->where('status', self::WARTEND)
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
