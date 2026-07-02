<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Modul-Verwaltung</h1>
    </x-slot>

    <div class="max-w-3xl">
        @include('admin.partials.tabs')

        <p class="text-gray-600 mb-6">
            Ziehe Module am Griff, um ihre Reihenfolge zu ändern (wird sofort gespeichert). Klappe ein Modul auf,
            um seine Unterseiten zu sortieren und festzulegen, <span class="font-medium">welche Rollen</span>
            das Modul bzw. einzelne Unterpunkte in der Navigation sehen.
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
                            <form method="POST" action="{{ route('admin.modules.visibility', $module) }}" class="space-y-4">
                                @csrf
                                @method('PUT')

                                {{-- Modul-Sichtbarkeit --}}
                                <div>
                                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">Modul sichtbar für</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($roles as $role)
                                            <label class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1 text-sm text-gray-700">
                                                <input type="checkbox" name="module_roles[]" value="{{ $role->role_id }}"
                                                       @checked($module->roles->contains('role_id', $role->role_id))
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                {{ $role->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Unterseiten: Reihenfolge (ziehen) + Sichtbarkeit --}}
                                @if ($module->menuItems->isEmpty())
                                    <p class="text-sm text-gray-400">Dieses Modul hat keine Unterseiten.</p>
                                @else
                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">Unterseiten sichtbar für</p>
                                        <ul class="space-y-2" data-sortable="{{ route('admin.modules.menu.reorder', $module) }}" data-handle="[data-drag-handle=item]">
                                            @foreach ($module->menuItems as $item)
                                                <li data-id="{{ $item->id }}" class="rounded-lg bg-gray-50 px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" data-drag-handle="item" title="Ziehen zum Sortieren"
                                                                class="cursor-grab active:cursor-grabbing text-lg leading-none text-gray-300 hover:text-gray-500">
                                                            <i class='bx bx-dots-vertical-rounded'></i>
                                                        </button>
                                                        <span class="text-sm font-medium text-gray-700">{{ $item->label }}</span>
                                                        <span class="ml-auto text-xs text-gray-400">{{ $item->route_name }}</span>
                                                    </div>
                                                    <div class="mt-2 flex flex-wrap gap-1.5 pl-7">
                                                        @foreach ($roles as $role)
                                                            <label class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-sm text-gray-700">
                                                                <input type="checkbox" name="item_roles[{{ $item->id }}][]" value="{{ $role->role_id }}"
                                                                       @checked($item->roles->contains('role_id', $role->role_id))
                                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                                {{ $role->name }}
                                                            </label>
                                                        @endforeach
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
                                    <span class="text-xs text-gray-400">Keine Auswahl = für alle sichtbar · Admins sehen immer alles</span>
                                </div>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
