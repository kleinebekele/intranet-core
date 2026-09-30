{{--
    Nach der Passwort-Anmeldung: einmal anbieten, einen Passkey anzulegen.
    Erscheint nur, wenn der Benutzer noch keinen hat, nicht "Nicht mehr fragen"
    gewählt hat und der Browser Passkeys kann. Im Profil geht es jederzeit.
--}}
@php
    $passkeyAngebot = app(\App\Support\Passkeys::class)->angebotZeigen(request(), auth()->user());
@endphp

@if ($passkeyAngebot)
    <x-passkey-script />
    <div x-data="{
            offen: window.Passkey.verfuegbar(),
            laeuft: false,
            meldung: '',
            async einrichten() {
                this.laeuft = true;
                this.meldung = '';
                try {
                    const optionen = await Passkey.post(@js(route('profile.passkeys.optionen')));
                    const antwort = await Passkey.anlegen(optionen);
                    await Passkey.post(@js(route('profile.passkeys.speichern')), { name: Passkey.geraetename(), antwort });
                    window.location.reload();
                } catch (e) {
                    this.meldung = e.name === 'InvalidStateError'
                        ? 'Auf diesem Gerät ist bereits ein Passkey für dein Konto hinterlegt.'
                        : (Passkey.abgebrochen(e) ? 'Abgebrochen – du kannst es erneut versuchen oder später im Profil einrichten.' : e.message);
                } finally {
                    this.laeuft = false;
                }
            },
            async nichtMehr() {
                try { await Passkey.post(@js(route('profile.passkeys.angebot-aus'))); } catch (e) {}
                this.offen = false;
            },
         }"
         x-show="offen" x-cloak
         x-transition.opacity.duration.150ms
         @keydown.escape.window="offen = false"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50"
         role="dialog" aria-modal="true" aria-label="Passkey einrichten">
        <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
            <div class="px-5 py-4">
                <h3 class="flex items-center gap-2 font-semibold text-gray-800">
                    <svg class="h-6 w-6 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33"/>
                    </svg>
                    Künftig ohne Passwort anmelden?
                </h3>
                <p class="mt-2 text-sm text-gray-700">
                    Mit einem Passkey meldest du dich per Face ID, Fingerabdruck, Windows Hello oder
                    Geräte-PIN an – ohne Passwort und ohne Zwei-Faktor-Code. Das dauert nur einen Moment.
                </p>
                <p class="mt-3 text-sm text-red-600" x-show="meldung" x-text="meldung"></p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t px-5 py-3">
                <button type="button" @click="nichtMehr()" :disabled="laeuft"
                        class="mr-auto rounded-md px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                    Nicht mehr fragen
                </button>
                <button type="button" @click="offen = false" :disabled="laeuft"
                        class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                    Später
                </button>
                <button type="button" @click="einrichten()" :disabled="laeuft"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                    <span x-show="!laeuft">Jetzt einrichten</span>
                    <span x-show="laeuft" x-cloak>Bitte am Gerät bestätigen …</span>
                </button>
            </div>
        </div>
    </div>
@endif
