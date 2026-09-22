<?php

namespace App\Ekkon\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Das Microsoft-Konto, in dessen Namen das Intranet Teams-Nachrichten per
 * Graph postet und Dateien nach SharePoint legt. Es gibt genau eins (das
 * zuletzt verbundene gilt); die Nachrichten erscheinen unter diesem Namen.
 *
 * Das Refresh-Token ist ein Passwort ⇒ Cast 'encrypted'. Microsoft dreht es
 * bei jeder Erneuerung weiter; solange es mindestens alle 90 Tage benutzt
 * wird, läuft es nicht ab. Ist es doch ungültig (Passwortwechsel, Entzug),
 * steht der Fehler in `letzter_fehler`, und die Maske zeigt „neu verbinden".
 */
class GraphKonto extends Model
{
    protected $table = 'ekkon_graph_konten';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'verbunden_am' => 'datetime',
            'zuletzt_benutzt_am' => 'datetime',
        ];
    }

    public static function aktuelles(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
