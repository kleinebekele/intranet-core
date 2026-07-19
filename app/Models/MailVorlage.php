<?php

namespace App\Models;

use App\Mail\Vorlagen\VorlagenDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Die im Backend gespeicherte Fassung einer Mailvorlage.
 *
 * Existiert für einen Schlüssel keine Zeile, gilt der Standard aus der
 * {@see VorlagenDefinition}. Gespeichert wird also nur, was
 * jemand bewusst geändert hat.
 */
class MailVorlage extends Model
{
    protected $table = 'mail_vorlagen';

    protected $primaryKey = 'schluessel';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['schluessel', 'betreff', 'html', 'text'];
}
