<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mailvorlage: {{ $definition->titel }}</h1>
    </x-slot>

    @php
        $htmlWert = old('html', $gespeichert->html ?? $definition->html);
        $textWert = old('text', $gespeichert->text ?? $definition->text);
        $betreffWert = old('betreff', $gespeichert->betreff ?? $definition->betreff);
        $istRahmen = $definition->istRahmen();
        // Das Logo ist ein fertiges <img>-Tag aus den Einstellungen, kein Text,
        // den man in ein Vorschau-Feld tippen würde.
        $werteFelder = array_diff_key($definition->platzhalter, ['logo' => true]);
    @endphp

    <div x-data="mailEditor({
             vorschauUrl: '{{ route('admin.mailvorlagen.vorschau', $definition->schluessel) }}',
             testmailUrl: '{{ route('admin.mailvorlagen.testmail', $definition->schluessel) }}',
             csrf: '{{ csrf_token() }}',
             istRahmen: @js($istRahmen)
         })">
        <div class="mb-4">
            <a href="{{ route('admin.mailvorlagen.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; alle Vorlagen</a>
        </div>

        <p class="mb-4 max-w-3xl text-gray-600">{{ $definition->beschreibung }}</p>

        <form method="POST" action="{{ route('admin.mailvorlagen.update', $definition->schluessel) }}"
              @submit="vorSpeichern">
            @csrf
            @method('PUT')

            {{-- Über den Reitern steht, was in JEDEM Reiter gebraucht wird. --}}
            @unless ($istRahmen)
                <label class="mb-1 block text-sm font-medium text-gray-700">Betreff</label>
                <input type="text" name="betreff" x-model="betreffWert" @input="nachVorschau"
                       class="mb-4 block w-full max-w-3xl rounded-lg border-gray-300 text-sm">
            @endunless

            {{-- Platzhalter-Hilfe: gilt für formatierte Fassung UND reinen Text. --}}
            <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Platzhalter (Klick zum Kopieren)</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($definition->platzhalter as $name => $erklaerung)
                        {{-- Klammern getrennt zusammensetzen: Blade würde ein literales
                             Doppel-Geschweift auch hier im PHP-String als Ausgabe deuten. --}}
                        @php($marke = '{'.'{ '.$name.' }'.'}')
                        <button type="button" @click="platzhalterKopieren(@js($marke))"
                                title="{{ $erklaerung }}"
                                class="rounded-lg border border-gray-300 bg-white px-2 py-1 font-mono text-xs text-gray-700 hover:border-indigo-400 hover:text-indigo-700">
                            {{ $marke }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Reiter --}}
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-6">
                    @foreach ([
                        'html' => 'Formatierte Fassung',
                        'text' => 'Reiner Text',
                        'vorschau' => 'Vorschau',
                    ] as $schluessel => $beschriftung)
                        <button type="button" @click="reiter = '{{ $schluessel }}'"
                                class="border-b-2 px-1 py-3 text-sm font-medium"
                                :class="reiter === '{{ $schluessel }}'
                                    ? 'border-indigo-600 text-indigo-700'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'">
                            {{ $beschriftung }}
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- ── Reiter 1: Formatierte Fassung ───────────────────────────── --}}
            <div x-show="reiter === 'html'" class="pt-4">
                @if ($istRahmen)
                    {{-- Der Rahmen ist ein vollständiges HTML-Dokument (<!DOCTYPE>,
                         <html>, <body>). Ein Rich-Text-Feld kann das nicht halten:
                         Beim Einlesen in ein contenteditable wirft der Browser die
                         Dokumenthülle weg – gespeichert würde ein Rumpf ohne Kopf.
                         Deshalb hier bewusst nur der Quelltext. --}}
                    <p class="mb-2 flex items-start gap-1.5 text-xs text-gray-500">
                        <i class='bx bx-info-circle mt-0.5'></i>
                        <span>
                            Der Rahmen ist ein vollständiges HTML-Dokument – deshalb gibt es hier
                            nur den Quelltext. Ein Formatier-Feld würde <code>&lt;!DOCTYPE&gt;</code>
                            und <code>&lt;html&gt;</code> beim Speichern verwerfen.
                        </span>
                    </p>
                    <textarea x-model="html" @input="nachVorschau" spellcheck="false"
                              class="block h-[32rem] w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>
                @else
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-700">Formatierte Fassung (HTML)</label>
                        <button type="button" @click="htmlModus = ! htmlModus"
                                class="text-xs text-indigo-600 hover:underline"
                                x-text="htmlModus ? 'zurück zur Ansicht' : 'HTML-Quelltext'"></button>
                    </div>

                    {{-- Werkzeugleiste (nur im Ansicht-Modus) --}}
                    <div x-show="! htmlModus" class="flex flex-wrap gap-1 rounded-t-lg border border-b-0 border-gray-300 bg-gray-50 p-1">
                        <button type="button" @click="format('bold')" class="rounded px-2 py-1 text-sm font-bold hover:bg-gray-200">B</button>
                        <button type="button" @click="format('italic')" class="rounded px-2 py-1 text-sm italic hover:bg-gray-200">I</button>
                        <button type="button" @click="format('insertUnorderedList')" class="rounded px-2 py-1 text-sm hover:bg-gray-200">• Liste</button>
                        <button type="button" @click="linkSetzen" class="rounded px-2 py-1 text-sm hover:bg-gray-200">🔗 Link</button>
                    </div>

                    {{-- WYSIWYG. `overflow-auto` + die Regeln in <style> halten
                         breite Inhalte (Tabellen, Bilder) im Feld – vorher ragte
                         eine Mail-Tabelle sichtbar aus ihrem Kasten heraus. --}}
                    <div x-show="! htmlModus" x-ref="wysiwyg" contenteditable="true"
                         @input="ausWysiwyg"
                         class="mail-wysiwyg block max-h-96 min-h-[16rem] w-full overflow-auto break-words rounded-b-lg border border-gray-300 bg-white p-3 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"></div>

                    {{-- HTML-Quelltext --}}
                    <textarea x-show="htmlModus" x-model="html" @input="nachVorschau" spellcheck="false"
                              class="block h-96 w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>
                @endif

                <input type="hidden" name="html" :value="html">
            </div>

            {{-- ── Reiter 2: Reiner Text ──────────────────────────────────── --}}
            <div x-show="reiter === 'text'" class="pt-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">Reiner Text (ohne Formatierung)</label>
                <p class="mb-2 text-xs text-gray-500">
                    Diese Fassung geht als zweite Spur mit und wird angezeigt, wenn ein
                    Mailprogramm kein HTML darstellt.
                </p>
                <textarea name="text" x-model="text" @input="nachVorschau" spellcheck="false"
                          class="block h-[32rem] w-full rounded-lg border-gray-300 font-mono text-xs">{{ $textWert }}</textarea>
            </div>

            {{-- ── Reiter 3: Vorschau (samt Testmail) ─────────────────────── --}}
            <div x-show="reiter === 'vorschau'" class="pt-4">
                {{-- Vorschau-Werte: genau die Platzhalter DIESER Vorlage --}}
                <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-1 text-sm font-medium text-gray-700">Werte für Vorschau &amp; Testmail</div>
                    <p class="mb-3 text-xs text-gray-500">
                        Nur zum Ansehen und Testen – gespeichert wird davon nichts.
                    </p>

                    @php($hatName = array_key_exists('name', $werteFelder))

                    @if ($hatName)
                        {{-- Benutzer suchen und übernehmen (Muster: Korrektoren-Feld im Zeugnismodul) --}}
                        <div class="mb-3 relative" @click.outside="treffer = []">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Benutzer übernehmen</label>
                            <input type="text" x-model="suche" @input="suchen" placeholder="Name oder E-Mail tippen …"
                                   autocomplete="off" class="w-full max-w-md rounded-lg border-gray-300 text-sm">
                            <ul x-show="treffer.length" x-cloak
                                class="absolute z-10 mt-1 max-h-56 w-full max-w-md overflow-auto rounded-lg border border-gray-200 bg-white shadow">
                                <template x-for="t in treffer" :key="t.id">
                                    <li @click="benutzerUebernehmen(t)"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-indigo-50">
                                        <span x-text="t.name" class="font-medium"></span>
                                        <span x-text="t.email" class="ml-1 text-gray-500"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    @endif

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($werteFelder as $name => $erklaerung)
                            <div @class(['md:col-span-2 xl:col-span-3' => $name === 'text' || $name === 'inhalt'])>
                                <label class="mb-1 block text-xs font-medium text-gray-600">
                                    <code>{{ $name }}</code>
                                    <span class="ml-1 font-normal text-gray-400">{{ $erklaerung }}</span>
                                </label>
                                @if ($name === 'text' || $name === 'inhalt')
                                    <textarea x-model="werte['{{ $name }}']" @input="nachVorschau" rows="3"
                                              class="block w-full rounded-lg border-gray-300 text-sm"></textarea>
                                @else
                                    <input type="text" x-model="werte['{{ $name }}']" @input="nachVorschau"
                                           class="block w-full rounded-lg border-gray-300 text-sm">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Vorschau --}}
                <div class="rounded-xl border border-gray-200 bg-white p-2">
                    <div class="mb-2 border-b border-gray-100 px-2 py-1 text-sm text-gray-500">
                        Betreff: <span class="font-medium text-gray-700" x-text="vorschauBetreff"></span>
                    </div>
                    <iframe x-ref="vorschau" class="h-[36rem] w-full rounded" title="Vorschau"></iframe>
                </div>

                {{-- Testmail --}}
                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-1 text-sm font-medium text-gray-700">Testmail versenden</div>
                    <p class="mb-3 max-w-3xl text-xs text-gray-500">
                        Schickt die Vorlage mit den aktuell eingegebenen Texten und den oben gewählten
                        Benutzerdaten an eine beliebige Adresse. Der Link bleibt ein Beispiel — es wird kein
                        echter Zugang verschickt.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="email" x-model="testEmail" placeholder="empfaenger@example.org"
                               class="w-64 rounded-lg border-gray-300 text-sm">
                        <button type="button" @click="testSenden" :disabled="testLaeuft"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-600 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50">
                            <i class='bx bx-mail-send'></i>
                            <span x-text="testLaeuft ? 'Sende…' : 'Testmail senden'"></span>
                        </button>
                        <span class="text-sm" :class="testOk ? 'text-emerald-600' : 'text-red-600'" x-text="testMeldung"></span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save'></i> Speichern
                </button>

                @if ($gespeichert)
                    <button type="submit" form="reset-form"
                            onclick="return confirm('Diese Vorlage auf den mitgelieferten Standard zurücksetzen?');"
                            class="text-sm text-gray-500 hover:text-red-600">Auf Standard zurücksetzen</button>
                @endif
            </div>
        </form>

        @if ($gespeichert)
            <form id="reset-form" method="POST" action="{{ route('admin.mailvorlagen.reset', $definition->schluessel) }}" class="hidden">
                @csrf
            </form>
        @endif
    </div>

    {{-- Bewusst echtes CSS statt Tailwind-Klassen: die Regeln greifen auf
         Elemente, die erst der Benutzer in das Feld schreibt. --}}
    <style>
        .mail-wysiwyg img,
        .mail-wysiwyg table { max-width: 100%; }
        .mail-wysiwyg img { height: auto; }
    </style>

    <script>
        function mailEditor(config) {
            return {
                reiter: 'html',
                // Der Rahmen hat kein Formatier-Feld (siehe Kommentar in der View),
                // dort ist der Quelltext-Modus der einzige.
                htmlModus: config.istRahmen,
                html: @js($htmlWert),
                text: @js($textWert),
                // Betreff als Zustand, NICHT per DOM-Zugriff lesen: Alpines
                // $root ist in einem setTimeout-Callback nicht verfügbar – der
                // frühere `this.$root.querySelector(...)` warf dort still einen
                // Fehler, und die Vorschau blieb auf dem alten Stand stehen.
                betreffWert: @js((string) ($betreffWert ?? '')),
                vorschauBetreff: '',
                // Die Platzhalter-Werte dieser Vorlage (Vorbelegung: Beispiele).
                werte: @js($beispielwerte),
                // Benutzer-Suche (füllt name/email aus einem echten Konto).
                suche: '',
                treffer: [],
                benutzer: @js($benutzer->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'email' => $b->email])->values()),
                testEmail: '',
                testLaeuft: false,
                testOk: false,
                testMeldung: '',
                _timer: null,
                _lauf: 0,
                // Elementbezüge, EINMAL beim Start gemerkt. Alpines $refs/$root
                // sind nur im Auswertungs-Kontext von Alpine verfügbar – in einem
                // setTimeout- oder await-Callback sind sie undefined und ein
                // Zugriff wirft still einen Fehler (die Vorschau blieb dann stehen).
                _wysiwyg: null,
                _vorschau: null,

                init() {
                    this._wysiwyg = this.$refs.wysiwyg ?? null;
                    this._vorschau = this.$refs.vorschau;

                    // Zieladresse aus ?testmail vorbefüllen (Aufruf aus einer
                    // Benachrichtigungs-Route: dann steht der Empfänger schon fest).
                    const ziel = new URLSearchParams(location.search).get('testmail');
                    if (ziel) { this.testEmail = ziel; this.reiter = 'vorschau'; }

                    if (this._wysiwyg) {
                        // WYSIWYG mit dem gespeicherten HTML füllen …
                        this._wysiwyg.innerHTML = this.html;
                        // … und beim Wechsel in die Ansicht neu synchronisieren.
                        this.$watch('htmlModus', (an) => {
                            if (! an) this._wysiwyg.innerHTML = this.html;
                        });
                    }
                    this.nachVorschau();
                },

                format(befehl) {
                    document.execCommand(befehl, false, null);
                    this.ausWysiwyg();
                },

                linkSetzen() {
                    const url = prompt('Link-Adresse:');
                    if (url) { document.execCommand('createLink', false, url); this.ausWysiwyg(); }
                },

                ausWysiwyg() {
                    this.html = this._wysiwyg.innerHTML;
                    this.nachVorschau();
                },

                platzhalterKopieren(text) {
                    // An die Cursorposition – aber nur, wenn der Cursor auch
                    // wirklich im Formatier-Feld steht. Sonst (Quelltext-Modus,
                    // anderer Reiter, gar kein Fokus) in die Zwischenablage,
                    // weil `insertText` sonst ins Leere liefe.
                    if (this._wysiwyg && ! this.htmlModus && document.activeElement === this._wysiwyg) {
                        document.execCommand('insertText', false, text);
                        this.ausWysiwyg();
                        return;
                    }

                    navigator.clipboard?.writeText(text);
                },

                nachVorschau() {
                    clearTimeout(this._timer);
                    this._timer = setTimeout(() => this.vorschauLaden(), 350);
                },

                async vorschauLaden() {
                    // Laufende Nummer je Anfrage: Beim Tippen (oder direkt nach
                    // dem Laden) sind mehrere unterwegs. Ohne diese Prüfung kann
                    // eine ÄLTERE Antwort zuletzt eintreffen und die neuere
                    // überschreiben – die Vorschau zeigt dann veraltete Werte.
                    const lauf = ++this._lauf;

                    try {
                        const res = await fetch(config.vorschauUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                            body: JSON.stringify({ betreff: this.betreffWert, html: this.html, text: this.text, werte: this.werte }),
                        });
                        const daten = await res.json();

                        if (lauf !== this._lauf) return; // überholt – verwerfen

                        this.vorschauBetreff = daten.betreff;
                        this._vorschau.srcdoc = daten.html;
                    } catch (e) { /* Vorschau ist unkritisch */ }
                },


                suchen() {
                    const q = this.suche.trim().toLowerCase();
                    if (q.length < 2) { this.treffer = []; return; }
                    this.treffer = this.benutzer
                        .filter(b => (b.name + ' ' + b.email).toLowerCase().includes(q))
                        .slice(0, 8);
                },

                benutzerUebernehmen(b) {
                    // Nur Platzhalter füllen, die diese Vorlage auch kennt.
                    if ('name' in this.werte) this.werte.name = b.name;
                    if ('email' in this.werte) this.werte.email = b.email;
                    this.suche = b.name;
                    this.treffer = [];
                    this.nachVorschau();
                },

                async testSenden() {
                    if (! this.testEmail) { this.testOk = false; this.testMeldung = 'Bitte eine Adresse eingeben.'; return; }
                    this.testLaeuft = true;
                    this.testMeldung = '';
                    try {
                        const res = await fetch(config.testmailUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                            body: JSON.stringify({ an: this.testEmail, werte: this.werte, betreff: this.betreffWert, html: this.html, text: this.text }),
                        });
                        const daten = await res.json();
                        this.testOk = daten.ok;
                        this.testMeldung = daten.meldung;
                    } catch (e) {
                        this.testOk = false;
                        this.testMeldung = 'Senden fehlgeschlagen.';
                    } finally {
                        this.testLaeuft = false;
                    }
                },

                vorSpeichern() {
                    // Sicherstellen, dass das versteckte html-Feld aktuell ist.
                    if (this._wysiwyg && ! this.htmlModus) this.html = this._wysiwyg.innerHTML;
                },
            };
        }
    </script>
</x-app-layout>
