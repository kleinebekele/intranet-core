<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-4xl">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-6 flex items-center justify-between">
            <p class="text-gray-600">
                Lege Benutzer an, weise ihnen Rollen zu und verschicke Passwort-Links.
                Die <span class="font-medium">E-Mail</span> ist nach dem Anlegen fest.
            </p>
            <a href="{{ route('admin.users.create') }}"
               class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Neuer Benutzer
            </a>
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
                    @foreach ($users as $user)
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
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="rounded-md px-2.5 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">Bearbeiten</a>

                                    <form method="POST" action="{{ route('admin.users.reset', $user) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-md px-2.5 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                                            Reset senden
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                          onsubmit="return confirm('Benutzer „{{ $user->name }}“ wirklich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-md px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">
                                            Löschen
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
