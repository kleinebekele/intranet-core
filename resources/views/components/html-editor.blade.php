{{--
    HTML-Editor: Formatier-Ansicht (contenteditable mit Werkzeugleiste) und
    Quelltext umschaltbar. Abgeschickt wird immer das HTML im versteckten Feld.

    <x-html-editor name="inhalt" :wert="$seite->inhalt" />

    Bewusst ohne fremde Bibliothek; die Befehle sind die des Browsers
    (execCommand). Was die Leiste nicht kann, geht im Quelltext.
--}}
@props(['name', 'wert' => '', 'hoehe' => 'min-h-[20rem]'])

<div x-data="{
        quelltext: false,
        html: @js((string) $wert),
        _feld: null,
        init() {
            this._feld = this.$refs.feld;
            this._feld.innerHTML = this.html;
            this.$watch('quelltext', (an) => { if (! an) this._feld.innerHTML = this.html; });
        },
        befehl(name, wert = null) {
            this._feld.focus();
            document.execCommand(name, false, wert);
            this.uebernehmen();
        },
        link() {
            const url = prompt('Link-Adresse (https://… oder /seite/…):');
            if (url) this.befehl('createLink', url);
        },
        uebernehmen() { this.html = this._feld.innerHTML; },
     }" class="html-editor">
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-lg border border-b-0 border-gray-300 bg-gray-50 p-1">
        <div class="flex flex-wrap gap-1" x-show="! quelltext">
            <button type="button" @click="befehl('formatBlock', 'h2')" class="rounded px-2 py-1 text-sm font-semibold hover:bg-gray-200" title="Überschrift">H2</button>
            <button type="button" @click="befehl('formatBlock', 'h3')" class="rounded px-2 py-1 text-sm font-semibold hover:bg-gray-200" title="Zwischenüberschrift">H3</button>
            <button type="button" @click="befehl('formatBlock', 'p')" class="rounded px-2 py-1 text-sm hover:bg-gray-200" title="Absatz">¶</button>
            <span class="mx-1 w-px bg-gray-300"></span>
            <button type="button" @click="befehl('bold')" class="rounded px-2 py-1 text-sm font-bold hover:bg-gray-200" title="Fett">B</button>
            <button type="button" @click="befehl('italic')" class="rounded px-2 py-1 text-sm italic hover:bg-gray-200" title="Kursiv">I</button>
            <button type="button" @click="befehl('underline')" class="rounded px-2 py-1 text-sm underline hover:bg-gray-200" title="Unterstrichen">U</button>
            <span class="mx-1 w-px bg-gray-300"></span>
            <button type="button" @click="befehl('insertUnorderedList')" class="rounded px-2 py-1 text-sm hover:bg-gray-200" title="Aufzählung">• Liste</button>
            <button type="button" @click="befehl('insertOrderedList')" class="rounded px-2 py-1 text-sm hover:bg-gray-200" title="Nummerierte Liste">1. Liste</button>
            <button type="button" @click="link()" class="rounded px-2 py-1 text-sm hover:bg-gray-200" title="Link setzen">🔗 Link</button>
            <button type="button" @click="befehl('removeFormat')" class="rounded px-2 py-1 text-sm hover:bg-gray-200" title="Formatierung entfernen">⌫ Format</button>
        </div>
        <button type="button" @click="quelltext = ! quelltext" class="ml-auto rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                x-text="quelltext ? 'Zurück zur Ansicht' : 'HTML-Quelltext'"></button>
    </div>

    <div x-show="! quelltext" x-ref="feld" contenteditable="true" @input="uebernehmen()"
         class="seiten-inhalt {{ $hoehe }} block w-full overflow-auto break-words rounded-b-lg border border-gray-300 bg-white p-4 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"></div>

    <textarea x-show="quelltext" x-cloak x-model="html" spellcheck="false"
              class="{{ $hoehe }} block w-full rounded-b-lg border-gray-300 font-mono text-xs"></textarea>

    <input type="hidden" name="{{ $name }}" :value="html">
</div>

@once
    @include('seiten.partials.inhalt-stil')
@endonce
