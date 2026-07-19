<?php

namespace App\Http\Controllers\Admin;

use App\Models\MailOutbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ],
        ]);
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
            'fehler' => null,
        ]);

        return back()->with('status', "Mail #{$mail->id} steht wieder in der Warteschlange.");
    }
}
