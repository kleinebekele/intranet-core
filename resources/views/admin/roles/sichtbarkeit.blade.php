<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Systemeinstellungen</h1>
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
                <h2 class="text-lg font-medium text-gray-800">Sichtbarkeit: {{ $role->name }}</h2>
                <div class="text-xs text-gray-400">
                    <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $role->role_id }}</code>
                    &middot; Welche Unterseiten sehen Mitglieder dieser Rolle, und wie weit dürfen sie dort gehen?
                    Lesen &lt; Bearbeiten &lt; Verwalten (anlegen/löschen). Dieselbe Einstellung wie unter „Module", von der Rolle aus.
                </div>
            </div>
            <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                <i class='bx bx-arrow-back'></i> Zurück zu den Rollen
            </a>
        </div>

        @include('admin.roles._sichtbarkeit_formular')
    </div>
</x-app-layout>
