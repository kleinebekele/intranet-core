<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>
    {{-- Ohne HTML-Entities: Der Slot-Inhalt wird im Layout noch einmal maskiert,
         aus &amp; wuerde sonst sichtbar "&amp;". --}}
    <x-slot name="titel">Adressen und Titel</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <p class="mb-4 max-w-3xl text-sm text-gray-600">
            Hier bekommt jede Seite eine sprechende Adresse und einen festen Titel. Die Adresse
            <span class="font-medium">ersetzt</span> die bisherige – auch alle Menüpunkte und
            internen Verweise zeigen danach dorthin. Die alte Adresse leitet weiter, damit
            verschickte Links nicht ins Leere laufen.
        </p>

        {{-- Filterleiste (GET, damit der Filter in der Adresse steht und teilbar bleibt).
             Ohne Knopf: Die Modulauswahl schickt sofort ab, die Suche nach kurzer Tipppause.
             Da dabei neu geladen wird, holt sich das Suchfeld den Fokus zurück. --}}
        <form method="GET" action="{{ route('admin.seo.index') }}"
              class="mb-4 flex flex-wrap items-end gap-3"
              x-data="{
                  timer: null,
                  search(form, value) {
                      clearTimeout(this.timer);
                      if (value.length > 0 && value.length < 3) return;
                      this.timer = setTimeout(() => form.requestSubmit(), 400);
                  },
              }">
            <div class="min-w-[14rem] flex-1">
                <label for="suche" class="block text-xs font-medium text-gray-500">
                    Suche (Seite, Adresse, Titel – ab 3 Zeichen von selbst)
                </label>
                <input id="suche" name="suche" type="text" value="{{ $suche }}"
                       placeholder="z. B. speiseplan"
                       @input="search($el.form, $el.value)"
                       x-init="if ($el.value) { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length); }"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="modul" class="block text-xs font-medium text-gray-500">Modul</label>
                <select id="modul" name="modul" @change="$el.form.requestSubmit()"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Alle Module</option>
                    <option value="core" @selected($modulFilter === 'core')>Core (ohne Modul)</option>
                    @foreach ($module as $manifest)
                        <option value="{{ $manifest->key }}" @selected($modulFilter === $manifest->key)>{{ $manifest->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($suche !== '' || $modulFilter !== '')
                <a href="{{ route('admin.seo.index') }}"
                   class="inline-flex items-center gap-1.5 px-2 py-2 text-sm text-gray-500 hover:text-gray-700">
                    <i class='bx bx-x text-base'></i> Zurücksetzen
                </a>
            @endif
        </form>

        <div class="mb-2 text-xs text-gray-400">
            {{ $anzahl }} von {{ $gesamt }} Seiten
            @if ($suche !== '' || $modulFilter !== '')
                <span class="text-gray-300">·</span> gefiltert
            @endif
        </div>

        @if ($bereiche === [])
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Keine Seite passt zum Filter.
            </div>
        @else
            {{-- Die Formulare stehen bewusst AUSSERHALB der Tabelle: Ein <form> als
                 Kind von <tr> ist ungültiges HTML, Browser verschieben es dann aus
                 der Tabelle heraus. Die Felder in den Zeilen hängen über das
                 form="…"-Attribut daran. --}}
            @foreach ($bereiche as $bereich)
                @foreach (array_merge([$bereich['zeile']], $bereich['kinder']) as $zeile)
                    <form method="POST" action="{{ route('admin.seo.update') }}"
                          id="f-{{ str_replace('.', '_', $zeile['name']) }}" class="hidden">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="route_name" value="{{ $zeile['name'] }}">
                        <input type="hidden" name="suche" value="{{ $suche }}">
                        <input type="hidden" name="modul" value="{{ $modulFilter }}">
                    </form>
                @endforeach
            @endforeach

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Seite</th>
                            <th class="px-4 py-3">Modul</th>
                            <th class="px-4 py-3">Adresse</th>
                            <th class="px-4 py-3">Fester Titel</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    @foreach ($bereiche as $bereich)
                        {{-- Ein tbody je Bereich: Das Aufklappen bleibt damit rein
                             örtlich, ohne dass die Zeilen voneinander wissen müssen. --}}
                        <tbody class="divide-y divide-gray-100 border-t border-gray-100" x-data="{ offen: false }">
                            @include('admin.seo.partials.zeile', ['zeile' => $bereich['zeile'], 'kind' => false])

                            @if ($bereich['kinder'] !== [])
                                <tr>
                                    <td colspan="5" class="px-4 py-0">
                                        <button type="button" @click="offen = ! offen"
                                                class="flex w-full items-center gap-1.5 py-2 pl-6 text-left text-xs font-medium text-gray-500 hover:text-gray-700">
                                            <i class='bx text-sm leading-none' :class="offen ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                                            {{ count($bereich['kinder']).' '.\Illuminate\Support\Str::plural('Unterlink', count($bereich['kinder'])) }}
                                        </button>
                                    </td>
                                </tr>

                                @foreach ($bereich['kinder'] as $kindZeile)
                                    @include('admin.seo.partials.zeile', [
                                        'zeile' => $kindZeile,
                                        'kind' => true,
                                        'versteckt' => true,
                                    ])
                                @endforeach
                            @endif
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
