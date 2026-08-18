@php
    $benutzer = auth()->user();
    $microsoft = $benutzer?->microsoft_id !== null && app(\App\Support\Microsoft\MicrosoftSso::class)->aktiv();
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    @if ($microsoft)
        <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
            <p class="font-medium">Dein Konto läuft über Microsoft.</p>
            @if ($benutzer->isAdmin())
                <p class="mt-1">
                    Melde dich am besten über den Knopf „Mit Microsoft anmelden" an – dann brauchst du hier
                    kein Passwort mehr. Weil du Administrator bist, funktioniert dein Passwort trotzdem
                    weiter: So kommst du auch dann noch herein, wenn Microsoft einmal nicht erreichbar ist.
                </p>
            @else
                <p class="mt-1">
                    Die Anmeldung läuft ab jetzt über den Knopf „Mit Microsoft anmelden". Ein Passwort
                    brauchst du hier nicht mehr – die Anmeldung damit wird abgelehnt.
                </p>
            @endif
        </div>
    @endif

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
