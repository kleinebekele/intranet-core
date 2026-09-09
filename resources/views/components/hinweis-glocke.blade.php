{{--
    Die Glocke in der Kopfzeile. Sie zeigt, was gerade jemanden braucht:
    Module melden offene Zustände über App\Support\Hinweise an, der Core
    zählt sie in den roten Punkt und listet sie im Klappmenü. Gibt es nichts,
    bleibt die Glocke grau – sie ist immer da, damit man sich auf sie
    verlassen kann.
--}}
@php($hinweise = \App\Support\Hinweise::alle())
@php($anzahl = \App\Support\Hinweise::summe($hinweise))

<x-dropdown align="right" width="w-80">
    <x-slot name="trigger">
        <button type="button"
                title="{{ $anzahl > 0 ? $anzahl.' offene Hinweise' : 'Keine offenen Hinweise' }}"
                class="relative inline-flex items-center justify-center h-9 w-9 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $anzahl > 0 ? 'text-red-600 hover:bg-red-50' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600' }}">
            <x-module-icon name="bell" class="text-xl" />
            @if ($anzahl > 0)
                <span class="absolute -top-0.5 -right-0.5 min-w-[1.25rem] h-5 px-1 rounded-full bg-red-600 text-white text-xs font-semibold flex items-center justify-center">
                    {{ $anzahl > 99 ? '99+' : $anzahl }}
                </span>
            @endif
            <span class="sr-only">{{ $anzahl > 0 ? $anzahl.' offene Hinweise' : 'Keine offenen Hinweise' }}</span>
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="px-4 py-2 border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-500">
            Offene Hinweise
        </div>

        @forelse ($hinweise as $hinweis)
            <a href="{{ $hinweis->url }}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                <div class="flex items-start gap-2">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-600"></span>
                    <span class="min-w-0">
                        <span class="block text-sm text-gray-800">{{ $hinweis->titel }}</span>
                        @if ($hinweis->quelle !== '')
                            <span class="block text-xs text-gray-500">{{ $hinweis->quelle }}</span>
                        @endif
                    </span>
                </div>
            </a>
        @empty
            <div class="px-4 py-3 text-sm text-gray-500">Nichts offen – alles läuft.</div>
        @endforelse
    </x-slot>
</x-dropdown>
