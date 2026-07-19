<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Modul-Verwaltung</h1>
    </x-slot>

    <div class="max-w-3xl">
        @include('admin.partials.tabs')

        {{-- Erfolgsmeldungen rendert das Layout; Fehler aus dem Entfernen-Dialog hier. --}}
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <p class="text-gray-600 mb-6">
            Ziehe Module am Griff, um ihre Reihenfolge zu ändern (wird sofort gespeichert). Klappe ein Modul auf,
            um seine Unterseiten zu sortieren und festzulegen, <span class="font-medium">welche Rollen</span>
            die einzelnen Unterseiten sehen <span class="font-medium">und aufrufen</span> dürfen.
            Ist <span class="font-medium">keine Rolle</span> ausgewählt, ist die Seite <span class="font-medium">nur für
            Administratoren</span> zugänglich — „für alle" wählst du über die Basis-Rolle <span class="font-medium">Benutzer</span>,
            die jeder automatisch hat. Ein Modul erscheint im Menü, sobald jemand mindestens eine seiner Unterseiten sehen darf.
        </p>

        @if ($modules->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Es sind noch keine Module installiert.
                <div class="mt-1 text-sm text-gray-400">
                    Installiere ein Modul und führe <code class="rounded bg-gray-100 px-1.5 py-0.5">php artisan modules:sync</code> aus.
                </div>
            </div>
        @else
            <ul class="space-y-3" data-sortable="{{ route('admin.modules.reorder') }}" data-handle="[data-drag-handle=module]">
                @foreach ($modules as $module)
                    <li data-id="{{ $module->id }}"
                        x-data="{ open: false }"
                        class="rounded-xl border border-gray-200 bg-white">
                        <div class="flex items-center gap-3 p-4">
                            <!-- Griff zum Ziehen -->
                            <button type="button" data-drag-handle="module"
                                    class="cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500"
                                    title="Ziehen zum Sortieren">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M7 4a1 1 0 100 2 1 1 0 000-2zM7 9a1 1 0 100 2 1 1 0 000-2zM7 14a1 1 0 100 2 1 1 0 000-2zM13 4a1 1 0 100 2 1 1 0 000-2zM13 9a1 1 0 100 2 1 1 0 000-2zM13 14a1 1 0 100 2 1 1 0 000-2z" />
                                </svg>
                            </button>

                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <x-module-icon :name="$module->icon" class="text-xl" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-gray-800">{{ $module->name }}</div>
                                <div class="text-xs text-gray-400">{{ $module->key }} &middot; {{ $module->menuItems->count() }} Unterseiten</div>
                            </div>

                            <!-- An/aus -->
                            <form method="POST" action="{{ route('admin.modules.toggle', $module) }}">
                                @csrf
                                <button type="submit"
                                        @class([
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition',
                                            'bg-indigo-600' => $module->is_enabled,
                                            'bg-gray-300' => ! $module->is_enabled,
                                        ])
                                        title="{{ $module->is_enabled ? 'Aktiv – klicken zum Deaktivieren' : 'Inaktiv – klicken zum Aktivieren' }}">
                                    <span @class([
                                        'inline-block h-4 w-4 transform rounded-full bg-white transition',
                                        'translate-x-6' => $module->is_enabled,
                                        'translate-x-1' => ! $module->is_enabled,
                                    ])></span>
                                </button>
                            </form>

                            <!-- Aufklappen -->
                            <button type="button" @click="open = ! open"
                                    class="p-1.5 rounded-md text-gray-400 hover:bg-gray-100"
                                    title="Unterseiten anzeigen">
                                <svg class="h-5 w-5 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Aufgeklappt: Sichtbarkeit (Modul + Unterseiten) -->
                        <div x-show="open" x-cloak class="border-t border-gray-100 px-4 py-4">
                            <form method="POST" action="{{ route('admin.modules.visibility', $module) }}" class="space-y-4"
                                  x-data="{ adminsOnly: {{ $module->admins_only ? 'true' : 'false' }} }">
                                @csrf
                                @method('PUT')

                                {{-- Nur für Admins (Modul) --}}
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="module_admins_only" value="1" x-model="adminsOnly"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="inline-flex items-center gap-1"><i class='bx bx-lock-alt'></i> Nur für Admins sichtbar</span>
                                </label>

                                {{-- Unterseiten: Reihenfolge (ziehen) + Sichtbarkeit --}}
                                @if ($module->menuItems->isEmpty())
                                    <p class="text-sm text-gray-400">Dieses Modul hat keine Unterseiten.</p>
                                @else
                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">Unterseiten sichtbar für</p>
                                        <ul class="space-y-2" data-sortable="{{ route('admin.modules.menu.reorder', $module) }}" data-handle="[data-drag-handle=item]">
                                            @foreach ($module->menuItems as $item)
                                                <li data-id="{{ $item->id }}" class="rounded-lg bg-gray-50 px-3 py-2"
                                                    x-data="{ itemAdminsOnly: {{ $item->admins_only ? 'true' : 'false' }} }">
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" data-drag-handle="item" title="Ziehen zum Sortieren"
                                                                class="cursor-grab active:cursor-grabbing text-lg leading-none text-gray-300 hover:text-gray-500">
                                                            <i class='bx bx-dots-vertical-rounded'></i>
                                                        </button>
                                                        <span class="text-sm font-medium text-gray-700">{{ $item->label }}</span>
                                                        <span class="ml-auto text-xs text-gray-400">{{ $item->route_name }}</span>
                                                    </div>
                                                    <div class="mt-2 space-y-2 pl-7">
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                                            <input type="checkbox" name="item_admins_only[{{ $item->id }}]" value="1" x-model="itemAdminsOnly"
                                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                            <span class="inline-flex items-center gap-1"><i class='bx bx-lock-alt'></i> Nur für Admins</span>
                                                        </label>
                                                        <div class="flex flex-wrap gap-1.5" :class="itemAdminsOnly && 'opacity-40 pointer-events-none'">
                                                            @foreach ($roles as $role)
                                                                <label class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-sm text-gray-700">
                                                                    <input type="checkbox" name="item_roles[{{ $item->id }}][]" value="{{ $role->role_id }}"
                                                                           @checked($item->roles->contains('role_id', $role->role_id))
                                                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                                    {{ $role->name }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="flex items-center gap-3 pt-1">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                        <i class='bx bx-save text-base'></i>
                                        Sichtbarkeit speichern
                                    </button>
                                    <span class="text-xs text-gray-400">Keine Auswahl = nur Administratoren · „Benutzer" = alle · gilt für Menü und Zugriff</span>
                                </div>
                            </form>

                            {{-- Entfernen: eingeklappt, damit es nicht versehentlich angeklickt wird. --}}
                            @php($vorschau = $vorschauen[$module->id] ?? null)
                            @php($umfang = 'Modul-Eintrag, '.$module->menuItems->count().' Menüpunkt(e) samt Rollen-Zuordnung'
                                .(($vorschau && $vorschau['adressen']) ? ' und '.$vorschau['adressen'].' sprechende Adresse(n)' : ''))
                            <div class="mt-6 border-t border-gray-100 pt-4" x-data="{ zeigen: false, mitDaten: false }">
                                <button type="button" @click="zeigen = ! zeigen"
                                        class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-red-600">
                                    <i class='bx bx-trash text-base'></i>
                                    Modul entfernen
                                </button>

                                <div x-show="zeigen" x-cloak class="mt-3 rounded-lg border border-red-200 bg-red-50 p-4">
                                    <p class="text-sm text-gray-700">
                                        Entfernt <span class="font-medium">{{ $module->name }}</span> aus dieser Instanz:
                                        {{ $umfang }}.
                                    </p>

                                    @if ($vorschau && ! $vorschau['paket_installiert'])
                                        <p class="mt-2 text-sm text-gray-600">
                                            Das Paket ist bereits deinstalliert – hier lassen sich nur noch die
                                            zurückgebliebenen Einträge aufräumen. Die Tabellen des Moduls könnte
                                            nur die Konsole abräumen, und auch das erst, wenn das Paket kurz
                                            wieder eingebunden wird.
                                        </p>
                                    @endif

                                    <form method="POST" action="{{ route('admin.modules.destroy', $module) }}" class="mt-3 space-y-3">
                                        @csrf
                                        @method('DELETE')

                                        @if ($vorschau && $vorschau['paket_installiert'] && count($vorschau['migrationen']))
                                            <div class="rounded-lg border border-red-200 bg-white p-3">
                                                <label class="inline-flex items-start gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="mit_daten" value="1" x-model="mitDaten"
                                                           class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                                    <span>
                                                        Auch die <span class="font-medium">Tabellen des Moduls</span> löschen
                                                        (Migrationen zurückrollen)
                                                    </span>
                                                </label>

                                                <ul class="mt-2 space-y-0.5 pl-6 text-xs text-gray-500">
                                                    @foreach ($vorschau['migrationen'] as $migration)
                                                        @forelse ($migration['tabellen'] as $tabelle)
                                                            <li>
                                                                <code>{{ $tabelle['name'] }}</code>
                                                                @if ($tabelle['vorhanden'])
                                                                    — <span class="font-medium text-gray-700">{{ $tabelle['zeilen'] }} Zeilen</span>
                                                                @else
                                                                    — nicht vorhanden
                                                                @endif
                                                            </li>
                                                        @empty
                                                            <li>{{ $migration['name'] }}</li>
                                                        @endforelse
                                                    @endforeach
                                                </ul>

                                                <div x-show="mitDaten" x-cloak class="mt-3">
                                                    <label class="block text-sm text-gray-700">
                                                        Diese Daten sind danach weg. Zum Bestätigen den Modul-Schlüssel
                                                        <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $module->key }}</code> eintippen:
                                                        <input type="text" name="bestaetigung" autocomplete="off"
                                                               class="mt-1 block w-56 rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                                                    </label>
                                                    @error('bestaetigung')
                                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Den composer-Befehl nur zeigen, wenn der echte Paketname
                                             bekannt ist. Ein Platzhalter würde abgetippt werden und
                                             richtet dabei Schaden an. --}}
                                        @if ($vorschau && $vorschau['paket_name'])
                                            <p class="text-sm text-gray-600">
                                                <i class='bx bx-info-circle'></i>
                                                Das <span class="font-medium">Paket selbst</span> bleibt installiert. Sonst taucht das
                                                Modul beim nächsten <code class="rounded bg-white px-1 py-0.5">modules:sync</code> wieder
                                                auf. Danach also auf dem Server noch:
                                                <code class="mt-1 block rounded bg-white px-2 py-1">composer remove {{ $vorschau['paket_name'] }}</code>
                                            </p>
                                        @else
                                            <p class="text-sm text-gray-600">
                                                <i class='bx bx-info-circle'></i>
                                                Das Paket ist hier nicht installiert, sein Name also unbekannt. Sieh in der
                                                <code class="rounded bg-white px-1 py-0.5">composer.json</code> nach, ob dort noch ein
                                                Eintrag für <span class="font-medium">{{ $module->key }}</span> steht — sonst ist das
                                                Modul beim nächsten <code class="rounded bg-white px-1 py-0.5">composer install</code>
                                                samt <code class="rounded bg-white px-1 py-0.5">modules:sync</code> wieder da.
                                            </p>
                                        @endif

                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                                            <i class='bx bx-trash text-base'></i>
                                            <span x-text="mitDaten ? 'Modul und Daten löschen' : 'Modul entfernen'">Modul entfernen</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
