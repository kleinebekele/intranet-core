{{-- Klappliste mit Häkchen für den Maillog-Filter. Beim Schließen wird das
     Formular abgeschickt, sofern sich die Auswahl geändert hat.

     $name       Feldname (ohne []), z. B. „modul"
     $optionen   Wert => Beschriftung
     $gewaehlt   gewählte Werte
     $alle       Beschriftung ohne Auswahl, z. B. „Alle Module"
     $mehrzahl   z. B. „Module" für „3 Module" --}}
<div x-data="{ offen: false, geaendert: false }"
     @click.outside="if (offen) { offen = false; if (geaendert) $el.closest('form').submit() }"
     class="relative">
    <button type="button" @click="offen = !offen"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
        @if ($gewaehlt === [])
            {{ $alle }}
        @elseif (count($gewaehlt) === 1)
            {{ $optionen[$gewaehlt[0]] ?? $gewaehlt[0] }}
        @else
            {{ count($gewaehlt) }} {{ $mehrzahl }}
        @endif
        <i class='bx bx-chevron-down text-base leading-none text-gray-400'></i>
    </button>
    <div x-show="offen" x-cloak
         class="absolute left-0 z-30 mt-1 max-h-72 w-64 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
        @forelse ($optionen as $wert => $beschriftung)
            <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $wert }}" @checked(in_array((string) $wert, $gewaehlt, true))
                       @change="geaendert = true"
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                {{ $beschriftung }}
            </label>
        @empty
            <div class="px-3 py-1.5 text-sm text-gray-400">Noch keine Mails im Log.</div>
        @endforelse
    </div>
</div>
