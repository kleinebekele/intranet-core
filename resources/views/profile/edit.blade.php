<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div id="profilbild" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.profilbild-form', ['user' => $user])
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-3xl">
                    @include('profile.partials.darstellung-form', ['user' => $user])
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.two-factor-form', ['user' => $user])
                </div>
            </div>

            {{-- Konten, die nur über Microsoft hereinkommen, brauchen (und
                 dürfen) keinen Passkey: Die Anmeldung soll an Microsoft hängen. --}}
            @if (app(\App\Support\Passkeys::class)->erlaubt($user))
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.passkeys-form', ['user' => $user])
                    </div>
                </div>
            @endif

            {{-- Zusatzbereiche der Module (App\Support\Profilbereiche), z. B. Mail-Passwort. --}}
            @foreach (\App\Support\Profilbereiche::fuer($user) as $schluessel => $bereich)
                <div id="{{ $schluessel }}" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">{!! $bereich !!}</div>
                </div>
            @endforeach

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
