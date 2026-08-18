<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>
    <x-slot name="titel">Microsoft-SSO</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        {{-- Zustand auf einen Blick --}}
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span @class([
                        'inline-block h-3 w-3 rounded-full',
                        'bg-emerald-500' => $aktiv,
                        'bg-gray-300' => ! $aktiv,
                    ])></span>
                    <div>
                        <p class="font-medium text-gray-800">
                            {{ $aktiv ? 'Anmeldung mit Microsoft ist eingerichtet' : 'Anmeldung mit Microsoft ist aus' }}
                        </p>
                        <p class="text-sm text-gray-500">
                            @if ($aktiv)
                                Auf der Anmeldeseite erscheint der Knopf „Mit Microsoft anmelden".
                            @else
                                Solange in der .env keine Zugangsdaten stehen, sieht niemand einen Microsoft-Knopf.
                            @endif
                        </p>
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    {{ $verknuepft }} {{ $verknuepft === 1 ? 'Konto' : 'Konten' }} mit einem Microsoft-Konto verknüpft
                </div>
            </div>
        </div>

        {{-- Die geltenden Werte --}}
        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 font-medium text-gray-800">Eingetragene Werte</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Umleitungs-Adresse (im Entra-Portal eintragen)</dt>
                        <dd class="mt-1 break-all rounded-md bg-gray-50 p-2 font-mono text-xs text-gray-800">{{ $umleitung }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Verzeichnis (Tenant)</dt>
                        <dd class="break-all font-mono text-xs text-gray-800">{{ $tenant ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Anwendung (Client-ID)</dt>
                        <dd class="break-all font-mono text-xs text-gray-800">{{ $clientId ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Geheimnis</dt>
                        <dd class="text-xs {{ $secretGesetzt ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $secretGesetzt ? 'hinterlegt' : 'fehlt' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Berechtigungen</dt>
                        <dd class="text-right font-mono text-xs text-gray-800">{{ implode(' ', $scopes) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 font-medium text-gray-800">Wer darf herein?</h2>
                <p class="mb-3 text-sm text-gray-600">
                    Wer im Intranet <strong>schon ein Konto hat</strong>, kann sich immer per Microsoft anmelden –
                    die Zuordnung läuft beim ersten Mal über die E-Mail-Adresse.
                </p>
                @if ($darfAnlegen)
                    <p class="mb-2 text-sm text-gray-600">
                        <strong>Neu angelegt</strong> wird nur, wer Mitglied einer dieser Gruppen ist:
                    </p>
                    <ul class="mb-3 space-y-1">
                        @foreach ($gruppen as $gruppe)
                            <li class="break-all font-mono text-xs text-gray-800">{{ $gruppe }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm text-gray-600">
                        Neue Konten bekommen {!! $rollen ? 'die Rolle(n) <strong>'.e(implode(', ', $rollen)).'</strong> (und user)' : 'nur die Rolle <strong>user</strong>' !!}.
                    </p>
                @else
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                        Es ist keine Gruppe hinterlegt (MS_GRUPPEN). Damit wird <strong>niemand</strong> automatisch
                        angelegt: Es kommen ausschließlich Benutzer herein, die es im Intranet schon gibt.
                    </p>
                @endif
            </div>
        </div>

        {{-- Einrichtung, falls noch nichts steht --}}
        @unless ($aktiv)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="mb-3 font-medium text-gray-800">So wird es eingerichtet</h2>
                <ol class="list-decimal space-y-2 pl-5 text-sm text-gray-600">
                    <li>Im Entra-Portal (entra.microsoft.com) unter <em>Identität → Anwendungen → App-Registrierungen</em>
                        eine neue Registrierung anlegen.</li>
                    <li>Als Umleitungs-URI vom Typ <em>Web</em> genau diese Adresse eintragen:
                        <span class="break-all font-mono text-xs">{{ $umleitung }}</span></li>
                    <li>Unter <em>Zertifikate &amp; Geheimnisse</em> ein neues Client-Geheimnis erzeugen und den
                        <strong>Wert</strong> (nicht die ID) notieren – er ist nur einmal sichtbar.</li>
                    <li>Unter <em>Tokenkonfiguration</em> den optionalen Anspruch <span class="font-mono text-xs">groups</span>
                        für das ID-Token hinzufügen (Sicherheitsgruppen). Ohne ihn lässt sich die Gruppen-Zugehörigkeit
                        nicht prüfen, und es wird niemand neu angelegt.</li>
                    <li>Verzeichnis-ID, Anwendungs-ID und das Geheimnis in die <span class="font-mono text-xs">.env</span>
                        des Servers eintragen (MS_TENANT_ID, MS_CLIENT_ID, MS_CLIENT_SECRET, MS_GRUPPEN) und
                        <span class="font-mono text-xs">php artisan config:clear</span> ausführen.</li>
                </ol>
            </div>
        @endunless

        {{-- Protokoll --}}
        <div class="rounded-xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="font-medium text-gray-800">Letzte Anmeldeversuche</h2>
                <span class="text-xs text-gray-500">{{ $versuche->count() }} angezeigt (neueste zuerst)</span>
            </div>

            @if ($versuche->isEmpty())
                <p class="p-5 text-sm text-gray-500">Bisher hat sich noch niemand über Microsoft angemeldet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-2 font-medium">Zeitpunkt</th>
                                <th class="px-5 py-2 font-medium">Wer</th>
                                <th class="px-5 py-2 font-medium">Ergebnis</th>
                                <th class="px-5 py-2 font-medium">Anmerkung</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($versuche as $versuch)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-2 text-xs text-gray-500">
                                        {{ $versuch->created_at?->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-5 py-2">
                                        <span class="text-gray-800">{{ $versuch->email ?? '—' }}</span>
                                        @if ($versuch->name)
                                            <span class="block text-xs text-gray-500">{{ $versuch->name }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-2">
                                        <span @class([
                                            'inline-block rounded-full px-2 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => $versuch->istErfolg(),
                                            'bg-red-100 text-red-800' => ! $versuch->istErfolg(),
                                        ])>{{ $versuch->ergebnisText() }}</span>
                                    </td>
                                    <td class="px-5 py-2 text-xs text-gray-600">{{ $versuch->meldung }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
