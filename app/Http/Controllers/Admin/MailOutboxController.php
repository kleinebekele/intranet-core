<?php

namespace App\Http\Controllers\Admin;

use App\Models\MailKonto;
use App\Models\MailOutbox;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Einblick in den Mail-Ausgangskorb: was wartet, was ging raus, was scheiterte.
 *
 * Beantwortet die Frage, die Laravel von sich aus nicht beantwortet – ist die
 * Mail eigentlich rausgegangen?
 */
class MailOutboxController
{
    /** Zulässige Werte des Status-Filters. */
    private const FILTER = [
        MailOutbox::WARTEND,
        MailOutbox::VERSENDET,
        MailOutbox::FEHLGESCHLAGEN,
        MailOutbox::VERWORFEN,
    ];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), self::FILTER, true)
            ? $request->query('status')
            : null;

        $mails = MailOutbox::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $limit = (int) config('mail.outbox.stundenlimit', 0);

        return view('admin.mail.index', [
            'mails' => $mails,
            'empfaenger' => $this->benutzerZuAdressen($mails->getCollection()),
            // Mailer-Name (konto-<id>) → Bezeichnung des SMTP-Absenders.
            'konten' => MailKonto::all()->mapWithKeys(fn (MailKonto $k) => [$k->mailerName() => $k->bezeichnung])->all(),
            'status' => $status,
            'aktiv' => (bool) config('mail.outbox.aktiv', true),
            'limit' => $limit,
            // Gleitend über 60 Minuten – so zählt auch der Auslieferungs-Task.
            'letzteStunde' => MailOutbox::where('status', MailOutbox::VERSENDET)
                ->where('versendet_am', '>=', now()->subHour())
                ->count(),
            'anzahl' => [
                MailOutbox::WARTEND => MailOutbox::where('status', MailOutbox::WARTEND)->count(),
                MailOutbox::VERSENDET => MailOutbox::where('status', MailOutbox::VERSENDET)->count(),
                MailOutbox::FEHLGESCHLAGEN => MailOutbox::where('status', MailOutbox::FEHLGESCHLAGEN)->count(),
                MailOutbox::VERWORFEN => MailOutbox::where('status', MailOutbox::VERWORFEN)->count(),
            ],
        ]);
    }

    /**
     * Die Benutzer hinter den Empfängeradressen dieser Seite, für den Tooltip
     * in der Spalte „An". Schlüssel ist die Adresse in Kleinbuchstaben.
     *
     * @param  Collection<int, MailOutbox>  $mails
     * @return array<string, User>
     */
    private function benutzerZuAdressen(Collection $mails): array
    {
        $adressen = $mails->flatMap(fn (MailOutbox $m) => (array) $m->an)
            ->map(fn ($a) => mb_strtolower(trim((string) $a)))
            ->filter()
            ->unique()
            ->values();

        if ($adressen->isEmpty()) {
            return [];
        }

        return User::with(['roles' => fn ($q) => $q->orderBy('name')])
            ->whereIn(DB::raw('LOWER(email)'), $adressen->all())
            ->get()
            ->keyBy(fn (User $u) => mb_strtolower($u->email))
            ->all();
    }

    /**
     * Eine gescheiterte Mail zurück in die Warteschlange stellen.
     *
     * Der Versuchszähler wird zurückgesetzt, sonst wäre sie nach dem ersten
     * neuen Fehlschlag sofort wieder endgültig gescheitert.
     */
    public function erneut(MailOutbox $mail): RedirectResponse
    {
        if ($mail->status === MailOutbox::VERSENDET) {
            return back()->withErrors('Diese Mail wurde bereits versendet.');
        }

        $mail->update([
            'status' => MailOutbox::WARTEND,
            'versuche' => 0,
            'naechster_versuch_am' => null,
            'verworfen_am' => null,
            'fehler' => null,
        ]);

        return back()->with('status', "Mail #{$mail->id} steht wieder in der Warteschlange.");
    }

    /** Eine gescheiterte Mail abhaken – unwichtiger Fehler, kein neuer Versuch. */
    public function verwerfen(MailOutbox $mail): RedirectResponse
    {
        if (! $mail->verwerfbar()) {
            return back()->withErrors('Nur fehlgeschlagene Mails lassen sich verwerfen.');
        }

        $mail->verwerfen();

        return back()->with('status', "Mail #{$mail->id} verworfen.");
    }
}
