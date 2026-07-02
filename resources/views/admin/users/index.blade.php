<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-4xl">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="mb-6 flex items-center justify-between">
            <p class="text-gray-600">
                Lege Benutzer an, weise ihnen Rollen zu und verschicke Passwort-Links.
                Die <span class="font-medium">E-Mail</span> ist nach dem Anlegen fest.
            </p>
            <a href="{{ route('admin.users.create') }}"
               class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-user-plus text-lg'></i>
                Neuer Benutzer
            </a>
        </div>

        {{-- Filterleiste: Suche (Name/E-Mail) + Rollenfilter (GET, damit der Filter in der URL steht) --}}
        <form method="GET" action="{{ route('admin.users.index') }}"
              class="mb-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[12rem]">
                <label for="search" class="block text-xs font-medium text-gray-500">Suche (Name oder E-Mail)</label>
                <input id="search" name="search" type="text" value="{{ $search }}"
                       placeholder="z. B. Schmidt oder @firma.de"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>
            <div>
                <label for="role" class="block text-xs font-medium text-gray-500">Rolle</label>
                <select id="role" name="role"
                        class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">Alle Rollen</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->role_id }}" @selected($roleFilter === $role->role_id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-search text-base'></i> Filtern
            </button>
            @if ($search !== '' || $roleFilter !== '')
                <a href="{{ route('admin.users.index') }}"
                   class="px-2 py-2 text-sm text-gray-500 hover:text-gray-700">Zurücksetzen</a>
            @endif
        </form>

        <div class="mb-2 text-xs text-gray-400">
            {{ $users->count() }} Benutzer
            @if ($search !== '' || $roleFilter !== '')
                <span class="text-gray-300">·</span> gefiltert
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">E-Mail</th>
                        <th class="px-4 py-3">Rollen</th>
                        <th class="px-4 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">
                                    {{ $user->name }}
                                    @if ($user->is_admin)
                                        <span class="ml-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Admin</span>
                                    @endif
                                </div>
                                @if ($user->source)
                                    <div class="text-xs text-gray-400">Quelle: {{ $user->source }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @forelse ($user->roles as $role)
                                    <span class="mr-1 mb-1 inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $role->role_id }}</span>
                                @empty
                                    <span class="text-xs text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1 whitespace-nowrap text-xl">
                                    <a href="{{ route('admin.users.edit', $user) }}" title="Bearbeiten"
                                       class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                        <i class='bx bx-edit'></i>
                                    </a>

                                    <form method="POST" action="{{ route('admin.users.reset', $user) }}">
                                        @csrf
                                        <button type="submit" title="Passwort-Reset-Link senden"
                                                class="block rounded-md p-1.5 text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700">
                                            <i class='bx bx-mail-send'></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                          onsubmit="return confirm('Benutzer „{{ $user->name }}“ wirklich löschen?');">
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
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                Keine Benutzer gefunden. Passe Suche oder Rollenfilter an.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
