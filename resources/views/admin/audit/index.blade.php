<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <x-slot name="titel">Audit</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <p class="text-gray-600">
                Wer hat wann was getan: Anmeldungen (auch fehlgeschlagene), Änderungen an Benutzern,
                Rollen, Modulen und Einstellungen – und was Module hier eintragen.
            </p>
            <p class="shrink-0 text-xs text-gray-400">
                @if ($aufbewahrungTage > 0)
                    Einträge werden nach {{ $aufbewahrungTage }} Tagen gelöscht
                    (<code class="rounded bg-gray-100 px-1">AUDIT_AUFBEWAHRUNG_TAGE</code>).
                @else
                    Einträge werden nie automatisch gelöscht.
                @endif
            </p>
        </div>

        @if ($benutzer)
            <div class="mb-4 flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <i class='bx bx-user text-lg leading-none'></i>
                <span>Verlauf von <span class="font-medium">{{ $benutzer->name }}</span> ({{ $benutzer->email }}) – als Handelnder oder Betroffener.</span>
                <a href="{{ route('admin.users.edit', $benutzer) }}" class="ml-auto text-indigo-600 hover:underline">Benutzer öffnen</a>
            </div>
        @elseif ($userId > 0)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Der Benutzer mit der Nummer {{ $userId }} existiert nicht mehr – gezeigt werden seine verbliebenen Einträge.
            </div>
        @endif

        <form method="GET" action="{{ route('admin.audit.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
            @if ($userId > 0)
                <input type="hidden" name="user" value="{{ $userId }}">
            @endif
            <div class="flex-1 min-w-[12rem]">
                <label for="search" class="block text-xs font-medium text-gray-500">Suche (Name, Ziel, Text, IP)</label>
                <input id="search" name="search" type="text" value="{{ $search }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label for="aktion" class="block text-xs font-medium text-gray-500">Aktion</label>
                <select id="aktion" name="aktion"
                        class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Alle Aktionen</option>
                    @foreach ($aktionen as $schluessel => $text)
                        <option value="{{ $schluessel }}" @selected($aktion === $schluessel)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="von" class="block text-xs font-medium text-gray-500">Von</label>
                <input id="von" name="von" type="date" value="{{ $von }}"
                       class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label for="bis" class="block text-xs font-medium text-gray-500">Bis</label>
                <input id="bis" name="bis" type="date" value="{{ $bis }}"
                       class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-search text-base'></i> Filtern
            </button>
            @if ($gefiltert)
                <a href="{{ route('admin.audit.index') }}" class="px-2 py-2 text-sm text-gray-500 hover:text-gray-700">Zurücksetzen</a>
            @endif
        </form>

        <div class="mb-2 text-xs text-gray-400">
            {{ $eintraege->total() }} Einträge
            @if ($gefiltert)
                <span class="text-gray-300">·</span> gefiltert
            @endif
            @if ($eintraege->hasPages())
                <span class="text-gray-300">·</span> Seite {{ $eintraege->currentPage() }} von {{ $eintraege->lastPage() }}
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Zeitpunkt</th>
                        <th class="px-4 py-3">Aktion</th>
                        <th class="px-4 py-3">Wer</th>
                        <th class="px-4 py-3">Betrifft</th>
                        <th class="px-4 py-3">Was</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($eintraege as $eintrag)
                        <tr x-data="{ offen: false }">
                            <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-500">
                                {{ $eintrag->created_at?->format('d.m.Y H:i:s') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <span @class([
                                    'inline-block rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-red-100 text-red-800' => $eintrag->istWarnung(),
                                    'bg-emerald-100 text-emerald-800' => $eintrag->aktion === 'anmeldung',
                                    'bg-gray-100 text-gray-700' => ! $eintrag->istWarnung() && $eintrag->aktion !== 'anmeldung',
                                ])>{{ $eintrag->aktionText() }}</span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($eintrag->user_id)
                                    <a href="{{ route('admin.audit.index', ['user' => $eintrag->user_id]) }}"
                                       class="text-gray-800 hover:text-indigo-700 hover:underline">{{ $eintrag->akteur }}</a>
                                @elseif ($eintrag->akteur)
                                    <span class="text-gray-800">{{ $eintrag->akteur }}</span>
                                    <span class="block text-xs text-gray-400">Konto gelöscht</span>
                                @else
                                    <span class="text-xs text-gray-400">System / niemand</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($eintrag->betroffener_id && $eintrag->betroffener_id !== $eintrag->user_id)
                                    <a href="{{ route('admin.audit.index', ['user' => $eintrag->betroffener_id]) }}"
                                       class="text-gray-800 hover:text-indigo-700 hover:underline">{{ $eintrag->betroffener }}</a>
                                @elseif ($eintrag->betroffener && $eintrag->betroffener_id !== $eintrag->user_id)
                                    <span class="text-gray-800">{{ $eintrag->betroffener }}</span>
                                    <span class="block text-xs text-gray-400">Konto gelöscht</span>
                                @elseif ($eintrag->betroffener_id && $eintrag->betroffener_id === $eintrag->user_id)
                                    <span class="text-xs text-gray-400">sich selbst</span>
                                @endif
                                @if ($eintrag->ziel)
                                    <span class="block text-xs text-gray-500">{{ $eintrag->ziel }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600">
                                {{ $eintrag->beschreibung }}
                                @if ($eintrag->daten)
                                    <button type="button" @click="offen = !offen"
                                            class="ml-1 text-xs text-indigo-600 hover:underline">Details</button>
                                    <pre x-show="offen" x-cloak
                                         class="mt-1 max-w-xl overflow-x-auto whitespace-pre-wrap rounded bg-gray-50 p-2 text-xs text-gray-600">{{ json_encode($eintrag->daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-400">{{ $eintrag->ip }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                Keine Einträge{{ $gefiltert ? ' für diesen Filter' : ' – das Log füllt sich mit der nächsten Anmeldung' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($eintraege->hasPages())
            <div class="mt-4">{{ $eintraege->links() }}</div>
        @endif
    </div>
</x-app-layout>
