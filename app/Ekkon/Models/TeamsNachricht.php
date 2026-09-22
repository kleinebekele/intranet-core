<?php

namespace App\Ekkon\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Teams-Nachricht, die jemand dem verbundenen (Bot-)Konto geschrieben hat.
 * Abgeholt vom Lauscher (`teams:lauschen`), roh abgelegt; was damit passiert,
 * entscheidet die Fachlogik dahinter (Event TeamsNachrichtEmpfangen).
 */
class TeamsNachricht extends Model
{
    protected $table = 'ekkon_teams_nachrichten';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'anhaenge' => 'array',
            'diagnose' => 'array',
            'gesendet_am' => 'datetime',
            'verarbeitet_am' => 'datetime',
        ];
    }
}
