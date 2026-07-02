<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <div class="max-w-3xl" x-data="{ detachOpen: false, detachAction: '', detachRole: '', detachCount: 0 }">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="mb-6 flex items-center justify-between">
            <p class="text-gray-600">
                Rollen bündeln Berechtigungen. <span class="font-medium">admin</span> und
                <span class="font-medium">user</span> sind feste System-Rollen; jeder Benutzer hat automatisch
                <span class="font-medium">user</span>.
            </p>
            <a href="{{ route('admin.roles.create') }}"
               class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-plus text-lg'></i>
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
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 text-xl">
                            <i class='bx bx-id-card leading-none'></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-800">
                                {{ $role->name }}
                                @if ($role->isSystem())
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 align-middle">
                                        <i class='bx bx-lock-alt'></i> System
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $role->role_id }}</code>
                                &middot; {{ $role->users_count }} Benutzer
                            </div>
                        </div>

                        <div class="flex items-center gap-1 text-xl">
                            <a href="{{ route('admin.roles.edit', $role) }}" title="Bearbeiten"
                               class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                <i class='bx bx-edit'></i>
                            </a>

                            @if ($role->isSystem())
                                <span title="System-Rolle – geschützt" class="p-1.5 text-gray-300">
                                    <i class='bx bx-lock-alt'></i>
                                </span>
                            @elseif ($role->users_count > 0)
                                <button type="button" title="Alle Zuweisungen aufheben"
                                        @click="detachOpen = true; detachAction = '{{ route('admin.roles.detach-all', $role) }}'; detachRole = @js($role->name); detachCount = {{ $role->users_count }}"
                                        class="rounded-md p-1.5 text-amber-500 hover:bg-amber-50 hover:text-amber-700">
                                    <i class='bx bx-unlink'></i>
                                </button>
                            @else
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                      onsubmit="return confirm('Rolle „{{ $role->name }}“ wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Löschen"
                                            class="block rounded-md p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Hinweisfenster: Alle Zuweisungen aufheben --}}
        <div x-show="detachOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/40" @click="detachOpen = false"></div>

            <div class="relative z-50 w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 text-2xl">
                        <i class='bx bx-error leading-none'></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-medium text-gray-800">Alle Zuweisungen aufheben?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Du hebst alle Zuweisungen der Rolle
                            <span class="font-medium" x-text="detachRole"></span>
                            (<span x-text="detachCount"></span> Benutzer) auf.
                        </p>
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            Achtung: Das Aufheben von Rollen kann dazu führen, dass gewisse Module nicht mehr
                            ordnungsgemäß arbeiten.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="detachOpen = false"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        Abbrechen
                    </button>
                    <form :action="detachAction" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            <i class='bx bx-unlink text-base'></i>
                            Ja, aufheben
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
