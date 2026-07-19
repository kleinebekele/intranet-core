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
                <input type="text" name="betreff" value="{{ $betreffWert }}"
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

            {{-- Vorschau --}}
            <div class="mt-6">
                <div class="mb-1 text-sm font-medium text-gray-700">Vorschau <span class="text-gray-400">(mit Beispieldaten)</span></div>
                <div class="rounded-xl border border-gray-200 bg-white p-2">
                    <div class="mb-2 border-b border-gray-100 px-2 py-1 text-sm text-gray-500">
                        Betreff: <span class="font-medium text-gray-700" x-text="vorschauBetreff"></span>
                    </div>
                    <iframe x-ref="vorschau" class="h-96 w-full rounded" title="Vorschau"></iframe>
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
                vorschauBetreff: '',
                _timer: null,

                init() {
                    // WYSIWYG mit dem gespeicherten HTML füllen …
                    this.$refs.wysiwyg.innerHTML = this.html;
                    // … und beim Wechsel in die Ansicht neu synchronisieren.
                    this.$watch('htmlModus', (an) => {
                        if (! an) this.$refs.wysiwyg.innerHTML = this.html;
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
                    this.html = this.$refs.wysiwyg.innerHTML;
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
                    try {
                        const res = await fetch(config.vorschauUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                            body: JSON.stringify({ betreff: this.$root.querySelector('[name=betreff]')?.value ?? '', html: this.html, text: this.text }),
                        });
                        const daten = await res.json();
                        this.vorschauBetreff = daten.betreff;
                        this.$refs.vorschau.srcdoc = daten.html;
                    } catch (e) { /* Vorschau ist unkritisch */ }
                },

                vorSpeichern() {
                    // Sicherstellen, dass das versteckte html-Feld aktuell ist.
                    if (! this.htmlModus) this.html = this.$refs.wysiwyg.innerHTML;
                },
            };
        }
    </script>
</x-app-layout>
