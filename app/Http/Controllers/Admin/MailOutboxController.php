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

    /** Wert des Absender-Filters für „über den Standard-Mailer". */
    private const STANDARD = 'standard';

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), self::FILTER, true)
            ? $request->query('status')
            : null;

        $suche = trim((string) $request->query('suche', ''));
        // Mehrfachauswahl: ?modul[]=Core&modul[]=Newsletter
        $modul = collect((array) $request->query('modul', []))
            ->map(fn ($m) => trim((string) $m))->filter()->unique()->values()->all();
        $absender = (string) $request->query('absender', '');

        $konten = MailKonto::all()->keyBy(fn (MailKonto $k) => $k->mailerName());

        $mails = MailOutbox::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($suche !== '', function ($q) use ($suche) {
                $muster = '%'.addcslashes($suche, '%_\\').'%';
                $q->where(fn ($w) => $w->where('betreff', 'like', $muster)->orWhere('an', 'like', $muster));
            })
            // „Core" steht in alten Zeilen als NULL.
            ->when($modul !== [], fn ($q) => $q->where(function ($w) use ($modul) {
                $w->whereIn('modul', $modul);
                if (in_array('Core', $modul, true)) {
                    $w->orWhereNull('modul');
                }
            }))
            // Absender = der Zugang, über den die Mail rausgeht: ein SMTP-Konto
            // oder der Standard-Mailer (leer bzw. ein Mailer aus config/mail.php).
            ->when($absender === self::STANDARD, fn ($q) => $q->where(fn ($w) => $w->whereNull('mailer')
                ->orWhere('mailer', 'not like', MailKonto::PRAEFIX.'%')))
            ->when($absender !== '' && $absender !== self::STANDARD, fn ($q) => $q->where('mailer', $absender))
            // Noch nicht versendete (wartend, gescheitert, verworfen) oben, dann
            // nach Versandzeit absteigend; bei Gleichstand die neuere zuerst.
            ->orderByRaw('versendet_am IS NULL DESC')
            ->orderByDesc('versendet_am')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $limit = (int) config('mail.outbox.stundenlimit', 0);

        $module = MailOutbox::query()->distinct()->pluck('modul')
            ->map(fn ($m) => $m ?: 'Core')->unique()->sort()->values();

        $absenderListe = [self::STANDARD => 'Standard-Mailer ('.config('mail.from.address').')'];
        foreach ($konten as $name => $konto) {
            $absenderListe[$name] = "{$konto->bezeichnung} ({$konto->absender_mail})";
        }

        return view('admin.mail.index', [
            'mails' => $mails,
            'suche' => $suche,
            'modul' => $modul,
            'absender' => $absender,
            'module' => $module,
            'absenderListe' => $absenderListe,
            'empfaenger' => $this->benutzerZuAdressen($mails->getCollection()),
            // Mailer-Name (konto-<id>) → Bezeichnung des SMTP-Absenders.
            'konten' => $konten->map(fn (MailKonto $k) => $k->bezeichnung)->all(),
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
