<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-lg">
        @include('admin.partials.tabs')

        <h2 class="text-lg font-medium text-gray-800 mb-4">Rolle bearbeiten</h2>

        <form method="POST" action="{{ route('admin.roles.update', $role) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700">Schlüssel</label>
                <div class="mt-1 flex items-center rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <code class="text-sm text-gray-600">{{ $role->role_id }}</code>
                    <span class="ml-auto text-xs text-gray-400">fest</span>
                </div>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Anzeigename</label>
                <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Speichern
                </button>
                <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
