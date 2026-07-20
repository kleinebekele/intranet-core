<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mailvorlagen</h1>
    </x-slot>

    <div class="max-w-3xl">
        @include('admin.partials.tabs')

        <p class="mb-6 text-gray-600">
            Hier legst du fest, wie die Mails des Intranets aussehen. Ein <span class="font-medium">Rahmen</span>
            umschließt die einzelne Mail (Kopf, Logo, Fußzeile); die übrigen Vorlagen sind die Mails selbst.
            Jede gibt es als formatierte Fassung <span class="font-medium">und</span> als reinen Text —
            beide werden verschickt.
        </p>

        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Rahmen</h2>
        <ul class="mb-8 space-y-2">
            @foreach ($rahmen as $vorlage)
                @include('admin.mailvorlagen.partials.zeile')
            @endforeach
        </ul>

        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Mails</h2>
        <ul class="space-y-2">
            @foreach ($vorlagen as $vorlage)
                @include('admin.mailvorlagen.partials.zeile')
            @endforeach
        </ul>
    </div>
</x-app-layout>
