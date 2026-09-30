@php
    // Der Microsoft-Knopf erscheint nur, wenn in der .env Zugangsdaten
    // hinterlegt sind. Instanzen ohne Microsoft 365 sehen die Anmeldeseite
    // unverändert.
    $microsoft = app(\App\Support\Microsoft\MicrosoftSso::class)->aktiv();
@endphp

<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Schnelle Wege: Microsoft (falls eingerichtet) und Passkey. Der
         Passkey-Knopf erscheint erst, wenn der Browser Passkeys kann – ohne
         Microsoft bleibt der Block bis dahin unsichtbar. --}}
    <div id="anmelden-schnell" class="mb-6" @unless ($microsoft) hidden @endunless>
        @if ($microsoft)
            <x-input-error :messages="$errors->get('microsoft')" class="mb-3" />

            <a href="{{ route('auth.microsoft.start') }}"
               class="flex w-full items-center justify-center gap-3 rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{-- Das Microsoft-Signet: vier Quadrate, wie von Microsoft für
                     den Anmelde-Knopf vorgegeben. --}}
                <svg class="h-5 w-5" viewBox="0 0 21 21" aria-hidden="true">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                </svg>
                Mit Microsoft anmelden
            </a>
        @endif

        {{-- Hülle mit hidden statt hidden am Knopf: dort würde die Klasse
             "flex" das Attribut überstimmen. --}}
        <div id="passkey-bereich" hidden>
        <button type="button" id="passkey-knopf"
                class="@if ($microsoft) mt-3 @endif flex w-full items-center justify-center gap-3 rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60">
            <svg class="h-5 w-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33"/>
            </svg>
            Mit Passkey anmelden
        </button>
        <p id="passkey-fehler" class="mt-2 text-sm text-red-600" hidden></p>
        </div>

        <div class="relative mt-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="bg-white px-3 text-xs uppercase tracking-wide text-gray-400">oder</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username webauthn" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <x-passkey-script />
    <script>
        (function () {
            if (!window.Passkey.verfuegbar()) {
                return;
            }

            const knopf = document.getElementById('passkey-knopf');
            const fehler = document.getElementById('passkey-fehler');
            let laufend = null;

            document.getElementById('anmelden-schnell').hidden = false;
            document.getElementById('passkey-bereich').hidden = false;

            const zeigeFehler = (text) => {
                fehler.textContent = text;
                fehler.hidden = !text;
            };

            const anmelden = async (zusatz) => {
                const optionen = await Passkey.post(@json(route('auth.passkey.optionen')));
                const antwort = await Passkey.anmelden(optionen, zusatz);
                const ergebnis = await Passkey.post(@json(route('auth.passkey.anmelden')), {
                    antwort,
                    remember: document.getElementById('remember_me').checked,
                });
                window.location.href = ergebnis.weiter;
            };

            // Knopf: das Gerät fragt aktiv nach Face ID, Windows Hello & Co.
            knopf.addEventListener('click', async () => {
                laufend?.abort();
                laufend = null;
                zeigeFehler('');
                knopf.disabled = true;

                try {
                    await anmelden();
                } catch (e) {
                    zeigeFehler(Passkey.abgebrochen(e)
                        ? 'Abgebrochen – oder auf diesem Gerät ist noch kein Passkey für das Intranet hinterlegt (einrichten unter Profil).'
                        : e.message);
                } finally {
                    knopf.disabled = false;
                }
            });

            // Nebenbei: Passkeys im Vorschlagsmenü des E-Mail-Felds anbieten
            // (Autofill). Läuft still im Hintergrund, bis jemand einen wählt.
            PublicKeyCredential.isConditionalMediationAvailable?.().then((geht) => {
                if (!geht) {
                    return;
                }
                laufend = new AbortController();
                anmelden({ mediation: 'conditional', signal: laufend.signal })
                    .catch((e) => { if (!Passkey.abgebrochen(e)) zeigeFehler(e.message); });
            });
        })();
    </script>
</x-guest-layout>
