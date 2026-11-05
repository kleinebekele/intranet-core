<x-guest-layout>
    @if ($abgelaufen || $errors->first('email') === __('passwords.token'))
        {{-- Link abgelaufen oder schon benutzt: kein Passwortformular, sondern genau
             das, was „Passwort vergessen" täte – mit der Adresse aus dem Link. --}}
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-gray-700">
            <div class="font-semibold text-gray-900">Dieser Link ist abgelaufen oder wurde schon benutzt.</div>
            <div class="mt-1">Kein Problem: Mit einem Klick schicken wir Ihnen einen neuen Link.</div>
        </div>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autocomplete="username" />
            </div>

            <button type="submit"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                </svg>
                Neuen Link per E-Mail senden
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Zurück zur Anmeldung</a>
        </div>
    @else
        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-password-input class="mt-1" id="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

                <x-password-input class="mt-1" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />

                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-primary-button>
                    {{ __('Reset Password') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
