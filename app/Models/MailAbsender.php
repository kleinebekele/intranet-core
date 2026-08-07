<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein eigenständiger Absender/Antwort-an für eine Modul+Auslöser-Kombination.
 *
 * Existiert keine Zeile, bleibt es beim Absender, den das Modul oder die
 * Instanz selbst setzt. Ist eine Zeile da, gewinnt sie (siehe
 * \App\Listeners\MailInDieOutbox::absenderAnwenden).
 */
class MailAbsender extends Model
{
    protected $table = 'mail_absender';

    protected $fillable = ['modul', 'ausloeser', 'absender_name', 'absender_mail', 'antwort_an'];

    /**
     * Die passende Konfig für eine ausgehende Mail – oder null, wenn nichts
     * hinterlegt ist. Exakte Kombination aus Modul und Auslöser.
     */
    public static function fuer(?string $modul, ?string $ausloeser): ?self
    {
        if ($modul === null || $modul === '' || $ausloeser === null || $ausloeser === '') {
            return null;
        }

        return static::query()
            ->where('modul', $modul)
            ->where('ausloeser', $ausloeser)
            ->first();
    }

    /** Ist überhaupt etwas zu setzen (sonst brauchen wir die Zeile nicht)? */
    public function hatAbsender(): bool
    {
        return filled($this->absender_mail) || filled($this->antwort_an);
    }
}
