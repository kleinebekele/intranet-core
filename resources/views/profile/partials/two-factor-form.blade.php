<section id="two-factor">
    <header>
        <h2 class="text-lg font-medium text-gray-900">Zwei-Faktor-Authentifizierung (2FA)</h2>
        <p class="mt-1 text-sm text-gray-600">
            Schützt dein Konto mit einem zweiten Faktor beim Login — standardmäßig per
            Code an deine E-Mail-Adresse, optional per Authenticator-App (z.&nbsp;B. Vaultwarden).
        </p>
    </header>

    @php($forced = config('intranet.two_factor_forced'))
    @php($pendingSecret = session('totp_pending_secret'))

    @if (session('status') === 'two-factor-enabled')
        <p class="mt-4 text-sm font-medium text-green-600">2FA ist jetzt aktiv — beim nächsten Login wird ein Code abgefragt.</p>
    @elseif (session('status') === 'two-factor-disabled')
        <p class="mt-4 text-sm font-medium text-green-600">2FA wurde deaktiviert.</p>
    @elseif (session('status') === 'totp-confirmed')
        <p class="mt-4 text-sm font-medium text-green-600">Authenticator-App erfolgreich eingerichtet.</p>
    @elseif (session('status') === 'totp-disabled')
        <p class="mt-4 text-sm font-medium text-green-600">Authenticator-App entfernt — es gilt wieder der Code per E-Mail.</p>
    @endif

    @if (! $user->two_factor_enabled && ! $forced)
        {{-- 2FA aus → aktivieren anbieten --}}
        <p class="mt-4 text-sm text-gray-700">Status: <b>deaktiviert</b>.</p>
        <form method="POST" action="{{ route('profile.two-factor.enable') }}" class="mt-3">
            @csrf
            <x-primary-button>2FA aktivieren (Code per E-Mail)</x-primary-button>
        </form>
        <p class="mt-2 text-xs text-gray-500">Nach dem Aktivieren kannst du hier zusätzlich eine Authenticator-App einrichten.</p>
    @else
        {{-- 2FA aktiv (freiwillig oder erzwungen) --}}
        <p class="mt-4 text-sm text-gray-700">
            Status: <b>aktiv</b>@if ($forced) <span class="text-xs text-gray-500">(auf dieser Installation für alle verpflichtend)</span>@endif
            — Verfahren:
            @if ($user->hasTotp())
                <b>Authenticator-App</b> (seit {{ $user->totp_confirmed_at->format('d.m.Y') }})
            @else
                <b>Code per E-Mail</b> an {{ $user->email }}
            @endif
        </p>

        @if ($user->hasTotp())
            {{-- TOTP aktiv → entfernen (fällt auf Mail-Code zurück) --}}
            <form method="POST" action="{{ route('profile.two-factor.remove-totp') }}" class="mt-4 space-y-3">
                @csrf
                @method('DELETE')
                <div>
                    <x-input-label for="totp_remove_password" value="Authenticator-App entfernen — aktuelles Passwort" />
                    <x-text-input id="totp_remove_password" name="password" type="password" class="mt-1 block w-full max-w-xs" />
                    <x-input-error :messages="$errors->totp->get('password')" class="mt-2" />
                </div>
                <x-secondary-button type="submit">App entfernen (zurück zu Mail-Codes)</x-secondary-button>
            </form>
        @elseif ($pendingSecret)
            {{-- TOTP-Einrichtung läuft --}}
            @php($uri = \App\Support\Totp::otpauthUri(config('app.name', 'Intranet'), $user->email, $pendingSecret))

            <div class="mt-4 space-y-4">
                <p class="text-sm text-gray-700">
                    <b>1.</b> Scanne den QR-Code mit deiner Authenticator-App — oder füge das Secret
                    manuell ein (Vaultwarden: Eintrag bearbeiten → „Authentifikatorschlüssel (TOTP)"):
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
            {{-- Mail-Code aktiv → TOTP anbieten --}}
            <form method="POST" action="{{ route('profile.two-factor.setup') }}" class="mt-4">
                @csrf
                <x-secondary-button type="submit">Authenticator-App (TOTP) einrichten</x-secondary-button>
            </form>
        @endif

        @unless ($forced)
            {{-- Komplett abschalten --}}
            <details class="mt-6">
                <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700">2FA deaktivieren …</summary>
                <form method="POST" action="{{ route('profile.two-factor.disable') }}" class="mt-3 space-y-3">
                    @csrf
                    @method('DELETE')
                    <div>
                        <x-input-label for="tf_disable_password" value="Zum Deaktivieren aktuelles Passwort eingeben" />
                        <x-text-input id="tf_disable_password" name="password" type="password" class="mt-1 block w-full max-w-xs" />
                        <x-input-error :messages="$errors->totp->get('password')" class="mt-2" />
                    </div>
                    <x-danger-button>2FA deaktivieren</x-danger-button>
                </form>
            </details>
        @endunless
    @endif
</section>
