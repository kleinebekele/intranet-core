<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="email-abbrechen" method="post" action="{{ route('profile.email.abbrechen') }}">
        @csrf
        @method('delete')
    </form>

    @php
        $nurMicrosoft = $user->nurUeberMicrosoft();
        $mitPasswort = $user->password !== null;
    @endphp

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6"
          x-data="{ email: @js(old('email', $user->email)), original: @js($user->email) }">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            @if ($nurMicrosoft)
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full bg-gray-50" :value="$user->email" readonly autocomplete="username" />
                <p class="mt-2 text-sm text-gray-600">Deine Adresse kommt aus deinem Microsoft-Konto und lässt sich nur dort ändern.</p>
            @else
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" x-model="email" required autocomplete="username" />
                <p class="mt-2 text-sm text-gray-600">Eine neue Adresse gilt erst, wenn du den Link bestätigst, den wir an sie schicken.</p>
            @endif
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user->email_neu)
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p>
                        Wartet auf Bestätigung: <strong>{{ $user->email_neu }}</strong>
                        (angefragt {{ $user->email_neu_am?->diffForHumans() }}). Bis dahin gilt weiter {{ $user->email }}.
                    </p>
                    <button form="email-abbrechen" class="mt-1 underline hover:text-amber-900">Änderung zurückziehen</button>
                </div>
            @endif
        </div>

        @if (! $nurMicrosoft && $mitPasswort)
            <div x-show="email.trim().toLowerCase() !== original.toLowerCase()" x-cloak>
                <x-input-label for="current_password_profil" value="Aktuelles Passwort" />
                <x-text-input id="current_password_profil" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <p class="mt-2 text-sm text-gray-600">Zum Ändern der E-Mail brauchen wir dein Passwort.</p>
                <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
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
