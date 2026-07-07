<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    @php($selectedRoles = old('roles', $user->roles->pluck('role_id')->all()))

    <div class="max-w-lg">
        @include('admin.partials.tabs')

        <h2 class="text-lg font-medium text-gray-800 mb-4">Benutzer bearbeiten</h2>

        <form method="POST" action="{{ route('admin.users.update', $user) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700">E-Mail</label>
                <div class="mt-1 flex items-center rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <span class="text-sm text-gray-600">{{ $user->email }}</span>
                    <span class="ml-auto text-xs text-gray-400">unveränderbar</span>
                </div>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                                       @checked($isBaseline || in_array($role->role_id, $selectedRoles))
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
                <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Administrator</span>
            </label>
            @error('is_admin') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save text-base'></i>
                    Speichern
                </button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>

        {{-- Passwort-Reset-Link als eigenes Formular (Formulare dürfen nicht verschachtelt sein). --}}
        <form method="POST" action="{{ route('admin.users.reset', $user) }}"
              class="mt-4 flex items-center justify-between rounded-xl border border-gray-200 bg-white px-6 py-4">
            @csrf
            <div class="text-sm text-gray-600">
                Passwort-Reset-Link an <span class="font-medium">{{ $user->email }}</span> senden.
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                <i class='bx bx-mail-send text-base'></i>
                Link senden
            </button>
        </form>

        {{-- TOTP-Reset: nur anzeigen, wenn der Benutzer eine Authenticator-App eingerichtet hat. --}}
        @if ($user->hasTotp())
            <form method="POST" action="{{ route('admin.users.reset-totp', $user) }}"
                  onsubmit="return confirm('TOTP für {{ $user->email }} wirklich zurücksetzen? Der Benutzer meldet sich danach wieder mit Mail-Codes an.');"
                  class="mt-4 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-6 py-4">
                @csrf
                <div class="text-sm text-amber-800">
                    Authenticator-App (TOTP) aktiv seit {{ $user->totp_confirmed_at->format('d.m.Y') }} —
                    bei Handy-Verlust hier zurücksetzen.
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    <i class='bx bx-shield-x text-base'></i>
                    TOTP zurücksetzen
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
