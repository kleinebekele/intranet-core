{{-- Stufen je Unterseite für eine Rolle. Genutzt von der eigenen Seite
     (sichtbarkeit.blade.php) und vom Modal in der Rollenliste, das diesen
     Baustein per fetch nachlädt. Erwartet $role, $module; $mitKnoepfen blendet
     Speichern/Abbrechen ein (das Modal hat eigene Knöpfe). --}}
@unless ($role->istAktiv())
    <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
        Das Modul „{{ $role->modul }}" dieser Rolle ist abgeschaltet – die Rolle gilt gerade nicht,
        die Auswahl wirkt erst wieder, wenn das Modul an ist.
    </div>
@endunless

@if ($module->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
        Kein Modul bietet Unterseiten für diese Rolle an.
    </div>
@else
    <form method="POST" action="{{ route('admin.roles.sichtbarkeit.update', $role) }}" data-sichtbarkeit-formular>
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
                            <li class="flex items-center gap-3 px-4 py-2">
                                <span class="min-w-0 flex-1 text-sm text-gray-800">
                                    @if ($item->group_label)
                                        <span class="text-gray-400">{{ $item->group_label }} ›</span>
                                    @endif
                                    {{ $item->label }}
                                    @if ($item->admins_only)
                                        <span class="ml-1 inline-flex items-center gap-1 text-xs text-amber-600"
                                              title="Dieser Punkt ist nur für Admins – die Auswahl wirkt erst, wenn der Schalter unter „Module" aus ist">
                                            <i class='bx bx-lock-alt'></i> nur Admins
                                        </span>
                                    @endif
                                </span>
                                <x-stufen-wahl :name="'stufen['.$item->id.']'"
                                               :stufe="$item->roles->firstWhere('role_id', $role->role_id)?->pivot->stufe"
                                               :nur-entfernen="$eintrag['fremd']" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        @if ($mitKnoepfen ?? true)
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
        @endif
    </form>
@endif
