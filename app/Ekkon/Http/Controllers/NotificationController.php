<?php

namespace App\Ekkon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Ekkon\Models\Notification;
use App\Ekkon\Models\NotificationRoute;
use App\Ekkon\Models\GraphKonto;
use App\Ekkon\Models\TeamsChannel;
use App\Ekkon\Services\GraphKontoVerbindung;
use App\Ekkon\Services\TeamsGraphClient;
use App\Ekkon\Services\TeamsWebhookClient;
use App\Ekkon\Support\TaskRegistry;

/**
 * Verwaltung der Benachrichtigungen: Teams-Channels, Routen, Warteschlange.
 *
 * Liegt unter den Task-Routen und erbt damit deren hartes EnsureUserIsAdmin –
 * das passt: Webhook-URLs sind Passwörter, und wer routet, entscheidet, wer
 * Betriebsmeldungen sieht.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly TaskRegistry $registry,
    ) {
    }

    public function index(): View
    {
        return view('ekkon::notifications.index', [
            'channels' => TeamsChannel::query()->orderBy('name')->get(),
            // Graph-Weg: das verbundene Microsoft-Konto (oder null) und ob die
            // Entra-App überhaupt konfiguriert ist.
            'graphKonto' => GraphKonto::aktuelles(),
            'graphMoeglich' => (new GraphKontoVerbindung)->moeglich(),
            'graphUmleitung' => (new GraphKontoVerbindung)->umleitungsAdresse(),
            'routes' => NotificationRoute::query()->with(['channel', 'mailUser'])->orderBy('meldungsart')->get(),
            // Auswahl für „Mail an einen bestimmten Administrator".
            'admins' => \App\Models\User::query()->where('is_admin', true)
                ->orderBy('name')->get(['id', 'name', 'email']),
            // Dropdown-Quelle: nur Meldungsarten, die ein Task auch wirklich
            // deklariert. Freitext wäre eine lautlose Fehlerquelle.
            'meldungsarten' => $this->registry->meldungsarten(),
            // Für die Gruppierung der Auswahl nach Modul.
            'meldungsartModule' => $this->registry->meldungsartModule(),
            // Das Konfigurations-Loch auf einen Blick: WELCHE Meldungsart hat
            // keine Route? Ohne diese Zeile sieht man im Task-Protokoll nur
            // eine Gesamtzahl und weiß nicht, wo man anfangen soll.
            'ohneRoute' => Notification::query()
                ->where('status', 'ohne_ziel')
                ->whereNotNull('meldungsart')
                ->where('meldungsart', '<>', '')
                ->selectRaw('meldungsart, count(*) as anzahl, max(created_at) as zuletzt')
                ->groupBy('meldungsart')
                ->orderByDesc('anzahl')
                ->get(),
            // Altbestand: Zeilen von vor dem 20.07.2026 haben keine Meldungsart
            // (die Spalte kam erst mit den Mailvorlagen dazu). Als "—" sind sie
            // ein Rätsel – nach QUELLE gruppiert sieht man wenigstens den Task.
            'ohneRouteAlt' => Notification::query()
                ->where('status', 'ohne_ziel')
                ->where(fn ($q) => $q->whereNull('meldungsart')->orWhere('meldungsart', ''))
                ->selectRaw('quelle, count(*) as anzahl, min(created_at) as seit, max(created_at) as zuletzt')
                ->groupBy('quelle')
                ->orderByDesc('anzahl')
                ->get(),
            'offene' => Notification::query()
                ->whereIn('status', ['pending', 'failed', 'ohne_ziel'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'letzte' => Notification::query()
                ->where('status', 'sent')
                ->latest('gesendet_am')
                ->limit(10)
                ->get(),
        ]);
    }

    // ── Teams-Channels ──────────────────────────────────────────────────

    public function channelStore(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Zwei Wege: Workflow (Webhook-URL) ODER Graph (Chat-ID). Nur die
            // Workflow-URL akzeptieren: Der klassische Connector
            // (outlook.office.com) ist seit Ende 2025 tot und würde still
            // scheitern. Lieber hier hart ablehnen als später rätseln.
            'webhook_url' => ['nullable', 'required_without:chat_id', 'url', 'starts_with:https://', 'max:2000'],
            'chat_id' => ['nullable', 'required_without:webhook_url', 'string', 'max:255', 'regex:/19:|@/'],
            'ablage_url' => ['nullable', 'required_with:chat_id', 'string', 'regex:~^https://[^/]+.sharepoint.com/~i', 'max:1000'],
            'notiz' => ['nullable', 'string', 'max:255'],
        ], [
            'webhook_url.required_without' => 'Entweder eine Webhook-URL (Workflow) oder eine Chat-ID (Graph) angeben.',
            'chat_id.required_without' => 'Entweder eine Webhook-URL (Workflow) oder eine Chat-ID (Graph) angeben.',
            'chat_id.regex' => 'Ziel: 19:…@thread.v2 (Chat), <Team-GUID>/19:…@thread.tacv2 (Kanal) oder die E-Mail-Adresse einer Person.',
            'ablage_url.required_with' => 'Für den Graph-Weg wird der SharePoint-Ordner (Ablage-URL) gebraucht, in den Anhänge gelegt werden.',
            'ablage_url.regex' => 'Der SharePoint-Ordner muss eine Adresse auf …sharepoint.com sein (aus dem Browser kopieren).',
        ]);

        if (filled($daten['webhook_url'] ?? null) && $this->istConnectorUrl($daten['webhook_url'])) {
            return back()->withInput()->withErrors(['webhook_url' => self::CONNECTOR_HINWEIS]);
        }

        TeamsChannel::create($daten + ['aktiv' => true]);

        return back()->with('status', 'Channel angelegt. Jetzt bitte "Test senden" – nur ein Blick in den Channel beweist, dass es ankommt.');
    }

    private const CONNECTOR_HINWEIS = 'Das ist eine klassische Connector-URL (outlook.office.com). Die hat Microsoft Ende 2025 abgeschaltet – bitte einen Teams-Workflow anlegen (URL auf logic.azure.com).';

    private function istConnectorUrl(string $url): bool
    {
        return str_contains($url, 'outlook.office.com');
    }

    /**
     * Name/Notiz ändern und – der eigentliche Anlass – die Webhook-URL
     * tauschen, wenn der Workflow neu angelegt werden musste. Die gespeicherte
     * URL wird nie angezeigt (sie ist ein Passwort); leer gelassen bleibt sie.
     */
    public function channelUpdate(Request $request, TeamsChannel $channel): RedirectResponse
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'starts_with:https://', 'max:2000'],
            'chat_id' => ['nullable', 'string', 'max:255', 'regex:/19:|@/'],
            'ablage_url' => ['nullable', 'required_with:chat_id', 'string', 'regex:~^https://[^/]+.sharepoint.com/~i', 'max:1000'],
            'notiz' => ['nullable', 'string', 'max:255'],
        ], [
            'chat_id.regex' => 'Ziel: 19:…@thread.v2 (Chat), <Team-GUID>/19:…@thread.tacv2 (Kanal) oder die E-Mail-Adresse einer Person.',
            'ablage_url.required_with' => 'Für den Graph-Weg wird der SharePoint-Ordner (Ablage-URL) gebraucht.',
            'ablage_url.regex' => 'Der SharePoint-Ordner muss eine Adresse auf …sharepoint.com sein (aus dem Browser kopieren).',
        ]);

        $neueUrl = trim((string) ($daten['webhook_url'] ?? ''));
        if ($neueUrl !== '' && $this->istConnectorUrl($neueUrl)) {
            return back()->withInput()->withErrors(['webhook_url' => self::CONNECTOR_HINWEIS]);
        }

        $chatId = trim((string) ($daten['chat_id'] ?? ''));
        if ($chatId === '' && $neueUrl === '' && blank($channel->webhook_url)) {
            return back()->withInput()->withErrors(['chat_id' => 'Ohne Chat-ID braucht der Channel eine Webhook-URL – eins von beiden muss bleiben.']);
        }

        $channel->name = $daten['name'];
        $channel->notiz = $daten['notiz'] ?? null;
        $channel->chat_id = $chatId !== '' ? $chatId : null;
        $channel->ablage_url = $chatId !== '' ? trim((string) ($daten['ablage_url'] ?? '')) : null;
        if ($neueUrl !== '') {
            $channel->webhook_url = $neueUrl;
        }
        $channel->save();

        return back()->with('status', $neueUrl !== ''
            ? 'Channel gespeichert, neue Webhook-URL hinterlegt. Jetzt bitte "Test senden".'
            : 'Channel gespeichert.');
    }

    public function channelToggle(TeamsChannel $channel): RedirectResponse
    {
        $channel->update(['aktiv' => ! $channel->aktiv]);

        return back();
    }

    public function channelDestroy(TeamsChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('status', 'Channel gelöscht.');
    }

    /**
     * "Test senden" – laut Konzept Pflicht, kein Luxus.
     *
     * Grund: Bei falschem Payload-Format antwortet der Teams-Workflow mit 2xx
     * und postet TROTZDEM NICHTS. Ein grüner HTTP-Status beweist hier gar
     * nichts – nur ein Blick in den Channel beweist es. Deshalb synchron, mit
     * sofortiger Rückmeldung, statt über die Warteschlange.
     */
    public function channelTest(TeamsChannel $channel): RedirectResponse
    {
        $titel = 'Testnachricht aus dem Intranet';
        $text = 'Wenn du das hier liest, funktioniert der Channel "'.$channel->name.'".';
        $daten = ['Ausgelöst' => now()->format('d.m.Y H:i'), 'Channel' => $channel->name];

        // Graph-Weg: Test mit kleiner Textdatei, damit auch Upload und
        // Dateikarte geprüft sind – genau daran hängt der Nutzen dieses Weges.
        if ($channel->perGraph()) {
            $fehler = (new TeamsGraphClient())->sende($channel, $titel, $text, $daten, [
                'name' => 'intranet-test-'.now()->format('Ymd-His').'.txt',
                'inhalt' => $text."\n",
            ]);

            return $fehler !== null
                ? back()->withErrors(['test' => 'Test fehlgeschlagen: '.$fehler])
                : back()->with('status', 'Test über Graph gepostet – Nachricht mit Dateikarte sollte im Chat stehen.');
        }

        $fehler = (new TeamsWebhookClient())->sende((string) $channel->webhook_url, $titel, $text, $daten);

        if ($fehler !== null) {
            return back()->withErrors(['test' => 'Test fehlgeschlagen: '.$fehler]);
        }

        return back()->with('status', 'Test abgeschickt (HTTP ok). ⚠ Bitte im Teams-Channel nachsehen: Bei falschem Format meldet der Workflow trotzdem Erfolg und postet nichts.');
    }

    // ── Microsoft-Konto für den Graph-Weg ───────────────────────────────

    public function graphVerbinden(Request $request, GraphKontoVerbindung $verbindung): RedirectResponse
    {
        if (! $verbindung->moeglich()) {
            return back()->withErrors(['graph' => 'Die Microsoft-Anmeldung ist nicht konfiguriert (MS_TENANT_ID, MS_CLIENT_ID, MS_CLIENT_SECRET).']);
        }

        return redirect()->away($verbindung->startUrl($request));
    }

    public function graphCallback(Request $request, GraphKontoVerbindung $verbindung): RedirectResponse
    {
        try {
            $konto = $verbindung->abschliessen($request, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return redirect()->to(route('module.ekkon.notifications.index').'#channels')->withErrors(['graph' => $e->getMessage()]);
        }

        return redirect()->to(route('module.ekkon.notifications.index').'#channels')
            ->with('status', 'Microsoft-Konto verbunden: '.$konto->name.' ('.$konto->email.'). Nachrichten über Graph erscheinen unter diesem Namen.');
    }

    /** Bibliotheken einer Site als JSON – der Dialog holt sie beim Aufklappen. */
    public function graphBibliotheken(Request $request, \App\Ekkon\Services\GraphAuskunft $auskunft): JsonResponse
    {
        $daten = $request->validate(['site' => ['required', 'string', 'max:500']]);

        try {
            return response()->json(['bibliotheken' => $auskunft->bibliotheken($daten['site'])]);
        } catch (\RuntimeException $e) {
            return response()->json(['fehler' => $e->getMessage()], 502);
        }
    }

    /**
     * Zugriffe des Kontos als JSON für den Auswahl-Dialog in der Channel-Maske.
     * Fünf Minuten gecacht: Der Dialog geht beim Anlegen mehrerer Channels
     * öfter auf, die Graph-Abfragen dauern aber ein paar Sekunden.
     */
    public function graphZugriffeJson(\App\Ekkon\Services\GraphAuskunft $auskunft): JsonResponse
    {
        if (GraphKonto::aktuelles() === null) {
            return response()->json(['fehler' => 'Erst ein Microsoft-Konto verbinden.'], 409);
        }

        try {
            $daten = \Illuminate\Support\Facades\Cache::remember('ekkon-graph-zugriffe', now()->addMinutes(5), fn () => $auskunft->zugriffe());
        } catch (\RuntimeException $e) {
            return response()->json(['fehler' => $e->getMessage()], 502);
        }

        return response()->json($daten);
    }

    /** Unterordner einer Bibliothek als JSON – die Zugriffe-Seite klappt damit Ebene für Ebene auf. */
    public function graphOrdner(Request $request, \App\Ekkon\Services\GraphAuskunft $auskunft): JsonResponse
    {
        $daten = $request->validate([
            'drive' => ['required', 'string', 'max:255'],
            'pfad' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            return response()->json(['ordner' => $auskunft->unterordner($daten['drive'], (string) ($daten['pfad'] ?? ''))]);
        } catch (\RuntimeException $e) {
            return response()->json(['fehler' => $e->getMessage()], 502);
        }
    }

    public function graphTrennen(GraphKontoVerbindung $verbindung): RedirectResponse
    {
        $verbindung->trennen();

        return back()->with('status', 'Microsoft-Konto getrennt. Channels mit Chat-ID können bis zum erneuten Verbinden nicht posten.');
    }

    // ── Routen ──────────────────────────────────────────────────────────

    public function routeStore(Request $request): RedirectResponse
    {
        // Bei Mail eines von drei Zielen: alle Admins, ein bestimmter Benutzer,
        // oder eine feste Adresse.
        $mailZiel = $request->input('typ') === 'mail' ? $request->input('mail_ziel', 'admins') : null;

        $daten = $request->validate([
            'meldungsart' => ['required', 'string', Rule::in(array_keys($this->registry->meldungsarten()))],
            'typ' => ['required', Rule::in(['mail', 'teams'])],
            'teams_channel_id' => ['nullable', 'exists:ekkon_teams_channels,id', 'required_if:typ,teams'],
            'mail_ziel' => ['nullable', Rule::in(['admins', 'benutzer', 'adresse'])],
            // Feste Adresse nur nötig, wenn Mail-Ziel „feste Adresse" ist.
            'mail_empfaenger' => ['nullable', 'email', Rule::requiredIf(fn () => $mailZiel === 'adresse')],
            // Benutzer nur nötig, wenn Mail-Ziel „bestimmter Benutzer" ist.
            'mail_user_id' => ['nullable', 'exists:users,id', Rule::requiredIf(fn () => $mailZiel === 'benutzer')],
        ]);

        // Sauber halten: nur das Feld des gewählten Ziels behalten, der Rest null.
        $werte = [
            'meldungsart' => $daten['meldungsart'],
            'typ' => $daten['typ'],
            'teams_channel_id' => null,
            'mail_empfaenger' => null,
            'mail_an_admins' => false,
            'mail_user_id' => null,
            'aktiv' => true,
        ];

        if ($daten['typ'] === 'teams') {
            $werte['teams_channel_id'] = $daten['teams_channel_id'];
        } elseif ($mailZiel === 'admins') {
            $werte['mail_an_admins'] = true;
        } elseif ($mailZiel === 'benutzer') {
            $werte['mail_user_id'] = $daten['mail_user_id'];
        } else {
            $werte['mail_empfaenger'] = $daten['mail_empfaenger'];
        }

        NotificationRoute::create($werte);

        return back()->with('status', 'Route angelegt.');
    }

    public function routeToggle(NotificationRoute $route): RedirectResponse
    {
        $route->update(['aktiv' => ! $route->aktiv]);

        return back();
    }

    public function routeDestroy(NotificationRoute $route): RedirectResponse
    {
        $route->delete();

        return back()->with('status', 'Route gelöscht.');
    }

    // ── Warteschlange ───────────────────────────────────────────────────

    /** Fehlgeschlagene Meldung erneut in die Schlange stellen. */
    public function retry(Notification $notification): RedirectResponse
    {
        $notification->update([
            'status' => 'pending',
            'versuche' => 0,
            'letzter_fehler' => null,
        ]);

        return back()->with('status', 'Benachrichtigung wird beim nächsten Lauf erneut versucht.');
    }

    /**
     * Eine liegengebliebene Meldung bewusst verwerfen – z. B. nachdem der
     * Zustand längst behoben ist oder die Meldung ohnehin niemanden mehr
     * erreichen soll. Löschen ist die einzige Art, wie eine Meldung hier
     * verschwindet; still passiert das nie.
     */
    public function destroy(Request $request, Notification $notification): RedirectResponse|JsonResponse
    {
        $notification->delete();

        // Die Liste löscht per Fetch (man bleibt, wo man ist); das Formular-
        // Fallback bekommt weiter den Redirect.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Meldung gelöscht.');
    }
}
