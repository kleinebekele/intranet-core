<x-guest-layout>
    @if (session('status'))
        {{-- Nach dem Absenden: kein Eingabefeld mehr – das lud dazu ein, die Adresse
             gleich noch einmal abzuschicken. Stattdessen eine Wartezeit mit Zähler;
             erst danach lässt sich der Link erneut anfordern. --}}
        <div x-data="{
                rest: 180,
                init() { const t = setInterval(() => { if (--this.rest <= 0) clearInterval(t); }, 1000); },
                get anzeige() { const m = Math.floor(this.rest / 60), s = this.rest % 60; return m + ':' + String(s).padStart(2, '0'); },
             }">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <div x-show="rest > 0" class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-4 text-center">
                <div class="text-sm text-gray-700">Bitte warte bis zu 3 Minuten, bis die Mail bei dir eintrifft.</div>
                <div class="mt-2 text-3xl font-bold tabular-nums text-indigo-700" x-text="anzeige">3:00</div>
                <div class="mt-2 text-xs text-gray-500">Schau auch im Spam-Ordner nach.</div>
            </div>

            <div x-show="rest <= 0" x-cloak class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-4 text-center text-sm text-gray-700">
                Noch keine Mail angekommen? Prüfe den Spam-Ordner und die Schreibweise der Adresse.
                <div class="mt-3">
                    <a href="{{ route('password.request') }}" class="font-semibold text-indigo-700 underline hover:text-indigo-900">Link erneut anfordern</a>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Zurück zur Anmeldung</a>
            </div>
        </div>
    @else
        <div class="mb-4 text-sm text-gray-600">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        @if (app(\App\Support\Microsoft\MicrosoftSso::class)->aktiv())
            <div class="mb-4 text-sm text-gray-600">{{ trans('auth.nur_microsoft') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-primary-button>
                    {{ __('Email Password Reset Link') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
