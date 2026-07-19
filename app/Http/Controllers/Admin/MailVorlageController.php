<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Vorlagen\VorlagenMailer;
use App\Mail\Vorlagen\VorlagenRegister;
use App\Models\MailVorlage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $this->beispielwerte($definition->platzhalter),
        );

        return response()->json($fertig);
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
            'name' => 'Anna Beispiel',
            'link' => 'https://intranet.example/passwort/setzen',
            'code' => '123456',
            'minuten' => '10',
        ];

        $werte = [];
        foreach (array_keys($platzhalter) as $schluessel) {
            $werte[$schluessel] = $beispiele[$schluessel] ?? '['.$schluessel.']';
        }

        return $werte;
    }
}
