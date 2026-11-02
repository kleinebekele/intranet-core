<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Systemeinstellungen</h1>
    </x-slot>

    @php($selectedRoles = old('roles', $user->roles->pluck('role_id')->all()))

    <div>
        @include('admin.partials.tabs')

        <h2 class="text-lg font-medium text-gray-800 mb-4">Benutzer bearbeiten</h2>

        <form method="POST" action="{{ route('admin.users.update', $user) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">E-Mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Anmelde-Adresse. Eine Änderung gilt sofort und wird im Audit-Log festgehalten; eine aus einem Import stammende Adresse wird dadurch nicht mehr überschrieben.</p>
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700">Rollen</span>
                @if ($roles->isEmpty())
                    <p class="mt-1 text-sm text-gray-400">Noch keine Rollen vorhanden – lege welche im Tab „Rollen" an.</p>
                @else
                    @include('admin.users._rollenauswahl', ['gruppen' => $gruppen, 'selectedRoles' => $selectedRoles])
                @endif

                {{-- Gruppen, die ein Abgleich pflegt (Klassen, Arbeitskreise aus einem
                     Fremdsystem): nur zur Ansicht. Ein Häkchen hier würde der nächste
                     Lauf zurückdrehen – gepflegt wird dort, wo die Daten herkommen. --}}
                @if ($verwaltet->isNotEmpty())
                    <div class="mt-4" x-data="{ offen: false }">
                        <button type="button" @click="offen = ! offen"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700">
                            <i class='bx bx-refresh'></i>
                            Vom Abgleich gepflegte Gruppen ({{ $verwaltet->count() }})
                            <i class='bx' :class="offen ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
                        </button>
                        <div x-show="offen" x-cloak class="mt-2">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($verwaltet as $role)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-sm text-sky-800"
                                          title="Pflegt der Abgleich „{{ $role->quelle }}“">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-gray-400">
                                Diese Zugehörigkeiten kommen aus einem Abgleich und lassen sich hier nicht ändern –
                                der nächste Lauf würde es zurückdrehen. Beim Speichern bleiben sie unverändert.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Administrator</span>
            </label>
            @error('is_admin') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save text-base'></i>
                    Speichern
                </button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>

        {{-- Passwort-Reset-Link als eigenes Formular (Formulare dürfen nicht verschachtelt sein). --}}
        <form method="POST" action="{{ route('admin.users.reset', $user) }}"
              class="mt-4 flex items-center justify-between rounded-xl border border-gray-200 bg-white px-6 py-4">
            @csrf
            <div class="text-sm text-gray-600">
                Passwort-Reset-Link an <span class="font-medium">{{ $user->email }}</span> senden.
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                <i class='bx bx-mail-send text-base'></i>
                Link senden
            </button>
        </form>

        {{-- Passwort direkt setzen – wenn der Link nicht ankommt (kein/falsches Postfach). --}}
        <form method="POST" action="{{ route('admin.users.passwort', $user) }}"
              x-data="{ zeigen: false }"
              class="mt-4 rounded-xl border border-gray-200 bg-white px-6 py-4">
            @csrf
            <div class="text-sm text-gray-600">
                Oder Passwort direkt vergeben – dann bitte persönlich mitteilen.
            </div>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="neues_passwort" class="block text-xs font-medium text-gray-500">Neues Passwort</label>
                    <input id="neues_passwort" name="password" :type="zeigen ? 'text' : 'password'" type="password" autocomplete="new-password" required
                           class="mt-1 block w-56 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="neues_passwort_wdh" class="block text-xs font-medium text-gray-500">Wiederholen</label>
                    <input id="neues_passwort_wdh" name="password_confirmation" :type="zeigen ? 'text' : 'password'" type="password" autocomplete="new-password" required
                           class="mt-1 block w-56 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <label class="mb-2 inline-flex items-center gap-1.5 text-xs text-gray-500">
                    <input type="checkbox" x-model="zeigen" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> anzeigen
                </label>
                <button type="submit"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                    <i class='bx bx-key text-base'></i>
                    Passwort setzen
                </button>
            </div>
            @if ($errors->passwort->any())
                <p class="mt-2 text-sm text-red-600">{{ $errors->passwort->first() }}</p>
            @endif
        </form>

        {{-- TOTP-Reset: nur anzeigen, wenn der Benutzer eine Authenticator-App eingerichtet hat. --}}
        @if ($user->hasTotp())
            <form method="POST" action="{{ route('admin.users.reset-totp', $user) }}"
                  onsubmit="return confirm('TOTP für {{ $user->email }} wirklich zurücksetzen? Der Benutzer meldet sich danach wieder mit Mail-Codes an.');"
                  class="mt-4 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-6 py-4">
                @csrf
                <div class="text-sm text-amber-800">
                    Authenticator-App (TOTP) aktiv seit {{ $user->totp_confirmed_at->format('d.m.Y') }} —
                    bei Handy-Verlust hier zurücksetzen.
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    <i class='bx bx-shield-x text-base'></i>
                    TOTP zurücksetzen
                </button>
            </form>
        @endif

        {{-- Zusatzbereiche der Module (App\Support\Benutzerbereiche), z. B. verknüpfte Fremdkonten. --}}
        @foreach (\App\Support\Benutzerbereiche::fuer($user) as $schluessel => $bereich)
            <div class="mt-4" data-benutzerbereich="{{ $schluessel }}">{!! $bereich !!}</div>
        @endforeach
    </div>
</x-app-layout>
