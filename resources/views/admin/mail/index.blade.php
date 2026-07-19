<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>

    <x-slot name="titel">Maillog</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- Ist der Ausgangskorb aus, geht alles ungedrosselt und unprotokolliert raus.
             Das muss man sofort sehen, sonst wundert man sich über eine leere Liste. --}}
        @unless ($aktiv)
            <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <i class='bx bx-error text-lg leading-none'></i>
                <span>
                    <span class="font-medium">Der Ausgangskorb ist abgeschaltet.</span>
                    Mails gehen sofort und ungedrosselt raus und tauchen hier nicht auf
                    (<code class="rounded bg-amber-100 px-1">MAIL_OUTBOX=false</code>).
                </span>
            </div>
        @endunless

        {{-- Kennzahlen --}}
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Letzte Stunde</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800">
                    {{ $letzteStunde }}@if ($limit > 0)<span class="text-base font-normal text-gray-400"> / {{ $limit }}</span>@endif
                </div>
                @if ($limit > 0)
                    @php $anteil = min(100, (int) round($letzteStunde / $limit * 100)); @endphp
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full {{ $anteil >= 90 ? 'bg-red-500' : ($anteil >= 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                             style="width: {{ $anteil }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-gray-400">Stundenlimit des Providers</div>
                @else
                    <div class="mt-2 text-xs text-gray-400">Kein Stundenlimit gesetzt</div>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Wartet</div>
                <div class="mt-1 text-2xl font-semibold text-gray-800">{{ $anzahl['wartend'] }}</div>
                <div class="mt-2 text-xs text-gray-400">Geht beim nächsten Lauf raus</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Versendet</div>
                <div class="mt-1 text-2xl font-semibold text-green-700">{{ $anzahl['versendet'] }}</div>
                <div class="mt-2 text-xs text-gray-400">Vom Mailserver angenommen</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Fehlgeschlagen</div>
                <div class="mt-1 text-2xl font-semibold {{ $anzahl['fehlgeschlagen'] ? 'text-red-700' : 'text-gray-800' }}">
                    {{ $anzahl['fehlgeschlagen'] }}
                </div>
                <div class="mt-2 text-xs text-gray-400">Nach mehreren Versuchen aufgegeben</div>
            </div>
        </div>

        {{-- Filter --}}
        <div class="mb-4 flex flex-wrap gap-1">
            @php
                $filter = [
                    null => 'Alle',
                    'wartend' => 'Wartet',
                    'versendet' => 'Versendet',
                    'fehlgeschlagen' => 'Fehlgeschlagen',
                ];
            @endphp
            @foreach ($filter as $wert => $beschriftung)
                <a href="{{ route('admin.mail.index', $wert ? ['status' => $wert] : []) }}"
                   @class([
                       'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                       'bg-indigo-600 text-white' => $status === $wert,
                       'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' => $status !== $wert,
                   ])>{{ $beschriftung }}</a>
            @endforeach
        </div>

        @if ($mails->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Keine Mails im Protokoll.
                <div class="mt-1 text-sm text-gray-400">
                    Hier erscheint jede E-Mail, die die Plattform verschickt – aus dem Core wie aus jedem Modul.
                </div>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Betreff</th>
                            <th class="px-4 py-3">An</th>
                            <th class="px-4 py-3">Auslöser</th>
                            <th class="px-4 py-3 whitespace-nowrap">Eingang</th>
                            <th class="px-4 py-3 whitespace-nowrap">Versendet</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($mails as $mail)
                            <tr class="align-top">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($mail->status === 'versendet')
                                        <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">versendet</span>
                                    @elseif ($mail->status === 'fehlgeschlagen')
                                        <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">fehlgeschlagen</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">wartet</span>
                                    @endif
                                    @if ($mail->prioritaet > 0)
                                        <span class="mt-1 block text-xs font-medium text-indigo-600">eilig</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 font-medium text-gray-800">
                                    {{ $mail->betreff ?: '—' }}
                                    @if ($mail->fehler)
                                        <div class="mt-1 text-xs font-normal text-red-600">
                                            {{ \Illuminate\Support\Str::limit($mail->fehler, 160) }}
                                            @if ($mail->versuche > 1)
                                                <span class="text-gray-400">({{ $mail->versuche }} Versuche)</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-gray-600">
                                    {{ implode(', ', $mail->an ?? []) ?: '—' }}
                                </td>

                                <td class="px-4 py-3 text-gray-500">
                                    {{ $mail->quelle ? class_basename($mail->quelle) : '—' }}
                                </td>

                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    {{ $mail->created_at?->format('d.m.Y H:i') }}
                                </td>

                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    {{ $mail->versendet_am?->format('d.m.Y H:i') ?? '—' }}
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($mail->status === 'fehlgeschlagen')
                                        <form method="POST" action="{{ route('admin.mail.erneut', $mail) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                <i class='bx bx-revision text-sm leading-none'></i>
                                                Erneut
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $mails->links() }}</div>
        @endif

        <p class="mt-6 text-xs text-gray-400">
            „Versendet" heißt: der Mailserver hat die Nachricht angenommen. Ob sie im Postfach
            ankam, weiß die Plattform nicht – dafür braucht es Rückmeldungen des Mailproviders,
            die später über die gespeicherte Message-ID zugeordnet werden können.
        </p>
    </div>
</x-app-layout>
