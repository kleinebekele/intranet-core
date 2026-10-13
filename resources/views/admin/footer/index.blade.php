<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Systemeinstellungen</h1>
    </x-slot>
    <x-slot name="titel">Footer</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <p class="text-sm text-gray-600">
                Links in der Fußzeile, mittig zwischen Copyright und Versionsangabe – und auf der Anmeldeseite.
                <strong>Intern</strong> = eigene Seite unter <code>/seite/…</code> mit Text aus dem Editor (auch ohne Anmeldung lesbar),
                <strong>extern</strong> = Link auf eine andere Adresse.
            </p>
            <a href="{{ route('admin.footer.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-plus text-base'></i> Neuer Link
            </a>
        </div>

        @if ($links->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">Noch kein Link.</div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Reihenfolge</th>
                            <th class="px-4 py-3">Titel</th>
                            <th class="px-4 py-3">Art</th>
                            <th class="px-4 py-3">Ziel</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($links as $link)
                            <tr class="{{ $link->aktiv ? '' : 'text-gray-400' }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @foreach (['links' => 'bx-chevron-left', 'rechts' => 'bx-chevron-right'] as $richtung => $icon)
                                        <form method="POST" action="{{ route('admin.footer.verschieben', $link) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="richtung" value="{{ $richtung }}">
                                            <button type="submit" title="nach {{ $richtung }}" class="rounded p-1 text-lg text-gray-500 hover:bg-gray-100"><i class='bx {{ $icon }}'></i></button>
                                        </form>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3 font-medium {{ $link->aktiv ? 'text-gray-900' : '' }}">
                                    {{ $link->titel }}
                                    @unless ($link->aktiv)
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">aus</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3">{{ $link->istIntern() ? 'intern' : 'extern' }}</td>
                                <td class="px-4 py-3 text-gray-600 [overflow-wrap:anywhere]">
                                    <a href="{{ $link->href() }}" target="_blank" rel="noopener" class="hover:underline">
                                        {{ $link->istIntern() ? '/seite/'.$link->slug : $link->url }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.footer.edit', $link) }}" class="text-sm text-indigo-700 hover:underline">Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.footer.destroy', $link) }}" class="ml-3 inline"
                                          data-frage="Link „{{ $link->titel }}“ löschen?" onsubmit="return confirm(this.dataset.frage)">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
