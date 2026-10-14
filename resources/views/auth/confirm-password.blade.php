<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        @if ($mitPasskey)
            Dieser Bereich ist besonders geschützt. Bitte bestätige zum Fortfahren mit deinem Passkey oder deinem Passwort, dass du es bist.
        @else
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        @endif
    </div>

    @if ($mitPasskey)
        {{-- Passkey zuerst; das Passwort-Formular nur auf Wunsch. --}}
        <div id="passkey-bereich">
            <button type="button" id="passkey-knopf"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-gray-800 px-4 py-3 text-sm font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91c-.57-.1-1.17.03-1.58.44L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.82c0-.6.24-1.17.66-1.59l6.4-6.4c.41-.41.54-1.01.44-1.58A6 6 0 1 1 21.75 8.25Z"/></svg>
                Mit Passkey bestätigen
            </button>
            <p id="passkey-fehler" class="mt-2 text-sm text-red-600" hidden></p>
            <div class="mt-4 text-center">
                <button type="button" id="passwort-zeigen" class="text-sm text-gray-600 underline hover:text-gray-900">Passwort eingeben</button>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.confirm') }}" id="passwort-formular" @if ($mitPasskey && ! $errors->has('password')) hidden @endif>
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>

    @if ($mitPasskey)
        <x-passkey-script />
        <script>
            (function () {
                const knopf = document.getElementById('passkey-knopf');
                const fehler = document.getElementById('passkey-fehler');
                const formular = document.getElementById('passwort-formular');

                const zeigeFehler = (text) => { fehler.textContent = text; fehler.hidden = !text; };

                document.getElementById('passwort-zeigen').addEventListener('click', () => {
                    formular.hidden = false;
                    document.getElementById('password').focus();
                });

                // Ohne WebAuthn im Browser bleibt nur das Passwort.
                if (!Passkey.verfuegbar()) {
                    document.getElementById('passkey-bereich').hidden = true;
                    formular.hidden = false;
                    return;
                }

                knopf.addEventListener('click', async () => {
                    zeigeFehler('');
                    knopf.disabled = true;
                    try {
                        const optionen = await Passkey.post(@json(route('password.confirm.passkey.optionen')));
                        const antwort = await Passkey.anmelden(optionen);
                        const ergebnis = await Passkey.post(@json(route('password.confirm.passkey')), { antwort });
                        window.location.href = ergebnis.weiter;
                    } catch (e) {
                        zeigeFehler(Passkey.abgebrochen(e)
                            ? 'Abgebrochen. Du kannst es erneut versuchen oder dein Passwort eingeben.'
                            : e.message);
                        knopf.disabled = false;
                    }
                });
            })();
        </script>
    @endif
</x-guest-layout>
