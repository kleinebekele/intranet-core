<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-lg">
        @include('admin.partials.tabs')

        <h2 class="text-lg font-medium text-gray-800 mb-4">Neuen Benutzer anlegen</h2>

        <form method="POST" action="{{ route('admin.users.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">E-Mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-400">Kann später nicht mehr geändert werden.</p>
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700">Rollen</span>
                @if ($roles->isEmpty())
                    <p class="mt-1 text-sm text-gray-400">Noch keine Rollen vorhanden – lege welche im Tab „Rollen" an.</p>
                @else
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @foreach ($roles as $role)
                            @php($isBaseline = $role->role_id === 'user')
                            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 {{ $isBaseline ? 'bg-gray-50' : '' }}">
                                <input type="checkbox" name="roles[]" value="{{ $role->role_id }}"
                                       @checked($isBaseline || in_array($role->role_id, old('roles', [])))
                                       @disabled($isBaseline)
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">
                                    {{ $role->name }}@if ($isBaseline) <span class="text-xs text-gray-400">(automatisch)</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin'))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Administrator</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-user-plus text-base'></i>
                    Anlegen &amp; einladen
                </button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
            <p class="text-xs text-gray-400">Der Benutzer erhält eine Willkommens-Mail mit Link zum Passwort-Setzen.</p>
        </form>
    </div>
</x-app-layout>
