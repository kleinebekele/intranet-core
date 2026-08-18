<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Anmeldeversuch über Microsoft – gelungen oder nicht.
 *
 * Wird bei jedem Versuch geschrieben und im Verwaltungsbereich unter
 * Microsoft-SSO angezeigt. Absicht: Wenn jemand sagt, es klappt nicht,
 * steht dort schwarz auf weiß, woran es lag.
 */
class MicrosoftAnmeldung extends Model
{
    protected $table = 'microsoft_anmeldungen';

    protected $fillable = ['email', 'microsoft_id', 'name', 'ergebnis', 'meldung', 'user_id', 'ip'];

    /** Klartext für die Anzeige – die Kurzform steht so in der Spalte. */
    public const ERGEBNISSE = [
        'angemeldet' => 'Angemeldet',
        'neu_angelegt' => 'Neu angelegt',
        'kein_konto' => 'Kein Intranet-Konto',
        'keine_gruppe' => 'Nicht in der freigegebenen Gruppe',
        'gesperrt' => 'Konto gesperrt',
        'fehler' => 'Fehler',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ist dieser Versuch gut ausgegangen? */
    public function istErfolg(): bool
    {
        return in_array($this->ergebnis, ['angemeldet', 'neu_angelegt'], true);
    }

    public function ergebnisText(): string
    {
        return self::ERGEBNISSE[$this->ergebnis] ?? $this->ergebnis;
    }
}
