<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mailvorlagen</h1>
    </x-slot>

    @php
        // Je Modul ein Reiter: Core vorn, danach die Module alphabetisch.
        $gruppen = collect($rahmen)->map(fn ($v) => ['art' => 'rahmen', 'vorlage' => $v])
            ->concat(collect($vorlagen)->map(fn ($v) => ['art' => 'mail', 'vorlage' => $v]))
            ->groupBy(fn ($z) => $reiter[$z['vorlage']->schluessel] ?? 'Core')
            ->sortBy(fn ($z, $modul) => ($modul === 'Core' ? '0' : '1').mb_strtolower($modul));
        $reiterKlasse = 'whitespace-nowrap border-b-2 -mb-px px-4 py-2 text-sm font-medium transition';
    @endphp

    <div>
        @include('admin.partials.tabs')

        <p class="mb-6 text-gray-600">
            Hier legst du fest, wie die Mails des Intranets aussehen. Ein <span class="font-medium">Rahmen</span>
            umschließt die einzelne Mail (Kopf, Logo, Fußzeile); die übrigen Vorlagen sind die Mails selbst.
            Jede gibt es als formatierte Fassung <span class="font-medium">und</span> als reinen Text —
            beide werden verschickt. In jeder Mail steht der Platzhalter <code>@{{ konto_einrichten }}</code> bereit:
            ein Hinweis, wie man über „Passwort vergessen" sein Konto einrichtet – er erscheint nur bei Empfängern ohne Passwort.
        </p>

        <div x-data="{ reiter: (() => { try { const r = localStorage.getItem('mailvorlagen.reiter'); return @js($gruppen->keys()->all()).includes(r) ? r : @js($gruppen->keys()->first()); } catch (e) { return @js($gruppen->keys()->first()); } })() }"
             x-init="$watch('reiter', r => { try { localStorage.setItem('mailvorlagen.reiter', r) } catch (e) {} })">
            <nav class="mb-4 flex flex-wrap gap-1 border-b border-gray-200" role="tablist">
                @foreach ($gruppen as $modul => $eintraege)
                    <button type="button" role="tab" :aria-selected="reiter === @js($modul)" @click="reiter = @js($modul)" class="{{ $reiterKlasse }}"
                            :class="reiter === @js($modul) ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        {{ $modul }} <span class="ml-1 text-xs text-gray-400">{{ $eintraege->count() }}</span>
                    </button>
                @endforeach
            </nav>

            @foreach ($gruppen as $modul => $eintraege)
                <div x-show="reiter === @js($modul)" @if (! $loop->first) x-cloak @endif>
                    @php
                        $rahmenListe = $eintraege->where('art', 'rahmen')->pluck('vorlage');
                        $mailListe = $eintraege->where('art', 'mail')->pluck('vorlage');
                    @endphp
                    @if ($rahmenListe->isNotEmpty())
                        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Rahmen</h2>
                        <ul class="mb-8 space-y-2">
                            @foreach ($rahmenListe as $vorlage)
                                @include('admin.mailvorlagen.partials.zeile')
                            @endforeach
                        </ul>
                    @endif
                    @if ($mailListe->isNotEmpty())
                        <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Mails</h2>
                        <ul class="space-y-2">
                            @foreach ($mailListe as $vorlage)
                                @include('admin.mailvorlagen.partials.zeile')
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
