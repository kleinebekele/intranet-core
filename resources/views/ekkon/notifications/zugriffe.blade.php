<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ekkon · Zugriffe des Microsoft-Kontos</h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-8"
             x-data="{
                kopiere(text) {
                    navigator.clipboard?.writeText(text).then(() => (window.hinweis ?? alert)('Kopiert: ' + text));
                }
             }">

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">
                        Konto <b>{{ $konto->name }}</b> ({{ $konto->email }}). Alles unten ist das, wohin dieses Konto posten
                        bzw. worauf es Dateien legen kann. „kopieren" holt die ID oder Adresse für die Channel-Maske.
                    </p>
                    <a href="{{ route('module.ekkon.notifications.index') }}#channels" class="text-sm text-indigo-700 hover:underline">← zurück zu den Teams-Channels</a>
                </div>
                @if ($fehler !== [])
                    <ul class="mt-3 rounded-lg bg-red-100 text-red-800 px-4 py-3 text-sm list-disc list-inside space-y-1">
                        @foreach ($fehler as $f)
                            <li>{{ $f }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- ── Chats ────────────────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="font-semibold text-gray-700 mb-1">Chats <span class="text-gray-400 font-normal text-sm">({{ count($chats) }}, neueste zuerst)</span></h3>
                <p class="text-sm text-gray-500 mb-4">Besprechungschats heißen wie der Termin. Die ID kommt ins Feld „Chat-/Kanal-ID" (Weg: Graph → Chat/Kanal).</p>
                @if ($chats === [])
                    <p class="text-sm text-gray-500 italic">Keine Chats gefunden.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 border-b">
                                <tr>
                                    <th class="py-2 pr-4">Titel</th>
                                    <th class="py-2 pr-4">Art</th>
                                    <th class="py-2 pr-4">Mitglieder</th>
                                    <th class="py-2 pr-4">Chat-ID</th>
                                    <th class="py-2 pr-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($chats as $chat)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-4 font-medium">{{ $chat['titel'] }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $chat['typ'] }}</td>
                                        <td class="py-2 pr-4 text-gray-500">{{ $chat['mitglieder'] }}</td>
                                        <td class="py-2 pr-4 font-mono text-xs text-gray-500 max-w-md truncate" title="{{ $chat['id'] }}">{{ $chat['id'] }}</td>
                                        <td class="py-2 pr-4"><button type="button" @click="kopiere({{ \Illuminate\Support\Js::from($chat['id']) }})" class="text-indigo-700 hover:underline text-xs whitespace-nowrap">kopieren</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ── Teams & Kanäle ───────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="font-semibold text-gray-700 mb-1">Teams und Kanäle <span class="text-gray-400 font-normal text-sm">({{ count($teams) }} Teams)</span></h3>
                <p class="text-sm text-gray-500 mb-4">Die Kanal-ID ist schon als „Team-GUID/Kanal-ID" zusammengesetzt, so wie die Maske sie braucht.</p>
                @if ($teams === [])
                    <p class="text-sm text-gray-500 italic">Keine Teams gefunden.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($teams as $team)
                            <div>
                                <div class="font-medium text-gray-800">{{ $team['name'] }}</div>
                                <table class="min-w-full text-sm mt-1">
                                    <tbody>
                                        @foreach ($team['kanaele'] as $kanal)
                                            <tr class="border-b last:border-0">
                                                <td class="py-1.5 pr-4 pl-4">{{ $kanal['name'] }} <span class="text-xs text-gray-400">{{ $kanal['typ'] }}</span></td>
                                                <td class="py-1.5 pr-4 font-mono text-xs text-gray-500 max-w-md truncate" title="{{ $kanal['id'] }}">{{ $kanal['id'] }}</td>
                                                <td class="py-1.5 pr-4"><button type="button" @click="kopiere({{ \Illuminate\Support\Js::from($kanal['id']) }})" class="text-indigo-700 hover:underline text-xs whitespace-nowrap">kopieren</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ── SharePoint ───────────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <h3 class="font-semibold text-gray-700 mb-1">SharePoint-Sites <span class="text-gray-400 font-normal text-sm">({{ count($sites) }})</span></h3>
                <p class="text-sm text-gray-500 mb-4">Die Adresse einer Bibliothek ist die „Ablage-URL" für Anhänge; ein Unterordner darf hinten angehängt werden.</p>
                @if ($sites === [])
                    <p class="text-sm text-gray-500 italic">Keine Sites gefunden.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($sites as $site)
                            <div>
                                <div class="font-medium text-gray-800">{{ $site['name'] }} <a href="{{ $site['url'] }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:underline font-normal">{{ $site['url'] }}</a></div>
                                <table class="min-w-full text-sm mt-1">
                                        @forelse ($site['bibliotheken'] as $bib)
                                            {{-- Eine Bibliothek = ein aufklappbarer Baum. Flache Zeilenliste mit
                                                 Tiefe statt echter Rekursion – Alpine kann Templates nicht
                                                 verschachtelt wiederholen; so bleibt es eine x-for-Schleife. --}}
                                            <tbody x-data="ordnerBaum({{ \Illuminate\Support\Js::from($bib['id']) }})">
                                                <tr class="border-b">
                                                    <td class="py-1.5 pr-4 pl-4">
                                                        <button type="button" @click="umschalten(null)" class="text-gray-500 hover:text-gray-800 mr-1 w-4 inline-block" :title="wurzelOffen ? 'zuklappen' : 'Unterordner laden'" x-text="wurzelOffen ? '▾' : '▸'"></button>
                                                        {{ $bib['name'] }}
                                                        <span x-show="laedt === ''" class="text-xs text-gray-400">lädt …</span>
                                                    </td>
                                                    <td class="py-1.5 pr-4 font-mono text-xs text-gray-500 max-w-lg truncate" title="{{ $bib['url'] }}">{{ $bib['url'] }}</td>
                                                    <td class="py-1.5 pr-4"><button type="button" @click="kopiere({{ \Illuminate\Support\Js::from($bib['url']) }})" class="text-indigo-700 hover:underline text-xs whitespace-nowrap">kopieren</button></td>
                                                </tr>
                                                <template x-for="z in zeilen" :key="z.pfad">
                                                    <tr class="border-b">
                                                        <td class="py-1.5 pr-4" :style="'padding-left:' + (1 + z.tiefe * 1.25) + 'rem'">
                                                            <button type="button" @click="umschalten(z)" class="text-gray-500 hover:text-gray-800 mr-1 w-4 inline-block" x-text="z.offen ? '▾' : '▸'"></button>
                                                            <span x-text="z.name"></span>
                                                            <span x-show="laedt === z.pfad" class="text-xs text-gray-400">lädt …</span>
                                                            <span x-show="z.leer" class="text-xs text-gray-400">(keine Unterordner)</span>
                                                        </td>
                                                        <td class="py-1.5 pr-4 font-mono text-xs text-gray-500 max-w-lg truncate" :title="z.url" x-text="z.url"></td>
                                                        <td class="py-1.5 pr-4"><button type="button" @click="kopiere(z.url)" class="text-indigo-700 hover:underline text-xs whitespace-nowrap">kopieren</button></td>
                                                    </tr>
                                                </template>
                                                <tr x-show="fehler" x-cloak><td colspan="3" class="py-1.5 pl-4 text-xs text-red-700" x-text="fehler"></td></tr>
                                            </tbody>
                                        @empty
                                            <tbody><tr><td class="py-1.5 pl-4 text-xs text-gray-400 italic">Bibliotheken nicht lesbar</td></tr></tbody>
                                        @endforelse
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Ordnerbaum einer Bibliothek: flache Zeilenliste mit Tiefe. Aufklappen
        // holt die Unterordner per Fetch und fügt sie hinter der Zeile ein,
        // Zuklappen entfernt alles Tiefere bis zur nächsten gleichrangigen Zeile.
        document.addEventListener('alpine:init', () => {
            Alpine.data('ordnerBaum', (driveId) => ({
                zeilen: [],
                wurzelOffen: false,
                laedt: null,
                fehler: '',
                async holen(pfad) {
                    const url = @json(route('module.ekkon.notifications.graph.ordner')) + '?drive=' + encodeURIComponent(driveId) + '&pfad=' + encodeURIComponent(pfad);
                    const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const j = await r.json().catch(() => ({}));
                    if (! r.ok) { throw new Error(j.fehler || ('HTTP ' + r.status)); }
                    return j.ordner || [];
                },
                async umschalten(z) {
                    this.fehler = '';
                    const tiefe = z ? z.tiefe + 1 : 0;
                    const offen = z ? z.offen : this.wurzelOffen;
                    if (offen) {
                        // zuklappen: alles hinter der Zeile entfernen, das tiefer liegt
                        const start = z ? this.zeilen.indexOf(z) + 1 : 0;
                        let ende = start;
                        while (ende < this.zeilen.length && this.zeilen[ende].tiefe >= tiefe) { ende++; }
                        this.zeilen.splice(start, ende - start);
                        if (z) { z.offen = false; } else { this.wurzelOffen = false; }
                        return;
                    }
                    this.laedt = z ? z.pfad : '';
                    try {
                        const kinder = (await this.holen(z ? z.pfad : '')).map(k => ({ ...k, tiefe, offen: false, leer: false }));
                        const start = z ? this.zeilen.indexOf(z) + 1 : 0;
                        this.zeilen.splice(start, 0, ...kinder);
                        if (z) { z.offen = true; z.leer = kinder.length === 0; } else { this.wurzelOffen = true; }
                    } catch (e) {
                        this.fehler = e.message;
                    } finally {
                        this.laedt = null;
                    }
                },
            }));
        });
    </script>
</x-app-layout>
