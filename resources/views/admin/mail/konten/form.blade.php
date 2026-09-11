<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <x-slot name="titel">{{ $konto->exists ? 'SMTP-Absender bearbeiten' : 'SMTP-Absender anlegen' }}</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        <div class="mb-4">
            <a href="{{ route('admin.mail.konten.index') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800">
                <i class='bx bx-chevron-left text-base leading-none'></i>
                Zurück zu den SMTP-Absendern
            </a>
        </div>

        <h2 class="mb-4 text-lg font-medium text-gray-800">
            {{ $konto->exists ? 'SMTP-Absender bearbeiten' : 'Neuen SMTP-Absender anlegen' }}
        </h2>

        <form method="POST"
              action="{{ $konto->exists ? route('admin.mail.konten.update', $konto) : route('admin.mail.konten.store') }}"
              class="rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($konto->exists) @method('PUT') @endif

            @php $feld = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm'; @endphp

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="space-y-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Absender</h3>

                    <div>
                        <label for="bezeichnung" class="block text-sm font-medium text-gray-700">Bezeichnung <span class="font-normal text-gray-400">(nur intern)</span></label>
                        <input id="bezeichnung" name="bezeichnung" type="text" required maxlength="120"
                               value="{{ old('bezeichnung', $konto->bezeichnung) }}" placeholder="z. B. Redaktion" class="{{ $feld }}">
                        @error('bezeichnung') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="absender_mail" class="block text-sm font-medium text-gray-700">Absenderadresse</label>
                        <input id="absender_mail" name="absender_mail" type="email" required maxlength="191"
                               value="{{ old('absender_mail', $konto->absender_mail) }}" placeholder="redaktion@example.org" class="{{ $feld }}">
                        <p class="mt-1 text-xs text-gray-400">Muss zum Postfach passen, über das verschickt wird – sonst lehnt der Server ab oder die Mail landet im Spam.</p>
                        @error('absender_mail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="absender_name" class="block text-sm font-medium text-gray-700">Absendername <span class="font-normal text-gray-400">(Vorgabe)</span></label>
                        <input id="absender_name" name="absender_name" type="text" maxlength="120"
                               value="{{ old('absender_name', $konto->absender_name) }}" placeholder="z. B. Inforum-Redaktion" class="{{ $feld }}">
                        @error('absender_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="antwort_an" class="block text-sm font-medium text-gray-700">Antworten an <span class="font-normal text-gray-400">(Vorgabe)</span></label>
                        <input id="antwort_an" name="antwort_an" type="email" maxlength="191"
                               value="{{ old('antwort_an', $konto->antwort_an) }}" placeholder="leer = Absenderadresse" class="{{ $feld }}">
                        @error('antwort_an') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Server-Zugang</h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="host" class="block text-sm font-medium text-gray-700">SMTP-Server</label>
                            <input id="host" name="host" type="text" required maxlength="191"
                                   value="{{ old('host', $konto->host) }}" placeholder="smtp.example.org" class="{{ $feld }}">
                            @error('host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="port" class="block text-sm font-medium text-gray-700">Port</label>
                            <input id="port" name="port" type="number" required min="1" max="65535"
                                   value="{{ old('port', $konto->port) }}" class="{{ $feld }}">
                            @error('port') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="verschluesselung" class="block text-sm font-medium text-gray-700">Verschlüsselung</label>
                        <select id="verschluesselung" name="verschluesselung" class="{{ $feld }}">
                            @foreach (\App\Models\MailKonto::VERSCHLUESSELUNGEN as $wert => $beschriftung)
                                <option value="{{ $wert }}" @selected(old('verschluesselung', $konto->verschluesselung) === $wert)>{{ $beschriftung }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="benutzername" class="block text-sm font-medium text-gray-700">Benutzername</label>
                        <input id="benutzername" name="benutzername" type="text" maxlength="191" autocomplete="off"
                               value="{{ old('benutzername', $konto->benutzername) }}" placeholder="meist die Mailadresse" class="{{ $feld }}">
                        @error('benutzername') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="passwort" class="block text-sm font-medium text-gray-700">Passwort</label>
                        <input id="passwort" name="passwort" type="password" maxlength="255" autocomplete="new-password"
                               placeholder="{{ $konto->exists && $konto->passwort ? 'unverändert lassen' : '' }}" class="{{ $feld }}">
                        <p class="mt-1 text-xs text-gray-400">Wird verschlüsselt gespeichert und nicht wieder angezeigt.@if ($konto->exists) Leer lassen = altes Passwort behalten.@endif</p>
                        @error('passwort') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="aktiv" value="1" @checked(old('aktiv', $konto->aktiv))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Aktiv <span class="text-gray-400">(abgeschaltet: wird nicht angeboten, wartende Mails bleiben liegen)</span></span>
                    </label>
                </section>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save text-base'></i>
                    {{ $konto->exists ? 'Speichern' : 'Anlegen' }}
                </button>
                <a href="{{ route('admin.mail.konten.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                @if ($konto->exists)
                    <span class="ml-auto text-xs text-gray-400">Verbindung prüfen: in der Liste „Testmail" (geht an deine eigene Adresse).</span>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
