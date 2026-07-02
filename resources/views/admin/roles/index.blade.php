<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-3xl">
        @include('admin.partials.tabs')

        <div class="mb-6 flex items-center justify-between">
            <p class="text-gray-600">
                Rollen bündeln Berechtigungen. Der <span class="font-medium">Schlüssel</span> ist fest,
                den <span class="font-medium">Namen</span> kannst du jederzeit ändern.
            </p>
            <a href="{{ route('admin.roles.create') }}"
               class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Neue Rolle
            </a>
        </div>

        @if ($roles->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Es sind noch keine Rollen angelegt.
                <div class="mt-1 text-sm text-gray-400">
                    Lege eine an – oder importiere Benutzer, dann entstehen unbekannte Rollen automatisch.
                </div>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($roles as $role)
                    <li class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <x-module-icon name="users" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-800">{{ $role->name }}</div>
                            <div class="text-xs text-gray-400">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $role->role_id }}</code>
                                &middot; {{ $role->users_count }} {{ $role->users_count === 1 ? 'Benutzer' : 'Benutzer' }}
                            </div>
                        </div>

                        <a href="{{ route('admin.roles.edit', $role) }}"
                           class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">
                            Bearbeiten
                        </a>

                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                              onsubmit="return confirm('Rolle „{{ $role->name }}“ wirklich löschen? Die Zuordnung zu {{ $role->users_count }} Benutzer(n) geht verloren.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">
                                Löschen
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
