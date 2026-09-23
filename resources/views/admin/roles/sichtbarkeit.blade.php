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
                <h2 class="text-lg font-medium text-gray-800">Sichtbarkeit: {{ $role->name }}</h2>
                <div class="text-xs text-gray-400">
                    <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $role->role_id }}</code>
                    &middot; Welche Unterseiten sehen Mitglieder dieser Rolle? Dieselbe Einstellung wie unter „Module", von der Rolle aus.
                </div>
            </div>
            <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                <i class='bx bx-arrow-back'></i> Zurück zu den Rollen
            </a>
        </div>

        @unless ($role->istAktiv())
            <div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                Das Modul „{{ $role->modul }}" dieser Rolle ist abgeschaltet – die Rolle gilt gerade nicht,
                die Häkchen wirken erst wieder, wenn das Modul an ist.
            </div>
        @endunless

        @if ($module->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Kein Modul bietet Unterseiten für diese Rolle an.
            </div>
        @else
            <form method="POST" action="{{ route('admin.roles.sichtbarkeit.update', $role) }}">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($module as $eintrag)
                        @php
                            $modul = $eintrag['modul'];
                        @endphp
                        <div class="rounded-xl border bg-white {{ $modul->key === $role->modul ? 'border-indigo-200' : 'border-gray-200' }}">
                            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3">
                                <x-module-icon :name="$modul->icon" class="text-lg text-gray-400" />
                                <span class="font-medium text-gray-700">{{ $modul->name }}</span>
                                @unless ($modul->is_enabled)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">abgeschaltet</span>
                                @endunless
                                @if ($modul->admins_only)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                                          title="Das ganze Modul ist nur für Admins – Rollen wirken hier nicht">
                                        <i class='bx bx-lock-alt'></i> nur Admins
                                    </span>
                                @endif
                                @if ($eintrag['fremd'])
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                                          title="Die Rolle gehört einem anderen Modul – bestehende Zuordnungen lassen sich hier nur noch entfernen">
                                        nur entfernen
                                    </span>
                                @endif
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @foreach ($eintrag['items'] as $item)
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-3 px-4 py-2 hover:bg-gray-50">
                                            <input type="checkbox" name="items[]" value="{{ $item->id }}"
                                                   @checked($item->roles->contains('role_id', $role->role_id))
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="min-w-0 flex-1 text-sm text-gray-800">
                                                @if ($item->group_label)
                                                    <span class="text-gray-400">{{ $item->group_label }} ›</span>
                                                @endif
                                                {{ $item->label }}
                                            </span>
                                            @if ($item->admins_only)
                                                <span class="inline-flex items-center gap-1 text-xs text-amber-600"
                                                      title="Dieser Punkt ist nur für Admins – das Häkchen wirkt erst, wenn der Schalter unter „Module" aus ist">
                                                    <i class='bx bx-lock-alt'></i> nur Admins
                                                </span>
                                            @endif
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <i class='bx bx-save text-base'></i>
                        Speichern
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Abbrechen
                    </a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
