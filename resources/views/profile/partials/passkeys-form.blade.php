<section id="passkeys">
    <header>
        <h2 class="text-lg font-medium text-gray-900">Passkeys</h2>
        <p class="mt-1 text-sm text-gray-600">
            Anmelden ohne Passwort – mit Face ID, Touch ID, Windows Hello, der Geräte-PIN oder einem
            Sicherheitsschlüssel. Ein Passkey ersetzt Passwort <b>und</b> Zwei-Faktor-Code. Lege ihn
            auf jedem Gerät an, mit dem du dich anmeldest (iPhone und Mac teilen ihn über den
            iCloud-Schlüsselbund).
        </p>
    </header>

    @if ($user->passkeys->isEmpty())
        <p class="mt-4 text-sm text-gray-700">Noch kein Passkey hinterlegt.</p>
    @else
        <ul class="mt-4 divide-y divide-gray-200 rounded-md border border-gray-200">
            @foreach ($user->passkeys->sortBy('created_at') as $passkey)
                <li class="flex items-center justify-between gap-4 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $passkey->name }}</p>
                        <p class="text-xs text-gray-500">
                            angelegt {{ $passkey->created_at->format('d.m.Y') }}
                            · zuletzt benutzt {{ $passkey->zuletzt_benutzt_am?->format('d.m.Y H:i') ?? 'noch nie' }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('profile.passkeys.entfernen', $passkey) }}"
                          onsubmit="return confirm('Passkey wirklich entfernen? Auf dem Gerät bleibt er gespeichert, funktioniert hier aber nicht mehr.')">
                        @csrf
                        @method('DELETE')
                        <x-secondary-button type="submit">Entfernen</x-secondary-button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <div id="passkey-anlegen" class="mt-6 space-y-3">
        <p id="passkey-kein-browser" class="text-sm text-gray-500" hidden>
            Dieser Browser unterstützt keine Passkeys.
        </p>
        <div>
            <x-input-label for="passkey_name" value="Bezeichnung (z. B. iPhone, Laptop Büro)" />
            <x-text-input id="passkey_name" type="text" maxlength="100" class="mt-1 block w-full max-w-xs" />
        </div>
        <div>
            <x-input-label for="passkey_password" value="Aktuelles Passwort" />
            <x-text-input id="passkey_password" type="password" autocomplete="current-password" class="mt-1 block w-full max-w-xs" />
        </div>
        <p id="passkey-meldung" class="text-sm text-red-600" hidden></p>
        <x-primary-button type="button" id="passkey-anlegen-knopf">Passkey auf diesem Gerät anlegen</x-primary-button>
    </div>
</section>

<x-passkey-script />
<script>
    (function () {
        const knopf = document.getElementById('passkey-anlegen-knopf');
        const meldung = document.getElementById('passkey-meldung');

        if (!window.Passkey.verfuegbar()) {
            document.getElementById('passkey-kein-browser').hidden = false;
            knopf.disabled = true;
            return;
        }

        knopf.addEventListener('click', async () => {
            meldung.hidden = true;
            knopf.disabled = true;

            try {
                const optionen = await Passkey.post(@json(route('profile.passkeys.optionen')), {
                    password: document.getElementById('passkey_password').value,
                });
                const antwort = await Passkey.anlegen(optionen);
                await Passkey.post(@json(route('profile.passkeys.speichern')), {
                    name: document.getElementById('passkey_name').value || Passkey.geraetename(),
                    antwort,
                });
                window.location.hash = 'passkeys';
                window.location.reload();
            } catch (e) {
                meldung.textContent = e.name === 'InvalidStateError'
                    ? 'Auf diesem Gerät ist bereits ein Passkey für dein Konto hinterlegt.'
                    : (Passkey.abgebrochen(e) ? 'Abgebrochen.' : e.message);
                meldung.hidden = false;
            } finally {
                knopf.disabled = false;
            }
        });
    })();
</script>
