<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        @if ($usesTotp)
            Gib den 6-stelligen Code aus deiner <b>Authenticator-App</b> ein
            (z.&nbsp;B. Vaultwarden), um die Anmeldung abzuschließen.
        @else
            Wir haben dir einen 6-stelligen Code <b>per E-Mail</b> geschickt.
            Gib ihn ein, um die Anmeldung abzuschließen.
        @endif
    </div>

    @if (session('status') === 'two-factor-code-sent')
        <div class="mb-4 text-sm font-medium text-green-600">Ein neuer Code wurde versendet.</div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf

        <div>
            <x-input-label for="code" value="Code" />
            <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                          class="mt-1 block w-full tracking-widest" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        @if ($rememberDays > 0)
            <label class="mt-4 flex items-center gap-2">
                <input type="checkbox" name="remember_device" value="1" checked
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="text-sm text-gray-600">Dieses Gerät {{ $rememberDays }} Tage merken</span>
            </label>
        @endif

        <div class="mt-4 flex items-center justify-end gap-4">
            <x-primary-button>Bestätigen</x-primary-button>
        </div>
    </form>

    <div class="mt-4 flex items-center justify-between text-sm">
        @unless ($usesTotp)
            <form method="POST" action="{{ route('two-factor.resend') }}">
                @csrf
                <button type="submit" @disabled(! $canResend)
                        class="underline text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:no-underline">
                    Code erneut senden
                </button>
            </form>
        @else
            <span></span>
        @endunless

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="underline text-gray-600 hover:text-gray-900">Abmelden</button>
        </form>
    </div>
</x-guest-layout>
