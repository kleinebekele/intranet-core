<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>
    <x-slot name="titel">Adressen &amp; Titel</x-slot>

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
            {{ count($zeilen) }} von {{ $gesamt }} Seiten
            @if ($suche !== '' || $modulFilter !== '')
                <span class="text-gray-300">·</span> gefiltert
            @endif
        </div>

        @if ($zeilen === [])
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Keine Seite passt zum Filter.
            </div>
        @else
            {{-- Die Formulare stehen bewusst AUSSERHALB der Tabelle: Ein <form> als
                 Kind von <tr> ist ungültiges HTML, Browser verschieben es dann aus
                 der Tabelle heraus. Die Felder in den Zeilen hängen über das
                 form="…"-Attribut daran. --}}
            @foreach ($zeilen as $zeile)
                <form method="POST" action="{{ route('admin.seo.update') }}" id="f-{{ $loop->index }}" class="hidden">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="route_name" value="{{ $zeile['name'] }}">
                    <input type="hidden" name="suche" value="{{ $suche }}">
                    <input type="hidden" name="modul" value="{{ $modulFilter }}">
                </form>
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
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($zeilen as $zeile)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">
                                        {{ \Illuminate\Support\Str::of($zeile['name'])->afterLast('.')->replace('-', ' ')->ucfirst() }}
                                    </div>
                                    <div class="text-xs text-gray-400">{{ $zeile['name'] }}</div>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $zeile['modul'] }}</td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400">/</span>
                                        <input type="text" name="pfad" form="f-{{ $loop->index }}"
                                               value="{{ $zeile['pfad'] }}"
                                               placeholder="{{ $zeile['original'] }}"
                                               class="block w-64 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    @if ($zeile['pfad'])
                                        <div class="mt-1 text-xs text-gray-400">
                                            leitet weiter von <span class="font-mono">/{{ $zeile['original'] }}</span>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <input type="text" name="titel" form="f-{{ $loop->index }}"
                                           value="{{ $zeile['titel'] }}"
                                           placeholder="{{ \App\Support\Seitentitel::haupttitel() }} – …"
                                           class="block w-64 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <div class="mt-1 text-xs text-gray-400">
                                        Leer = Titel nach Konvention
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="submit" form="f-{{ $loop->index }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        <i class='bx bx-save text-sm leading-none'></i>
                                        Speichern
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
