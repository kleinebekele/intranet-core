<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Modul-Verwaltung</h1>
    </x-slot>

    <div class="max-w-3xl">
        @include('admin.partials.tabs')

        <p class="text-gray-600 mb-6">
            Ziehe Module am <span class="font-medium">⠿</span>-Griff, um ihre Reihenfolge im Menü zu ändern.
            Klappe ein Modul auf, um seine Unterseiten zu sortieren. Die Reihenfolge wird sofort gespeichert.
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
                                <x-module-icon :name="$module->icon" class="h-5 w-5" />
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

                        <!-- Unterseiten -->
                        <div x-show="open" x-cloak class="border-t border-gray-100 px-4 py-3">
                            @if ($module->menuItems->isEmpty())
                                <p class="text-sm text-gray-400 py-1">Dieses Modul hat keine Unterseiten.</p>
                            @else
                                <ul class="space-y-2" data-sortable="{{ route('admin.modules.menu.reorder', $module) }}" data-handle="[data-drag-handle=item]">
                                    @foreach ($module->menuItems as $item)
                                        <li data-id="{{ $item->id }}"
                                            class="flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-2">
                                            <button type="button" data-drag-handle="item"
                                                    class="cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M7 4a1 1 0 100 2 1 1 0 000-2zM7 9a1 1 0 100 2 1 1 0 000-2zM7 14a1 1 0 100 2 1 1 0 000-2zM13 4a1 1 0 100 2 1 1 0 000-2zM13 9a1 1 0 100 2 1 1 0 000-2zM13 14a1 1 0 100 2 1 1 0 000-2z" />
                                                </svg>
                                            </button>
                                            <span class="text-sm text-gray-700">{{ $item->label }}</span>
                                            <span class="text-xs text-gray-400 ml-auto">{{ $item->route_name }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
