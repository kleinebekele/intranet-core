{{--
    Rollen-Häkchen für „Benutzer anlegen/bearbeiten", nach Herkunft gruppiert:
    System zuerst, darunter je Modul ein Block, zuletzt die von Hand angelegten.
    Erwartet: $gruppen (Role::nachHerkunft), $selectedRoles (role_id[]).
--}}
<div class="mt-2 space-y-4">
    @foreach ($gruppen as $gruppenName => $rollenDerGruppe)
        @php
            $erste = $rollenDerGruppe->first();
            $inaktiv = $erste->gehoertZuModul() && $rollenDerGruppe->every(fn ($r) => ! $r->istAktiv());
        @endphp
        <div>
            <p class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <i class='bx {{ $gruppenName === 'System' ? 'bx-lock-alt' : ($erste->gehoertZuModul() ? 'bx-cube' : 'bx-group') }}'></i>
                {{ $gruppenName }}
                @if ($inaktiv)
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium normal-case tracking-normal text-amber-700"
                          title="Das Modul ist deaktiviert oder nicht installiert – seine Rollen gelten gerade nicht.">Modul inaktiv</span>
                @endif
            </p>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($rollenDerGruppe as $role)
                    @php
                        $isBaseline = $role->role_id === 'user';
                    @endphp
                    <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 {{ $isBaseline ? 'bg-gray-50' : '' }}"
                           @if ($role->plattformweit) title="Plattformweite Rolle – gilt in allen Modulen" @endif>
                        <input type="checkbox" name="roles[]" value="{{ $role->role_id }}"
                               @checked($isBaseline || in_array($role->role_id, $selectedRoles))
                               @disabled($isBaseline)
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">
                            {{ $role->name }}@if ($isBaseline) <span class="text-xs text-gray-400">(automatisch)</span>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
