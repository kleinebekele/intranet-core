<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEintrag;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Audit-Log im Verwaltungsbereich: wer hat wann was getan.
 *
 * Nur lesend. Gefiltert wird nach Benutzer (als Akteur oder Betroffener),
 * Aktion, Freitext und Zeitraum; die Filter stehen in der URL, damit sich ein
 * Ausschnitt weitergeben lässt (z. B. aus der Benutzerliste: „Verlauf").
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->query('user', 0);
        $aktion = trim((string) $request->query('aktion', ''));
        $suche = trim((string) $request->query('search', ''));
        $von = $request->date('von');
        $bis = $request->date('bis');

        $eintraege = AuditEintrag::query()
            ->when($userId > 0, fn (Builder $q) => $q->zuBenutzer($userId))
            ->when($aktion !== '', fn (Builder $q) => $q->where('aktion', $aktion))
            ->when($suche !== '', function (Builder $q) use ($suche) {
                $q->where(fn (Builder $w) => $w
                    ->where('akteur', 'like', "%{$suche}%")
                    ->orWhere('betroffener', 'like', "%{$suche}%")
                    ->orWhere('ziel', 'like', "%{$suche}%")
                    ->orWhere('beschreibung', 'like', "%{$suche}%")
                    ->orWhere('ip', 'like', "%{$suche}%"));
            })
            ->when($von, fn (Builder $q) => $q->where('created_at', '>=', $von->startOfDay()))
            ->when($bis, fn (Builder $q) => $q->where('created_at', '<=', $bis->endOfDay()))
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        return view('admin.audit.index', [
            'eintraege' => $eintraege,
            'aktionen' => Audit::bekannteAktionen(),
            'benutzer' => $userId > 0 ? User::find($userId) : null,
            'userId' => $userId,
            'aktion' => $aktion,
            'search' => $suche,
            'von' => $von?->format('Y-m-d') ?? '',
            'bis' => $bis?->format('Y-m-d') ?? '',
            'gefiltert' => $userId > 0 || $aktion !== '' || $suche !== '' || $von || $bis,
            'aufbewahrungTage' => (int) config('intranet.audit_aufbewahrung_tage'),
        ]);
    }
}
