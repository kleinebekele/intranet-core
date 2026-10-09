{{--
    Der „?"-Knopf in der Kopfzeile. Er erscheint nur, wenn ein Modul (das Wiki)
    zur aktuellen Route eine Hilfeseite anbietet – ohne Anbieter rendert die
    Komponente nichts. Siehe App\Support\Hilfe.

    Ein Klick öffnet die Hilfe in einem Fenster über der Seite: Der Knopf lädt die
    Adresse mit der Kopfzeile `X-Hilfe-Modal: 1`, der Anbieter liefert dann nur den
    Text ohne Seitenrahmen. Klappt das nicht (Fehler, kein JavaScript), führt der
    Link ganz normal auf die Hilfeseite.
--}}
@php($hilfeUrl = \App\Support\Hilfe::url())

@if ($hilfeUrl)
    <div x-data="{
            offen: false,
            laden: false,
            inhalt: '',
            async oeffnen(url) {
                this.offen = true;
                if (this.inhalt) return;
                this.laden = true;
                try {
                    const antwort = await fetch(url, { headers: { 'X-Hilfe-Modal': '1', Accept: 'text/html' } });
                    if (! antwort.ok) throw new Error();
                    this.inhalt = await antwort.text();
                } catch (e) {
                    window.location.href = url;
                }
                this.laden = false;
            },
         }"
         @keydown.escape.window="offen = false">
        <a href="{{ $hilfeUrl }}" @click.prevent="oeffnen(@js($hilfeUrl))"
           title="Hilfe zu dieser Seite"
           class="inline-flex items-center justify-center h-9 w-9 rounded-md text-gray-500 hover:bg-gray-100 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <x-module-icon name="help" class="text-xl" />
            <span class="sr-only">Hilfe zu dieser Seite</span>
        </a>

        <template x-teleport="body">
            <div x-show="offen" x-cloak
                 class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:p-8"
                 @click.self="offen = false">
                <div class="relative w-full rounded-xl bg-white shadow-xl" role="dialog" aria-label="Hilfe zu dieser Seite">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <x-module-icon name="help" class="text-lg text-indigo-500" />
                            Hilfe
                        </span>
                        <button type="button" @click="offen = false" title="Schließen"
                                class="rounded-md p-1 text-xl text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                    <div class="px-5 py-4">
                        <p x-show="laden" class="text-sm text-gray-500">Lädt …</p>
                        <div x-html="inhalt"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
