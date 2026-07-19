@php
    // Der Routen-Name ist eindeutig und stabil – als Formular-Kennung besser
    // als ein Zähler, der sich beim Ein- und Ausklappen verschieben würde.
    $id = 'f-'.str_replace('.', '_', $zeile['name']);
    $kind ??= false;
    $versteckt ??= false;
@endphp

{{-- x-show statt <template x-if>: In einer Tabelle bleiben die Zeilen damit an
     ihrem Platz im tbody. Ein x-if baut sie aus- und wieder ein, was in
     Tabellen erfahrungsgemäß zu Überraschungen führt. --}}
<tr class="align-top {{ $kind ? 'bg-gray-50/60' : '' }}"
    @if ($versteckt) x-show="offen" x-cloak @endif>
    <td class="px-4 py-3 {{ $kind ? 'pl-10' : '' }}">
        @if ($zeile['bezeichnung'])
            <div class="font-medium text-gray-800">{{ $zeile['bezeichnung'] }}</div>
            <div class="text-xs text-gray-400">{{ $zeile['technischerName'] }}</div>
        @else
            {{-- Ohne Menüpunkt gibt es keinen sprechenden Namen. Dann steht der
                 technische allein da, statt ihn mit einem aus ihm selbst
                 abgeleiteten Wort zu doppeln. --}}
            <div class="text-gray-600">{{ $zeile['technischerName'] }}</div>
        @endif
    </td>

    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $kind ? '' : $zeile['modul'] }}</td>

    <td class="px-4 py-3" @if ($kind) x-data="{ absolut: {{ $zeile['absoluterPfad'] ? 'true' : 'false' }} }" @endif>
        <div class="flex items-center gap-1">
            <span class="text-gray-400">/</span>

            @if ($kind && $zeile['stammPfad'] !== null)
                {{-- Der Bereich als fester Vorsatz: Er gehört zur Adresse, ist
                     hier aber nicht zu ändern – er kommt aus der Zeile darüber
                     und wandert mit, wenn die sich ändert. --}}
                <span class="font-mono text-gray-500" x-show="! absolut">{{ $zeile['stammPfad'] }}/</span>
            @endif

            <input type="text" name="pfad" form="{{ $id }}"
                   value="{{ $zeile['pfad'] }}"
                   placeholder="{{ $zeile['vorgabe'] ?? $zeile['aktuell'] }}"
                   class="block w-64 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        @if ($zeile['pfad'])
            <div class="mt-1 text-xs text-gray-400">
                leitet weiter von <span class="font-mono">/{{ $zeile['original'] }}</span>
            </div>
        @elseif ($kind)
            {{-- Leeres Feld heißt hier nicht "nichts", sondern "erbt vom Bereich".
                 Ohne diesen Hinweis wirkt die graue Vorgabe wie ein Zufall. --}}
            <div class="mt-1 text-xs text-gray-400">
                Leer = folgt dem Bereich
            </div>
        @endif

        @if ($kind)
            <label class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-500">
                <input type="checkbox" name="absoluter_pfad" value="1" form="{{ $id }}"
                       x-model="absolut"
                       @checked($zeile['absoluterPfad'])
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                Absoluter Pfad
            </label>
        @endif
    </td>

    <td class="px-4 py-3">
        <input type="text" name="titel" form="{{ $id }}"
               value="{{ $zeile['titel'] }}"
               placeholder="{{ \App\Support\Seitentitel::haupttitel() }} – …"
               class="block w-64 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <div class="mt-1 text-xs text-gray-400">Leer = Titel nach Konvention</div>
    </td>

    <td class="px-4 py-3 text-right whitespace-nowrap">
        <button type="submit" form="{{ $id }}"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
            <i class='bx bx-save text-sm leading-none'></i>
            Speichern
        </button>
    </td>
</tr>
