<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-lg">
        @include('admin.partials.tabs')

        <h2 class="text-lg font-medium text-gray-800 mb-4">Neue Rolle anlegen</h2>

        <form method="POST" action="{{ route('admin.roles.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf

            <div>
                <label for="role_id" class="block text-sm font-medium text-gray-700">Schlüssel</label>
                <input id="role_id" name="role_id" type="text" value="{{ old('role_id') }}"
                       placeholder="z. B. teacher"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm">
                <p class="mt-1 text-xs text-gray-400">
                    Kleinbuchstaben, Ziffern, „-" und „_". Kann später nicht mehr geändert werden.
                </p>
                @error('role_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Anzeigename</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}"
                       placeholder="z. B. Lehrkraft"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-plus text-base'></i>
                    Rolle anlegen
                </button>
                <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
