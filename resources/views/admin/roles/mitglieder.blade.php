<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div>
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-medium text-gray-800">
                    Mitglieder: {{ $role->name }}
                    @if ($role->istVerwaltet())
                        <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 align-middle">
                            <i class='bx bx-refresh'></i> Abgleich „{{ $role->quelle }}"
                        </span>
                    @endif
                </h2>
                <div class="text-xs text-gray-400">
                    <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $role->role_id }}</code>
                    &middot; {{ $mitglieder->count() }} Mitglieder
                </div>
            </div>
            <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                <i class='bx bx-arrow-back'></i> Zurück zu den Rollen
            </a>
        </div>

        @if ($role->istVerwaltet())
            <div class="mb-6 rounded-lg bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-800">
                Diese Rolle wird vom Abgleich <span class="font-medium">{{ $role->quelle }}</span> gepflegt.
                Mitglieder kommen und gehen automatisch mit den Daten der Quelle; von Hand lässt sich hier nichts ändern.
                Wer fehlt, gehört in der Quelle nachgepflegt.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Aktuelle Mitglieder --}}
            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-4 py-3 font-medium text-gray-700">Mitglieder</div>
                @if ($mitglieder->isEmpty())
                    <div class="p-6 text-sm text-gray-400">Noch niemand in dieser Rolle.</div>
                @else
                    <ul class="divide-y divide-gray-100 max-h-[70vh] overflow-y-auto">
                        @foreach ($mitglieder as $user)
                            <li class="flex items-center gap-3 px-4 py-2">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm text-gray-800">
                                        {{ $user->name }}
                                        @if ($user->istGesperrt())
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">gesperrt</span>
                                        @endif
                                    </div>
                                    <div class="truncate text-xs text-gray-400">{{ $user->email }}</div>
                                </div>
                                @if (! $role->istVerwaltet() && $role->role_id !== 'user')
                                    <form method="POST" action="{{ route('admin.roles.mitglieder.destroy', [$role, $user]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Entfernen"
                                                class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <i class='bx bx-x text-lg'></i>
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Hinzufügen --}}
            @unless ($role->istVerwaltet())
                <div class="rounded-xl border border-gray-200 bg-white" x-data="{ gewaehlt: 0 }">
                    <div class="border-b border-gray-100 px-4 py-3 font-medium text-gray-700">Hinzufügen</div>

                    <form method="GET" action="{{ route('admin.roles.mitglieder', $role) }}" class="flex gap-2 px-4 py-3 border-b border-gray-100">
                        <input type="search" name="q" value="{{ $suche }}" placeholder="Name oder E-Mail suchen …"
                               class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="submit" class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200">
                            <i class='bx bx-search'></i>
                        </button>
                    </form>

                    @if ($kandidaten->isEmpty())
                        <div class="p-6 text-sm text-gray-400">
                            {{ $suche !== '' ? 'Niemand gefunden, der die Rolle noch nicht hat.' : 'Alle Benutzer haben diese Rolle bereits.' }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.roles.mitglieder.store', $role) }}">
                            @csrf
                            <ul class="divide-y divide-gray-100 max-h-[55vh] overflow-y-auto">
                                @foreach ($kandidaten as $user)
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-3 px-4 py-2 hover:bg-gray-50">
                                            <input type="checkbox" name="user_ids[]" value="{{ $user->id }}"
                                                   @change="gewaehlt += $event.target.checked ? 1 : -1"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm text-gray-800">{{ $user->name }}</span>
                                                <span class="block truncate text-xs text-gray-400">{{ $user->email }}</span>
                                            </span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                                <span class="text-xs text-gray-400">
                                    {{ $kandidaten->count() >= 200 ? 'Nur die ersten 200 – bitte Suche eingrenzen.' : $kandidaten->count().' Treffer' }}
                                </span>
                                <button type="submit" :disabled="gewaehlt === 0"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40">
                                    <i class='bx bx-user-plus text-base'></i>
                                    <span x-text="gewaehlt > 0 ? gewaehlt + ' hinzufügen' : 'Hinzufügen'"></span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
