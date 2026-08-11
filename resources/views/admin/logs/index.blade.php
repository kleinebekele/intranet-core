<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>
    <x-slot name="titel">Logs</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        @if (empty($dateien))
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">
                Es sind keine Logdateien vorhanden.
            </div>
        @else
            {{-- Datei-Auswahl + Info --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" class="flex items-center gap-2 text-sm">
                    <label for="datei" class="text-gray-600">Logdatei</label>
                    <select name="datei" id="datei" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($dateien as $name)
                            <option value="{{ $name }}" @selected($name === $gewaehlt)>{{ $name }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="text-xs text-gray-500">
                    {{ count($eintraege) }} Eintrag/Einträge (neueste zuerst)
                    @if ($gekuerzt)
                        · nur die letzten {{ $lese_kb }} KB von {{ number_format($groesse / 1024, 0, ',', '.') }} KB
                    @endif
                </div>
            </div>

            @forelse ($eintraege as $e)
                @php
                    $ton = match ($e['level']) {
                        'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'bg-red-100 text-red-800',
                        'WARNING' => 'bg-amber-100 text-amber-800',
                        'NOTICE', 'INFO' => 'bg-sky-100 text-sky-800',
                        default => 'bg-gray-100 text-gray-700',
                    };
                @endphp
                <div class="mb-2 rounded-lg border border-gray-200 bg-white p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $ton }}">{{ $e['level'] }}</span>
                        <span class="font-mono text-xs text-gray-500">{{ $e['zeit'] }}</span>
                        <span class="text-xs text-gray-400">{{ $e['kanal'] }}</span>
                    </div>
                    <p class="mt-1 break-words text-sm text-gray-800">{{ $e['kopf'] }}</p>
                    @if ($e['rest'] !== '')
                        <details class="group mt-1">
                            <summary class="cursor-pointer select-none list-none text-xs text-indigo-600 hover:underline [&::-webkit-details-marker]:hidden">
                                Details anzeigen
                            </summary>
                            <pre class="mt-2 overflow-x-auto whitespace-pre-wrap rounded-md bg-gray-50 p-3 text-xs leading-relaxed text-gray-700">{{ $e['rest'] }}</pre>
                        </details>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">
                    Die gewählte Logdatei enthält im gelesenen Bereich keine erkennbaren Einträge.
                </div>
            @endforelse
        @endif
    </div>
</x-app-layout>
