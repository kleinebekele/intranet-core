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
