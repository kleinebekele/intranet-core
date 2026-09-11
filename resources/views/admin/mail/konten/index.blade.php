<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <x-slot name="titel">SMTP-Absender</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('admin.mail.index') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800">
                <i class='bx bx-chevron-left text-base leading-none'></i>
                Zurück zum Maillog
            </a>
            <a href="{{ route('admin.mail.konten.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-plus text-base'></i>
                Neuer SMTP-Absender
            </a>
        </div>

        @if (session('error'))
            <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <p class="mb-6 text-sm text-gray-500">
            Ein SMTP-Absender ist ein eigenes Postfach mit eigenem Server-Zugang. Module wie der
            Newsletter bieten die Konten je Mail zur Auswahl an; die Mail geht dann über genau diesen
            Zugang raus, mit dessen Absenderadresse. Ohne Auswahl bleibt es beim Standard-Mailer der
            Instanz ({{ config('mail.from.address') }}).
        </p>

        @if ($konten->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch kein SMTP-Absender angelegt.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Bezeichnung</th>
                            <th class="px-4 py-3">Absender</th>
                            <th class="px-4 py-3">Antwort an</th>
                            <th class="px-4 py-3">Server</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($konten as $konto)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $konto->bezeichnung }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($konto->absender_name)<span class="text-gray-800">{{ $konto->absender_name }}</span> @endif
                                    <span class="text-gray-500">&lt;{{ $konto->absender_mail }}&gt;</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $konto->antwort_an ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $konto->host }}:{{ $konto->port }}
                                    <span class="text-xs text-gray-400">{{ strtoupper($konto->verschluesselung) }}</span>
                                    @if ($konto->benutzername)
                                        <div class="text-xs text-gray-400">{{ $konto->benutzername }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($konto->aktiv)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">abgeschaltet</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1 whitespace-nowrap text-xl">
                                        <form method="POST" action="{{ route('admin.mail.konten.testmail', $konto) }}">
                                            @csrf
                                            <button type="submit" title="Testmail an mich ({{ auth()->user()->email }})"
                                                    class="block rounded-md p-1.5 text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700">
                                                <i class='bx bx-mail-send'></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.mail.konten.edit', $konto) }}" title="Bearbeiten"
                                           class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.mail.konten.destroy', $konto) }}"
                                              data-bestaetigen="SMTP-Absender „{{ $konto->bezeichnung }}“ löschen? Ausgaben, die ihn gewählt haben, fallen auf den Standard zurück.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Löschen"
                                                    class="block rounded-md p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
