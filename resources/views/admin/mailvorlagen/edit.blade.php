<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mailvorlage: {{ $definition->titel }}</h1>
    </x-slot>

    @php
        $htmlWert = old('html', $gespeichert->html ?? $definition->html);
        $textWert = old('text', $gespeichert->text ?? $definition->text);
        $betreffWert = old('betreff', $gespeichert->betreff ?? $definition->betreff);
    @endphp

    <div class="max-w-5xl"
         x-data="mailEditor({
             vorschauUrl: '{{ route('admin.mailvorlagen.vorschau', $definition->schluessel) }}',
             testmailUrl: '{{ route('admin.mailvorlagen.testmail', $definition->schluessel) }}',
             csrf: '{{ csrf_token() }}'
         })">
        <div class="mb-4">
            <a href="{{ route('admin.mailvorlagen.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; alle Vorlagen</a>
        </div>

        <p class="mb-4 text-gray-600">{{ $definition->beschreibung }}</p>

        {{-- Platzhalter-Hilfe --}}
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

        <form method="POST" action="{{ route('admin.mailvorlagen.update', $definition->schluessel) }}"
              @submit="vorSpeichern">
            @csrf
            @method('PUT')

            @if ($definition->schluessel !== \App\Mail\Vorlagen\VorlagenDefinition::RAHMEN)
                <label class="mb-1 block text-sm font-medium text-gray-700">Betreff</label>
                <input type="text" name="betreff" x-model="betreffWert" @input="nachVorschau"
                       class="mb-6 block w-full rounded-lg border-gray-300 text-sm">
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- HTML-Fassung: WYSIWYG + Umschalter --}}
                <div>
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

                    {{-- WYSIWYG --}}
                    <div x-show="! htmlModus" x-ref="wysiwyg" contenteditable="true"
                         @input="ausWysiwyg"
                         class="min-h-[16rem] rounded-b-lg border border-gray-300 bg-white p-3 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"></div>

                    {{-- HTML-Quelltext --}}
                    <textarea x-show="htmlModus" x-model="html" @input="nachVorschau"
                              class="block h-72 w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>

                    <input type="hidden" name="html" :value="html">
                </div>

                {{-- Text-Fassung --}}
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Reiner Text (ohne Formatierung)</label>
                    <textarea name="text" x-model="text" @input="nachVorschau"
                              class="block h-[19.5rem] w-full rounded-lg border-gray-300 font-mono text-xs">{{ $textWert }}</textarea>
                </div>
            </div>

            {{-- Vorschau-Werte: genau die Platzhalter DIESER Vorlage --}}
            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="mb-1 text-sm font-medium text-gray-700">Werte für Vorschau &amp; Testmail</div>
                <p class="mb-3 text-xs text-gray-500">
                    Nur zum Ansehen und Testen – gespeichert wird davon nichts.
                </p>

                @php($hatName = array_key_exists('name', $definition->platzhalter))

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

                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($definition->platzhalter as $name => $erklaerung)
                        <div @class(['md:col-span-2' => $name === 'text' || $name === 'inhalt'])>
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
            <div class="mt-4">
                <div class="mb-2 text-sm font-medium text-gray-700">Vorschau</div>
                <div class="rounded-xl border border-gray-200 bg-white p-2">
                    <div class="mb-2 border-b border-gray-100 px-2 py-1 text-sm text-gray-500">
                        Betreff: <span class="font-medium text-gray-700" x-text="vorschauBetreff"></span>
                    </div>
                    <iframe x-ref="vorschau" class="h-96 w-full rounded" title="Vorschau"></iframe>
                </div>
            </div>

            {{-- Testmail --}}
            <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="mb-1 text-sm font-medium text-gray-700">Testmail versenden</div>
                <p class="mb-3 text-xs text-gray-500">
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

            <div class="mt-6 flex items-center gap-3">
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

    <script>
        function mailEditor(config) {
            return {
                htmlModus: false,
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
                    this._wysiwyg = this.$refs.wysiwyg;
                    this._vorschau = this.$refs.vorschau;

                    // Zieladresse aus ?testmail vorbefüllen (Aufruf aus einer
                    // Benachrichtigungs-Route: dann steht der Empfänger schon fest).
                    const ziel = new URLSearchParams(location.search).get('testmail');
                    if (ziel) this.testEmail = ziel;

                    // WYSIWYG mit dem gespeicherten HTML füllen …
                    this._wysiwyg.innerHTML = this.html;
                    // … und beim Wechsel in die Ansicht neu synchronisieren.
                    this.$watch('htmlModus', (an) => {
                        if (! an) this._wysiwyg.innerHTML = this.html;
                    });
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
                    // In den WYSIWYG an der Cursorposition, sonst ans Textende.
                    if (! this.htmlModus) {
                        document.execCommand('insertText', false, text);
                        this.ausWysiwyg();
                    } else {
                        navigator.clipboard?.writeText(text);
                    }
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
                    if (! this.htmlModus) this.html = this._wysiwyg.innerHTML;
                },
            };
        }
    </script>
</x-app-layout>
