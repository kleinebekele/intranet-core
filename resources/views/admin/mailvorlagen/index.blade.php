<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mailvorlagen</h1>
    </x-slot>

    <div class="max-w-3xl">
        @include('admin.partials.tabs')

        <p class="mb-6 text-gray-600">
            Hier legst du fest, wie die Mails des Intranets aussehen. Der <span class="font-medium">Rahmen</span>
            umschließt jede Mail (Kopf, Logo, Fußzeile); die übrigen Vorlagen sind die einzelnen Mails.
            Jede gibt es als formatierte Fassung <span class="font-medium">und</span> als reinen Text —
            beide werden verschickt.
        </p>

        <ul class="space-y-2">
            @foreach ($vorlagen as $vorlage)
                <li>
                    <a href="{{ route('admin.mailvorlagen.edit', $vorlage->schluessel) }}"
                       class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 hover:border-indigo-300 hover:bg-indigo-50/40">
                        <span>
                            <span class="font-medium text-gray-800">{{ $vorlage->titel }}</span>
                            <span class="block text-sm text-gray-500">{{ $vorlage->beschreibung }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            @if (\App\Models\MailVorlage::find($vorlage->schluessel))
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">angepasst</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Standard</span>
                            @endif
                            <i class='bx bx-chevron-right text-xl text-gray-400'></i>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
