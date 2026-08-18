<?php

namespace App\Http\Controllers\Admin;

use App\Models\MicrosoftAnmeldung;
use App\Models\User;
use App\Support\Microsoft\MicrosoftSso;
use Illuminate\View\View;

/**
 * Verwaltung → Microsoft-SSO: Zeigt, ob die Anmeldung mit dem Microsoft-Konto
 * eingerichtet ist, welche Werte gelten, und protokolliert jeden Versuch.
 *
 * Bewusst nur lesend: Client-ID und Secret gehören in die .env des Servers,
 * nicht in eine Web-Maske (sonst lägen Zugangsdaten in der Datenbank und in
 * jeder Sicherung). Diese Seite ist die Einrichtungshilfe dazu – vor allem die
 * Umleitungs-Adresse zum Abschreiben und die Liste der letzten Versuche.
 */
class MicrosoftSsoController
{
    public function __construct(private readonly MicrosoftSso $sso) {}

    public function index(): View
    {
        return view('admin.microsoft.index', [
            'aktiv' => $this->sso->aktiv(),
            'tenant' => $this->sso->tenant(),
            'clientId' => $this->sso->clientId(),
            'secretGesetzt' => config('services.microsoft.client_secret') !== null
                && config('services.microsoft.client_secret') !== '',
            'umleitung' => $this->sso->umleitungsAdresse(),
            'gruppen' => $this->sso->gruppen(),
            'rollen' => $this->sso->rollen(),
            'scopes' => $this->sso->scopes(),
            'darfAnlegen' => $this->sso->darfAnlegen(),
            'verknuepft' => User::query()->whereNotNull('microsoft_id')->count(),
            'versuche' => MicrosoftAnmeldung::query()
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
