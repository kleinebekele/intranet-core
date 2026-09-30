@php
    // Der Microsoft-Knopf erscheint nur, wenn in der .env Zugangsdaten
    // hinterlegt sind. Instanzen ohne Microsoft 365 sehen die Anmeldeseite
    // unverändert.
    $microsoft = app(\App\Support\Microsoft\MicrosoftSso::class)->aktiv();

    // Hat sich auf diesem Gerät zuletzt jemand per Passkey angemeldet (Cookie),
    // steht der Passkey vorn; E-Mail und Passwort gibt es auf Klick. Nach einem
    // Fehlversuch mit Passwort gleich wieder das Formular zeigen.
    $gemerkt = $errors->any() ? null : app(\App\Support\Passkeys::class)->gemerkterBenutzer(request());

    $fingerabdruck = 'M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33';
@endphp

<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($microsoft)
        <div class="mb-6">
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

            <div class="relative mt-6">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 text-xs uppercase tracking-wide text-gray-400">oder</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Hinweise zur Passkey-Anmeldung (Abbruch, passt nicht …). --}}
    <p id="passkey-fehler" class="mb-4 text-sm text-red-600" hidden></p>

    @if ($gemerkt)
        {{-- Bekanntes Gerät: der Passkey zuerst. Das Skript blendet diesen
             Block ein (und das Formular aus), wenn der Browser Passkeys kann. --}}
        <div id="passkey-gemerkt" hidden>
            <button type="button" id="passkey-gemerkt-knopf" data-email="{{ $gemerkt->email }}"
                    class="flex w-full items-center justify-center gap-3 rounded-md bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $fingerabdruck }}"/>
                </svg>
                Mit Passkey anmelden
            </button>
            <p class="mt-2 text-center text-sm text-gray-600">als <b>{{ $gemerkt->email }}</b></p>

            <div class="mt-6 text-center">
                <button type="button" id="passkey-formular-zeigen"
                        class="text-sm text-gray-600 underline hover:text-gray-900">
                    Mit E-Mail und Passwort anmelden
                </button>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="login-formular">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username webauthn" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />

            {{-- Erscheint erst, wenn zur eingegebenen Adresse ein Passkey
                 hinterlegt ist. Hülle mit hidden statt hidden am Knopf: dort
                 würde die Klasse "flex" das Attribut überstimmen. --}}
            <div id="passkey-bereich" class="mt-3" hidden>
                <button type="button" id="passkey-knopf"
                        class="flex w-full items-center justify-center gap-3 rounded-md border border-indigo-300 bg-indigo-50 px-4 py-2.5 text-sm font-medium text-indigo-700 shadow-sm transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $fingerabdruck }}"/>
                    </svg>
                    Mit Passkey anmelden (ohne Passwort)
                </button>
            </div>
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

            const fehler = document.getElementById('passkey-fehler');
            const formular = document.getElementById('login-formular');
            const bereich = document.getElementById('passkey-bereich');
            const email = document.getElementById('email');
            const gemerkt = document.getElementById('passkey-gemerkt');
            let laufend = null;
            let geprueft = null;

            const zeigeFehler = (text) => {
                fehler.textContent = text;
                fehler.hidden = !text;
            };

            const formularZeigen = () => {
                if (gemerkt) {
                    gemerkt.hidden = true;
                }
                formular.hidden = false;
                email.focus();
            };

            // Knopf unter dem E-Mail-Feld nur, wenn zur Adresse ein Passkey gehört.
            const pruefen = async () => {
                const adresse = email.value.trim();
                if (adresse === geprueft) {
                    return;
                }
                geprueft = adresse;

                if (!email.checkValidity() || adresse === '') {
                    bereich.hidden = true;
                    return;
                }
                try {
                    const antwort = await Passkey.post(@json(route('auth.passkey.pruefen')), { email: adresse });
                    if (email.value.trim() === adresse) {
                        bereich.hidden = !antwort.passkey;
                    }
                } catch (e) {
                    bereich.hidden = true;
                }
            };

            let warte = null;
            email.addEventListener('input', () => { clearTimeout(warte); warte = setTimeout(pruefen, 400); });
            email.addEventListener('change', pruefen);
            // Vom Browser vorausgefüllt (Autofill, alter Wert nach Fehler)?
            setTimeout(pruefen, 300);

            const anmelden = async (zusatz, adresse) => {
                try {
                    const optionen = await Passkey.post(@json(route('auth.passkey.optionen')), { email: adresse || null });
                    const antwort = await Passkey.anmelden(optionen, zusatz);
                    const ergebnis = await Passkey.post(@json(route('auth.passkey.anmelden')), {
                        antwort,
                        remember: document.getElementById('remember_me').checked,
                    });
                    window.location.href = ergebnis.weiter;
                } catch (e) {
                    // Passkey passt nicht: zum Passwort-Formular, danach bietet
                    // das Intranet an, einen neuen anzulegen.
                    if (e.daten && e.daten.passt_nicht) {
                        formularZeigen();
                        if (adresse) {
                            email.value = adresse;
                        }
                    }
                    throw e;
                }
            };

            const knopfAnmeldung = (knopf, adresse) => async () => {
                laufend?.abort();
                laufend = null;
                zeigeFehler('');
                knopf.disabled = true;

                try {
                    await anmelden(null, adresse());
                } catch (e) {
                    zeigeFehler(Passkey.abgebrochen(e)
                        ? 'Abgebrochen. Liegt der Passkey auf einem anderen Gerät, kannst du im Fenster auch das Handy wählen.'
                        : e.message);
                } finally {
                    knopf.disabled = false;
                }
            };

            const knopf = document.getElementById('passkey-knopf');
            knopf.addEventListener('click', knopfAnmeldung(knopf, () => email.value.trim()));

            if (gemerkt) {
                const gemerktKnopf = document.getElementById('passkey-gemerkt-knopf');
                gemerktKnopf.addEventListener('click', knopfAnmeldung(gemerktKnopf, () => gemerktKnopf.dataset.email));
                document.getElementById('passkey-formular-zeigen').addEventListener('click', formularZeigen);
                formular.hidden = true;
                gemerkt.hidden = false;
                gemerktKnopf.focus();
            }

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
