<section id="two-factor">
    <header>
        <h2 class="text-lg font-medium text-gray-900">Zwei-Faktor-Authentifizierung</h2>
        <p class="mt-1 text-sm text-gray-600">
            @if (config('intranet.two_factor'))
                Beim Login ist ein zweiter Faktor nötig — standardmäßig ein Code per E-Mail.
                Mit einer Authenticator-App (z.&nbsp;B. Vaultwarden) geht es schneller und ohne Mail.
            @else
                Auf dieser Installation ist die Zwei-Faktor-Anmeldung derzeit
                <b>deaktiviert</b>. Du kannst TOTP trotzdem schon einrichten — es greift,
                sobald sie aktiviert wird.
            @endif
        </p>
    </header>

    @php($pendingSecret = session('totp_pending_secret'))

    @if (session('status') === 'totp-confirmed')
        <p class="mt-4 text-sm font-medium text-green-600">Authenticator-App erfolgreich eingerichtet.</p>
    @elseif (session('status') === 'totp-disabled')
        <p class="mt-4 text-sm font-medium text-green-600">TOTP entfernt — es gilt wieder der Code per E-Mail.</p>
    @endif

    @if ($user->hasTotp())
        {{-- Zustand: TOTP aktiv --}}
        <p class="mt-4 text-sm text-gray-700">
            ✅ Authenticator-App aktiv seit {{ $user->totp_confirmed_at->format('d.m.Y H:i') }} Uhr.
        </p>

        <form method="POST" action="{{ route('profile.two-factor.disable') }}" class="mt-4 space-y-3">
            @csrf
            @method('DELETE')
            <div>
                <x-input-label for="totp_disable_password" value="Zum Entfernen aktuelles Passwort eingeben" />
                <x-text-input id="totp_disable_password" name="password" type="password" class="mt-1 block w-full max-w-xs" />
                <x-input-error :messages="$errors->totp->get('password')" class="mt-2" />
            </div>
            <x-danger-button>TOTP entfernen</x-danger-button>
        </form>

    @elseif ($pendingSecret)
        {{-- Zustand: Einrichtung läuft — QR + Secret zeigen, ersten Code abfragen --}}
        @php($uri = \App\Support\Totp::otpauthUri(config('app.name', 'Intranet'), $user->email, $pendingSecret))

        <div class="mt-4 space-y-4">
            <p class="text-sm text-gray-700">
                <b>1.</b> Scanne den QR-Code mit deiner Authenticator-App
                — oder füge das Secret manuell ein (Vaultwarden: Eintrag bearbeiten → „Authentifikatorschlüssel (TOTP)"):
            </p>

            <div id="totp-qr" class="inline-block rounded-lg border border-gray-200 p-3 bg-white"></div>
            <p class="font-mono text-sm bg-gray-50 rounded px-3 py-2 select-all break-all">{{ $pendingSecret }}</p>

            <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="totp_code" value="2. Zum Bestätigen den aktuellen Code aus der App eingeben" />
                    <x-text-input id="totp_code" name="code" type="text" inputmode="numeric"
                                  autocomplete="one-time-code" class="mt-1 block w-full max-w-xs tracking-widest" required />
                    <x-input-error :messages="$errors->totp->get('code')" class="mt-2" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Bestätigen &amp; aktivieren</x-primary-button>
                    <a href="{{ route('profile.two-factor.cancel') }}"
                       class="text-sm text-gray-500 underline hover:text-gray-700">Abbrechen</a>
                </div>
            </form>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            new QRCode(document.getElementById('totp-qr'), {
                text: @js($uri),
                width: 180,
                height: 180,
                correctLevel: QRCode.CorrectLevel.M,
            });
        </script>

    @else
        {{-- Zustand: kein TOTP — Standard ist Mail-Code --}}
        <p class="mt-4 text-sm text-gray-700">
            Aktuell: Code per E-Mail an <b>{{ $user->email }}</b>@unless (config('intranet.two_factor')) <i>(sobald aktiviert)</i>@endunless.
        </p>

        <form method="POST" action="{{ route('profile.two-factor.setup') }}" class="mt-4">
            @csrf
            <x-primary-button>Authenticator-App (TOTP) einrichten</x-primary-button>
        </form>
    @endif
</section>
