<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <x-slot name="titel">Absender je Auslöser</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('admin.mail.index') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800">
                <i class='bx bx-chevron-left text-base leading-none'></i>
                Zurück zum Maillog
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <i class='bx bx-check-circle text-lg leading-none'></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <div>
                    @foreach ($errors->all() as $fehler)
                        <div>{{ $fehler }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mb-6 max-w-3xl text-sm text-gray-500">
            Für jede Kombination aus <span class="font-medium text-gray-700">Modul</span> und
            <span class="font-medium text-gray-700">Auslöser</span> lässt sich ein eigener Absender und
            eine Antwort-Adresse festlegen. Bleibt ein Feld leer, gilt weiter der Absender, den das Modul
            selbst setzt. Ist etwas eingetragen, <span class="font-medium text-gray-700">gewinnt diese
            Einstellung</span>. Angezeigt werden alle Auslöser, die ein Modul angemeldet hat oder die im
            Maillog schon vorkamen.
        </p>

        @if (empty($gruppen))
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch keine Auslöser bekannt.
                <div class="mt-1 text-sm text-gray-400">
                    Sobald eine Mail verschickt oder ein Modul seine Auslöser angemeldet hat, erscheinen sie hier.
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.mail.absender.speichern') }}">
                @csrf
                @method('PUT')

                @php $i = 0; @endphp
                @foreach ($gruppen as $modul => $zeilen)
                    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                            <span class="text-sm font-semibold text-gray-700">{{ $modul }}</span>
                            <span class="ml-1 text-xs text-gray-400">
                                ({{ count($zeilen) }} {{ count($zeilen) === 1 ? 'Auslöser' : 'Auslöser' }})
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-white text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Auslöser</th>
                                        <th class="px-4 py-2.5">Absender-Name</th>
                                        <th class="px-4 py-2.5">Absender-Adresse</th>
                                        <th class="px-4 py-2.5">Antwort an</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($zeilen as $zeile)
                                        <tr>
                                            <td class="px-4 py-2.5 align-middle font-medium text-gray-700">
                                                {{ $zeile['ausloeser'] }}
                                                <input type="hidden" name="zeilen[{{ $i }}][modul]" value="{{ $zeile['modul'] }}">
                                                <input type="hidden" name="zeilen[{{ $i }}][ausloeser]" value="{{ $zeile['ausloeser'] }}">
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input type="text"
                                                       name="zeilen[{{ $i }}][absender_name]"
                                                       value="{{ $zeile['absender_name'] }}"
                                                       placeholder="Standard"
                                                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input type="email"
                                                       name="zeilen[{{ $i }}][absender_mail]"
                                                       value="{{ $zeile['absender_mail'] }}"
                                                       placeholder="Standardabsender"
                                                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <input type="email"
                                                       name="zeilen[{{ $i }}][antwort_an]"
                                                       value="{{ $zeile['antwort_an'] }}"
                                                       placeholder="—"
                                                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                        </tr>
                                        @php $i++; @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <i class='bx bx-save text-base leading-none'></i>
                        Speichern
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
