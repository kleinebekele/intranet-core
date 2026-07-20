<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Vorlagen\VorlagenMailer;
use App\Mail\Vorlagen\VorlagenRegister;
use App\Models\MailVorlage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Zustellbarkeit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Verwaltung → Mailvorlagen: Betreff, HTML und Textfassung jeder Mail
 * bearbeiten, eine Vorschau ansehen und auf den Standard zurücksetzen.
 */
class MailVorlageController extends Controller
{
    public function __construct(
        private VorlagenRegister $register,
        private VorlagenMailer $mailer,
    ) {}

    public function index(): View
    {
        return view('admin.mailvorlagen.index', [
            'vorlagen' => $this->register->alle(),
        ]);
    }

    public function edit(string $schluessel): View
    {
        $definition = $this->register->finden($schluessel) ?? abort(404);

        return view('admin.mailvorlagen.edit', [
            'definition' => $definition,
            'gespeichert' => MailVorlage::find($schluessel),
            'beispielwerte' => $this->beispielwerte($definition->platzhalter),
            // Für die „Vorschau anhand eines Benutzers" – nur die Felder, die
            // die Auswahl braucht.
            'benutzer' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, string $schluessel): RedirectResponse
    {
        $definition = $this->register->finden($schluessel) ?? abort(404);

        $daten = $request->validate([
            'betreff' => ['nullable', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
            'text' => ['nullable', 'string'],
        ]);

        // Deckt sich alles mit dem Standard, wird die Zeile gelöscht: Dann gilt
        // wieder der mitgelieferte Text und eine spätere Verbesserung greift.
        $istStandard = trim((string) ($daten['betreff'] ?? '')) === trim((string) ($definition->betreff ?? ''))
            && trim((string) ($daten['html'] ?? '')) === trim($definition->html)
            && trim((string) ($daten['text'] ?? '')) === trim($definition->text);

        if ($istStandard) {
            MailVorlage::where('schluessel', $schluessel)->delete();

            return redirect()->route('admin.mailvorlagen.index')
                ->with('status', "Vorlage „{$definition->titel}\" steht wieder auf dem Standard.");
        }

        MailVorlage::updateOrCreate(
            ['schluessel' => $schluessel],
            ['betreff' => $daten['betreff'] ?? null, 'html' => $daten['html'] ?? null, 'text' => $daten['text'] ?? null],
        );

        return redirect()->route('admin.mailvorlagen.index')
            ->with('status', "Vorlage „{$definition->titel}\" gespeichert.");
    }

    public function reset(string $schluessel): RedirectResponse
    {
        $definition = $this->register->finden($schluessel) ?? abort(404);
        MailVorlage::where('schluessel', $schluessel)->delete();

        return redirect()->route('admin.mailvorlagen.edit', $schluessel)
            ->with('status', "Vorlage „{$definition->titel}\" auf den Standard zurückgesetzt.");
    }

    /**
     * Live-Vorschau: rendert die im Formular eingegebenen Texte, ohne sie zu
     * speichern. Wird per fetch() aus dem Editor aufgerufen.
     */
    public function vorschau(Request $request, string $schluessel): JsonResponse
    {
        $definition = $this->register->finden($schluessel) ?? abort(404);

        // Die Formularwerte einsetzen, ohne sie zu speichern.
        $fassung = new MailVorlage([
            'schluessel' => $schluessel,
            'betreff' => $request->input('betreff'),
            'html' => $request->input('html'),
            'text' => $request->input('text'),
        ]);

        $fertig = $this->mailer->rendernMit(
            $fassung,
            $definition,
            $this->werteAusRequest($definition->platzhalter, $request),
        );

        return response()->json($fertig);
    }

    /**
     * Eine Testmail mit den aktuell im Editor stehenden (noch ungespeicherten)
     * Texten an eine frei eingegebene Adresse schicken.
     *
     * Die Werte kommen wahlweise aus einem ausgewählten Benutzer – so sieht man
     * die Mail so, wie dieser Empfänger sie bekäme. Der `link` bleibt aber ein
     * Beispiel-Link: Ein echter Passwort-Token, an eine fremde Adresse
     * geschickt, wäre ein Weg, ein fremdes Konto zu übernehmen.
     */
    public function testmail(Request $request, string $schluessel): JsonResponse
    {
        $definition = $this->register->finden($schluessel) ?? abort(404);

        $daten = $request->validate([
            'an' => ['required', 'email'],
            'betreff' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'text' => ['nullable', 'string'],
            'werte' => ['nullable', 'array'],
        ]);

        if (! Zustellbarkeit::zustellbar($daten['an'])) {
            return response()->json(['ok' => false, 'meldung' => 'An diese Adresse kann nicht zugestellt werden.'], 422);
        }

        $fassung = new MailVorlage([
            'schluessel' => $schluessel,
            'betreff' => $daten['betreff'] ?? null,
            'html' => $daten['html'] ?? null,
            'text' => $daten['text'] ?? null,
        ]);

        $fertig = $this->mailer->rendernMit(
            $fassung,
            $definition,
            $this->werteAusRequest($definition->platzhalter, $request),
        );

        Mail::html($fertig['html'], function ($nachricht) use ($daten, $fertig) {
            $nachricht->to($daten['an'])->subject('[TEST] '.$fertig['betreff'])->text($fertig['text']);
        });

        return response()->json([
            'ok' => true,
            'meldung' => "Testmail an {$daten['an']} liegt im Ausgangskorb (Maillog zeigt den Versand).",
        ]);
    }

    /**
     * Die Werte für Vorschau/Testmail.
     *
     * Grundlage sind die Beispielwerte; was im Editor je Platzhalter eingegeben
     * wurde, überschreibt sie. Es werden nur Platzhalter übernommen, die diese
     * Vorlage überhaupt kennt – Fremdes wird verworfen.
     *
     * @param  array<string, string>  $platzhalter
     * @return array<string, string>
     */
    private function werteAusRequest(array $platzhalter, Request $request): array
    {
        $werte = $this->beispielwerte($platzhalter);

        foreach ((array) $request->input('werte', []) as $name => $wert) {
            if (array_key_exists($name, $werte) && is_scalar($wert)) {
                $werte[$name] = (string) $wert;
            }
        }

        return $werte;
    }

    /**
     * Nachvollziehbare Beispielwerte für die Vorschau.
     *
     * @param  array<string, string>  $platzhalter
     * @return array<string, string>
     */
    private function beispielwerte(array $platzhalter): array
    {
        $beispiele = [
            // Titel und Jahr sind keine erfundenen Beispiele, sondern die echten
            // Werte – sonst stünde in der Rahmen-Vorschau „[titel]" statt des
            // Haupttitels, und man könnte den Kopf gar nicht beurteilen.
            'titel' => Setting::get('haupttitel', config('app.name', 'Intranet')),
            'jahr' => date('Y'),
            'name' => 'Anna Beispiel',
            'link' => 'https://intranet.example/passwort/setzen',
            'code' => '123456',
            'minuten' => '10',
            'neue_mail' => 'anna.neu@example.org',
            'inhalt' => 'Hier steht der Text der jeweiligen Mail.',
            'ueberschrift' => 'Beispiel-Überschrift der Meldung',
            'text' => "Beispieltext der Meldung.\nKann mehrere Zeilen und einen Link enthalten.",
            'quelle' => 'Beispiel/Task',
        ];

        $werte = [];
        foreach (array_keys($platzhalter) as $schluessel) {
            $werte[$schluessel] = $beispiele[$schluessel] ?? '['.$schluessel.']';
        }

        return $werte;
    }
}
