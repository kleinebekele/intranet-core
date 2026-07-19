<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Einladungen</h1>
    </x-slot>

    <div>
        @include('admin.partials.tabs')

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <p class="mb-6 text-gray-600">
            Hier warten Zugangslinks darauf, verschickt zu werden. Ein Import legt schnell hunderte
            Benutzer an — <span class="font-medium">verschickt wird nichts</span>, solange du nicht
            zustimmst. Beim Freigeben gehen die Mails über den Ausgangskorb und damit gedrosselt raus.
            Benutzer ohne echte Mailadresse (z.&nbsp;B. Schüler) stehen hier gar nicht erst: Sie melden
            sich an, ohne je eine Mail zu bekommen.
        </p>

        @if ($wartend->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Es wartet keine Einladung.
            </div>
        @else
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">{{ $wartend->count() }} wartend</div>

                <form method="POST" action="{{ route('admin.einladungen.alle') }}"
                      onsubmit="return confirm('{{ $wartend->count() }} Einladungen jetzt verschicken?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <i class='bx bx-mail-send text-base'></i>
                        Alle {{ $wartend->count() }} verschicken
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">E-Mail</th>
                            <th class="px-4 py-3">Rollen</th>
                            <th class="px-4 py-3">Vorgemerkt</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($wartend as $einladung)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $einladung->user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $einladung->user->email }}</td>
                                <td class="px-4 py-3">
                                    @foreach ($einladung->user->roles as $rolle)
                                        <span class="mr-1 inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $rolle->role_id }}</span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-400">
                                    {{ $einladung->created_at->format('d.m.Y H:i') }}
                                    @if ($einladung->quelle)
                                        <div>{{ $einladung->quelle }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1 text-xl">
                                        <form method="POST" action="{{ route('admin.einladungen.freigeben', $einladung) }}">
                                            @csrf
                                            <button type="submit" title="Einladung verschicken"
                                                    class="block rounded-md p-1.5 text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700">
                                                <i class='bx bx-mail-send'></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.einladungen.verwerfen', $einladung) }}"
                                              onsubmit="return confirm('Einladung an {{ $einladung->user->email }} verwerfen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Verwerfen"
                                                    class="block rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                                <i class='bx bx-x'></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($erledigt->isNotEmpty())
            <h2 class="mt-8 mb-2 text-sm font-semibold uppercase tracking-wide text-gray-400">Zuletzt entschieden</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($erledigt as $einladung)
                            <tr class="text-gray-600">
                                <td class="px-4 py-2">{{ $einladung->user?->name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $einladung->user?->email }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex rounded px-1.5 py-0.5 text-xs',
                                        'bg-emerald-50 text-emerald-700' => $einladung->status === \App\Models\Einladung::VERSCHICKT,
                                        'bg-gray-100 text-gray-600' => $einladung->status === \App\Models\Einladung::VERWORFEN,
                                        'bg-amber-50 text-amber-700' => $einladung->status === \App\Models\Einladung::UNZUSTELLBAR,
                                    ])>{{ $einladung->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-400">{{ $einladung->entschieden_am?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
