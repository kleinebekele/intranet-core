<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ekkon · Teams-Chat</h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-8"
             x-data="{
                offen: null,
                antwortAn: null,
                geloescht: [],
                loeschen(id, url) {
                    fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    }).then(r => { if (r.ok) { this.geloescht.push(id); } else { (window.hinweis ?? alert)('Löschen fehlgeschlagen (HTTP ' + r.status + ').'); } })
                      .catch(() => (window.hinweis ?? alert)('Löschen fehlgeschlagen.'));
                }
             }">

            @if ($errors->any())
                <div class="rounded-lg bg-red-100 text-red-800 px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $fehler)
                            <li>{{ $fehler }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Stand ────────────────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="font-semibold text-gray-700 mb-1">Eingang</h3>
                <p class="text-sm text-gray-500">
                    Nachrichten, die andere dem verbundenen Microsoft-Konto in Teams schreiben. Abgeholt vom
                    Lauscher <code>php artisan teams:lauschen</code> (Dauerdienst, alle paar Sekunden). Was mit
                    einer Nachricht passiert, entscheidet die Verarbeitung dahinter; solange es keine gibt,
                    lässt sich hier von Hand antworten.
                </p>
                <dl class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Konto</dt>
                        <dd>{{ $konto ? $konto->name.' ('.$konto->email.')' : '– nicht verbunden (Benachrichtigungen → Teams-Channels)' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Letzte Bewegung</dt>
                        <dd>{{ $letzterBlick?->format('d.m.Y H:i:s') ?? '– noch nie' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Chats im Blick</dt>
                        <dd>{{ $chats->count() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ── KI ───────────────────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6"
                 x-data="{
                    modelle: [],
                    listen: { spezialanwendungen: null, ordner: null },
                    listenFehler: '',
                    listeLaedt: '',
                    async listeLaden(was) {
                        this.listeLaedt = was; this.listenFehler = '';
                        const urls = { spezialanwendungen: {{ \Illuminate\Support\Js::from(route('module.ekkon.teams.ki.spezialanwendungen')) }}, ordner: {{ \Illuminate\Support\Js::from(route('module.ekkon.teams.ki.ordner')) }} };
                        try {
                            const r = await fetch(urls[was], { headers: { 'Accept': 'application/json' } });
                            const j = await r.json().catch(() => ({}));
                            if (! r.ok) { throw new Error(j.fehler || ('HTTP ' + r.status)); }
                            this.listen[was] = j[was] || [];
                        } catch (e) { this.listenFehler = e.message; }
                        this.listeLaedt = '';
                    },
                    modellFehler: '',
                    laedt: false,
                    async modelleLaden() {
                        this.laedt = true; this.modellFehler = '';
                        try {
                            const r = await fetch({{ \Illuminate\Support\Js::from(route('module.ekkon.teams.ki.modelle')) }}, { headers: { 'Accept': 'application/json' } });
                            const j = await r.json().catch(() => ({}));
                            if (! r.ok) { throw new Error(j.fehler || ('HTTP ' + r.status)); }
                            this.modelle = j.modelle || [];
                            if (this.modelle.length === 0) { this.modellFehler = 'Der Anbieter liefert keine Modelle.'; }
                        } catch (e) { this.modellFehler = e.message; }
                        this.laedt = false;
                    }
                 }">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
                    <h3 class="font-semibold text-gray-700">KI-Antworten (DeutschlandGPT)</h3>
                    @if ($ki['aktiv'] && $ki['schluessel_da'] && $ki['modell'] !== '')
                        <span class="text-xs font-semibold text-green-700 bg-green-100 rounded px-2 py-0.5">antwortet · {{ $ki['modell'] }}</span>
                    @elseif ($ki['aktiv'])
                        <span class="text-xs font-semibold text-red-700 bg-red-100 rounded px-2 py-0.5">eingeschaltet, aber unvollständig</span>
                    @else
                        <span class="text-xs font-semibold text-gray-500 bg-gray-100 rounded px-2 py-0.5">aus</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Jede eingehende Nachricht geht mit dem Gesprächsverlauf des Chats an die KI, die Antwort postet
                    der Bot zurück. OpenAI-kompatible Schnittstelle: Schlüssel aus dem DeutschlandGPT-Dashboard
                    (Plattform-API), Modell aus der Liste des Anbieters.
                </p>

                <form method="POST" action="{{ route('module.ekkon.teams.ki.speichern') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">API-Adresse</label>
                        <input name="url" value="{{ old('url', $ki['url']) }}" required class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            API-Schlüssel
                            @if ($ki['schluessel_da'])
                                <span class="text-green-700">– hinterlegt, wird nicht angezeigt</span> <span class="text-gray-400">(leer lassen = behalten)</span>
                            @else
                                <span class="text-red-700">– fehlt</span>
                            @endif
                        </label>
                        <input name="schluessel" value="" type="password" autocomplete="off" class="w-full rounded-md border-gray-300 text-sm"
                               placeholder="{{ $ki['schluessel_da'] ? '••••••••••••  (hinterlegt)' : 'dgpt_…' }}">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Modell</label>
                        <div class="flex gap-2">
                            <input name="modell" list="ki-modelle" value="{{ old('modell', $ki['modell']) }}" class="w-full rounded-md border-gray-300 text-sm font-mono" placeholder="z. B. claude-sonnet-… oder gpt-…">
                            <datalist id="ki-modelle">
                                <template x-for="m in modelle" :key="m"><option :value="m"></option></template>
                            </datalist>
                            <button type="button" @click="modelleLaden()" class="whitespace-nowrap rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                    :disabled="laedt" x-text="laedt ? 'lädt …' : 'Modelle laden'"></button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" x-show="modelle.length > 0">Liste geladen (<span x-text="modelle.length"></span>) – ins Feld klicken zeigt die Auswahl.</p>
                        <p class="text-xs text-red-700 mt-1" x-show="modellFehler" x-text="modellFehler"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Spezialanwendung <span class="text-gray-400">(übernimmt deren Anweisungen und Modell; leer = eigener Systemprompt)</span>
                        </label>
                        <div class="flex gap-2">
                            <select name="spezialanwendung" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">– keine –</option>
                                @if ($ki['spezialanwendung'] !== '')
                                    <option value="{{ $ki['spezialanwendung'] }}" selected x-show="! listen.spezialanwendungen">{{ $ki['spezialanwendung'] }} (gespeichert)</option>
                                @endif
                                <template x-for="s in (listen.spezialanwendungen || [])" :key="s.id">
                                    <option :value="s.id" :selected="s.id === {{ \Illuminate\Support\Js::from($ki['spezialanwendung']) }}" x-text="s.name + (s.beschreibung ? ' – ' + s.beschreibung.slice(0, 60) : '')"></option>
                                </template>
                            </select>
                            <button type="button" @click="listeLaden('spezialanwendungen')" :disabled="listeLaedt !== ''"
                                    class="whitespace-nowrap rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                    x-text="listeLaedt === 'spezialanwendungen' ? 'lädt …' : 'Liste laden'"></button>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Dokumentenordner als Wissen <span class="text-gray-400">(wird vor jeder Antwort durchsucht; leer = kein Kontextwissen)</span>
                        </label>
                        <div class="flex gap-2">
                            <select name="ordner" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">– keiner –</option>
                                @if ($ki['ordner'] !== '')
                                    <option value="{{ $ki['ordner'] }}" selected x-show="! listen.ordner">{{ $ki['ordner'] }} (gespeichert)</option>
                                @endif
                                <template x-for="o in (listen.ordner || [])" :key="o.id">
                                    <option :value="o.id" :selected="o.id === {{ \Illuminate\Support\Js::from($ki['ordner']) }}" x-text="o.name + ' (' + o.dateien + ' Dateien)'"></option>
                                </template>
                            </select>
                            <button type="button" @click="listeLaden('ordner')" :disabled="listeLaedt !== ''"
                                    class="whitespace-nowrap rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                    x-text="listeLaedt === 'ordner' ? 'lädt …' : 'Liste laden'"></button>
                        </div>
                        <p class="text-xs text-red-700 mt-1" x-show="listenFehler" x-text="listenFehler"></p>
                        <p class="text-xs text-gray-500 mt-1">
                            Hinweis: Die Chat-API kann eine Spezialanwendung nicht direkt befragen. Das Intranet übernimmt deshalb ihre
                            Anweisungen und ihr Modell; ihr Kontextwissen bildest du über einen Dokumentenordner mit denselben Dateien nach.
                            Beides muss im DeutschlandGPT-Dashboard für diesen API-Schlüssel freigegeben sein.
                        </p>
                    </div>
                    <div class="md:col-span-2 flex flex-col gap-2 text-sm pb-2">
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="aktiv" value="0">
                            <input type="checkbox" name="aktiv" value="1" class="rounded border-gray-300" @checked(old('aktiv', $ki['aktiv']))>
                            KI antwortet automatisch
                        </label>
                        <label class="inline-flex items-center gap-2" title="Module wie das Wiki melden Wissensquellen an; was die KI daraus bekommt, richtet sich nach den Rollen der fragenden Person (Zuordnung über ihr Microsoft-Konto)">
                            <input type="hidden" name="wissensquellen" value="0">
                            <input type="checkbox" name="wissensquellen" value="1" class="rounded border-gray-300" @checked(old('wissensquellen', $ki['wissensquellen'])) @disabled($ki['wissensquellen_namen'] === [])>
                            Intranet-Wissen mitgeben
                            @if ($ki['wissensquellen_namen'] !== [])
                                <span class="text-xs text-gray-500">({{ implode(', ', $ki['wissensquellen_namen']) }})</span>
                            @else
                                <span class="text-xs text-gray-400">(kein Modul stellt Wissen bereit)</span>
                            @endif
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="gruppen_nur_erwaehnt" value="0">
                            <input type="checkbox" name="gruppen_nur_erwaehnt" value="1" class="rounded border-gray-300" @checked(old('gruppen_nur_erwaehnt', $ki['gruppen_nur_erwaehnt']))>
                            in Gruppen- und Besprechungschats nur bei @-Erwähnung des Bots
                        </label>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Systemprompt <span class="text-gray-400">(Rolle und Regeln für die KI)</span></label>
                        <textarea name="system" rows="4" class="w-full rounded-md border-gray-300 text-sm">{{ old('system', $ki['system']) }}</textarea>
                    </div>
                    <div class="md:col-span-4">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Speichern</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('module.ekkon.teams.ki.test') }}" class="mt-4 flex flex-wrap gap-2 items-center border-t pt-4">
                    @csrf
                    <input name="frage" value="Antworte mit einem Satz: Funktioniert die Verbindung?" class="flex-1 min-w-64 rounded-md border-gray-300 text-sm">
                    <button class="rounded-md border border-indigo-600 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Probefrage an die KI</button>
                    <span class="text-xs text-gray-500">postet nichts nach Teams, zeigt nur die Antwort hier</span>
                </form>
            </div>

            {{-- ── Nachrichten ─────────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="font-semibold text-gray-700 mb-1">Nachrichten <span class="text-gray-400 font-normal text-sm">(letzte 100)</span></h3>
                <p class="text-sm text-gray-500 mb-4">Klick auf die Zeile zeigt die ganze Nachricht.</p>

                @if ($nachrichten->isEmpty())
                    <p class="text-sm text-gray-500 italic">Noch nichts angekommen.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 border-b">
                                <tr>
                                    <th class="py-2 pr-4">Gesendet</th>
                                    <th class="py-2 pr-4">Chat</th>
                                    <th class="py-2 pr-4">Von</th>
                                    <th class="py-2 pr-4">Nachricht</th>
                                    <th class="py-2 pr-4">Verarbeitung</th>
                                    <th class="py-2 pr-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nachrichten as $n)
                                    <tr x-show="! geloescht.includes({{ $n->id }})" class="border-b hover:bg-gray-50 cursor-pointer"
                                        @click="offen = (offen === {{ $n->id }} ? null : {{ $n->id }})">
                                        <td class="py-2 pr-4 whitespace-nowrap text-gray-600">{{ $n->gesendet_am?->format('d.m.Y H:i:s') }}</td>
                                        <td class="py-2 pr-4">
                                            {{ $n->chat_titel }}
                                            <span class="block text-xs text-gray-400">{{ $n->chat_typ }}</span>
                                        </td>
                                        <td class="py-2 pr-4">{{ $n->von_name }}</td>
                                        <td class="py-2 pr-4 text-gray-700 max-w-md truncate">{{ mb_substr((string) $n->text, 0, 120) }}</td>
                                        <td class="py-2 pr-4 text-xs whitespace-nowrap">
                                            @if ($n->verarbeitet_am)
                                                <span class="text-gray-600" title="{{ $n->verarbeitet_am->format('d.m.Y H:i:s') }}">{{ $n->verarbeitung ?: 'verarbeitet' }}</span>
                                            @elseif (str_starts_with((string) $n->verarbeitung, 'Fehler'))
                                                <span class="text-red-700" title="{{ $n->verarbeitung }}">Fehler</span>
                                            @else
                                                <span class="text-yellow-700 bg-yellow-100 rounded px-2 py-0.5 font-semibold">offen</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4">
                                            <div class="flex flex-wrap gap-2 whitespace-nowrap">
                                                <button type="button" class="text-indigo-700 hover:underline"
                                                        @click.stop="antwortAn = (antwortAn === {{ $n->id }} ? null : {{ $n->id }}); offen = {{ $n->id }}">antworten</button>
                                                <button type="button" class="text-red-700 hover:underline"
                                                        @click.stop="loeschen({{ $n->id }}, '{{ route('module.ekkon.teams.destroy', $n) }}')">löschen</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="offen === {{ $n->id }} && ! geloescht.includes({{ $n->id }})" x-cloak class="border-b bg-gray-50">
                                        <td colspan="6" class="py-3 pr-4 pl-4">
                                            <div class="text-xs text-gray-500 mb-1">Nachricht</div>
                                            <pre class="whitespace-pre-wrap font-sans text-sm rounded bg-white border p-3">{{ $n->text }}</pre>
                                            @if (($n->anhaenge ?? []) !== [])
                                                <div class="text-xs text-gray-500 mt-2 mb-1">Anhänge</div>
                                                <ul class="text-sm list-disc list-inside">
                                                    @foreach ($n->anhaenge as $a)
                                                        <li>@if ($a['url'] ?? '')<a href="{{ $a['url'] }}" target="_blank" rel="noopener" class="text-indigo-700 hover:underline">{{ $a['name'] ?: $a['url'] }}</a>@else{{ $a['name'] ?: $a['typ'] }}@endif</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if ($n->antwort)
                                                <div class="text-xs text-gray-500 mt-2 mb-1">Antwort</div>
                                                <pre class="whitespace-pre-wrap font-sans text-sm rounded bg-white border p-3">{{ $n->antwort }}</pre>
                                            @endif
                                            <form x-show="antwortAn === {{ $n->id }}" x-cloak method="POST" action="{{ route('module.ekkon.teams.antworten', $n) }}" class="mt-3 space-y-2" @click.stop>
                                                @csrf
                                                <textarea name="antwort" rows="3" required class="w-full rounded-md border-gray-300 text-sm" placeholder="Antwort in diesen Chat …">{{ old('antwort') }}</textarea>
                                                <div class="flex gap-3 items-center">
                                                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Antwort senden</button>
                                                    <button type="button" @click="antwortAn = null" class="text-sm text-gray-600 hover:underline">abbrechen</button>
                                                </div>
                                            </form>
                                            <div class="text-xs text-gray-400 mt-2 font-mono">{{ $n->chat_id }} · {{ $n->nachricht_id }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ── Chats ────────────────────────────────────────────────── --}}
            @if ($chats->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                    <h3 class="font-semibold text-gray-700 mb-3">Chats im Blick</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr><th class="py-2 pr-4">Chat</th><th class="py-2 pr-4">Gelesen bis</th><th class="py-2 pr-4">Chat-ID</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($chats as $c)
                                <tr class="border-b last:border-0">
                                    <td class="py-1.5 pr-4">{{ $c->titel }}</td>
                                    <td class="py-1.5 pr-4 text-gray-600 whitespace-nowrap">{{ $c->zuletzt_gesehen_am?->format('d.m.Y H:i:s') }}</td>
                                    <td class="py-1.5 pr-4 font-mono text-xs text-gray-500 max-w-md truncate" title="{{ $c->chat_id }}">{{ $c->chat_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
