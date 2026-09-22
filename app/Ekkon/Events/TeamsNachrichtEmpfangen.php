<?php

namespace App\Ekkon\Events;

use App\Ekkon\Models\TeamsNachricht;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Der Lauscher hat eine neue Teams-Nachricht an das Bot-Konto abgelegt.
 *
 * Hier hängt sich die Fachlogik ein (Listener im Core oder in einem Modul):
 * Nachricht lesen, antworten über TeamsGraphClient::nachrichtPosten(),
 * anschließend `verarbeitet_am`/`verarbeitung`/`antwort` an der Zeile setzen.
 * Wirft ein Listener, protokolliert der Lauscher den Fehler und läuft weiter.
 */
class TeamsNachrichtEmpfangen
{
    use Dispatchable;

    public function __construct(public readonly TeamsNachricht $nachricht) {}
}
