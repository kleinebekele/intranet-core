<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Einladung;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Der Einladungs-Puffer: Hier entscheidet ein Mensch, ob die vorgemerkten
 * Zugangslinks tatsächlich verschickt werden.
 */
class EinladungController extends Controller
{
    /** „Einladungen senden" arbeitet je Klick höchstens so lange (Grenze je Anfrage: 30 s). */
    private const SEKUNDEN_JE_KLICK = 20;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        // Der Suchfilter greift auf Name und E-Mail des eingeladenen Benutzers –
        // in beiden Listen, damit man eine Person auch nach der Entscheidung findet.
        $nachPerson = fn ($query) => $query->when($search !== '', fn ($q) => $q->whereHas(
            'user',
            fn ($u) => $u->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")),
        ));

        $rolle = trim((string) $request->query('rolle', ''));

        $wartend = Einladung::with('user.roles')->wartend()
            ->tap($nachPerson)
            ->tap(fn ($query) => $this->nachRolle($query, $rolle))
            ->oldest()
            ->paginate(50, ['*'], 'seite')
            ->withQueryString();

        $erledigt = Einladung::with('user')
            ->where('status', '!=', Einladung::WARTEND)
            ->tap($nachPerson)
            ->latest('entschieden_am')
            ->paginate(25, ['*'], 'erledigt_seite')
            ->withQueryString();

        $wartendGesamt = Einladung::wartend()->count();

        // Was „Alle verschicken" träfe: alle wartenden der gewählten Rolle, ohne Suche.
        $wartendAuswahl = $this->nachRolle(Einladung::wartend(), $rolle)->count();

        return view('admin.einladungen.index', [
            ...compact('wartend', 'erledigt', 'search', 'wartendGesamt', 'rolle', 'wartendAuswahl'),
            'rollen' => $this->rollenDerWartenden(),
        ]);
    }

    /** Eine einzelne Einladung verschicken. */
    public function freigeben(Request $request, Einladung $einladung): RedirectResponse
    {
        $verschickt = $einladung->freigeben($request->user());

        return back()->with('status', match (true) {
            $verschickt => "Einladung an {$einladung->user->email} ist im Ausgangskorb.",
            $einladung->status === Einladung::SCHON_ANGEMELDET => "{$einladung->user->email} hat sich schon angemeldet – keine Einladung nötig.",
            default => "Einladung an {$einladung->user->email} konnte nicht verschickt werden (keine echte Adresse oder Konto gesperrt).",
        });
    }

    /**
     * Alle wartenden auf einmal – der Regelfall nach einem Import. Mit Rolle
     * nur die wartenden dieser Rolle (z. B. erst Lehrer, Eltern später).
     *
     * Der Versand läuft über den Ausgangskorb, wird also gedrosselt und nicht
     * in einem Schwall verschickt.
     */
    public function alleFreigeben(Request $request): RedirectResponse
    {
        $verschickt = 0;
        $uebersprungen = 0;
        $schonDa = 0;
        $rolle = trim((string) $request->input('rolle', ''));

        // Jeder Zugangslink wird mit bcrypt gespeichert (≈ ¼ s je Stück) – bei Hunderten
        // reißt das die 30 s je Anfrage. Daher je Klick höchstens ~20 s; der Rest bleibt
        // „wartend" und geht beim nächsten Klick raus (nichts wird doppelt verschickt).
        $ende = microtime(true) + self::SEKUNDEN_JE_KLICK;

        foreach ($this->nachRolle(Einladung::with('user')->wartend(), $rolle)->get() as $einladung) {
            if (microtime(true) > $ende) {
                break;
            }
            if ($einladung->freigeben($request->user())) {
                $verschickt++;
            } elseif ($einladung->status === Einladung::SCHON_ANGEMELDET) {
                $schonDa++;
            } else {
                $uebersprungen++;
            }
        }

        $meldung = "{$verschickt} Einladung(en) in den Ausgangskorb gelegt.";
        $meldung .= $schonDa ? " {$schonDa} aussortiert (schon angemeldet)." : '';
        $meldung .= $uebersprungen ? " {$uebersprungen} übersprungen (keine echte Adresse oder Konto gesperrt)." : '';

        $offen = $this->nachRolle(Einladung::query()->wartend(), $rolle)->count();
        $meldung .= $offen ? " Noch {$offen} warten – bitte erneut auf „Einladungen senden“ klicken." : '';

        return back()->with('status', $meldung);
    }

    public function verwerfen(Request $request, Einladung $einladung): RedirectResponse
    {
        $einladung->verwerfen($request->user());

        return back()->with('status', "Einladung an {$einladung->user->email} verworfen.");
    }

    /** Nur Einladungen an Benutzer mit dieser Rolle; leere Rolle = alle. */
    private function nachRolle(Builder $query, string $rolle): Builder
    {
        return $query->when($rolle !== '', fn ($q) => $q->whereHas(
            'user.roles',
            fn ($r) => $r->where('roles.role_id', $rolle),
        ));
    }

    /**
     * Rollen, die unter den wartenden Einladungen vorkommen, mit Anzahl –
     * für die Auswahl über der Liste. Die Grundrolle „user" haben alle.
     *
     * @return array<string, array{name: string, anzahl: int}>
     */
    private function rollenDerWartenden(): array
    {
        $anzahl = DB::table('user_roles')
            ->join('einladungen', 'einladungen.user_id', '=', 'user_roles.user_id')
            ->where('einladungen.status', Einladung::WARTEND)
            ->where('user_roles.role_id', '!=', 'user')
            ->groupBy('user_roles.role_id')
            ->selectRaw('user_roles.role_id, count(*) as anzahl')
            ->pluck('anzahl', 'role_id');

        return Role::whereIn('role_id', $anzahl->keys())->orderBy('name')->get()
            ->mapWithKeys(fn (Role $r) => [$r->role_id => ['name' => $r->name, 'anzahl' => (int) $anzahl[$r->role_id]]])
            ->all();
    }
}
