{{-- Fußzeile: Copyright links, Links aus Systemeinstellungen → Footer mittig,
     Versionsangabe rechts. Auf schmalen Bildschirmen untereinander. --}}
@php
    $footerLinks = \App\Models\FooterLink::fuerFooter();
    $footerModule = \App\Support\Version::module();
@endphp

<footer class="border-t border-gray-200 bg-white">
    <div class="px-4 sm:px-6 lg:px-8 py-4 grid grid-cols-1 sm:grid-cols-3 items-center gap-2 text-sm text-gray-500">
        <span class="text-center sm:text-left">&copy; {{ date('Y') }} {{ config('app.name', 'Intranet') }}</span>

        <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1" aria-label="Rechtliches und Links">
            @foreach ($footerLinks as $link)
                <a href="{{ $link->href() }}"
                   @unless ($link->istIntern()) target="_blank" rel="noopener" @endunless
                   class="hover:text-gray-700 hover:underline">{{ $link->titel }}</a>
            @endforeach
        </nav>

        <div class="relative text-center sm:text-right text-gray-400" x-data="{ offen: false }" @click.outside="offen = false">
            <button type="button" @click="offen = ! offen" class="hover:text-gray-600" title="Installierte Module anzeigen">
                Intranet-Plattform &middot; {{ \App\Support\Version::plattform() }}
            </button>
            @if ($footerModule !== [])
                <div x-show="offen" x-cloak x-transition
                     class="absolute bottom-full right-0 z-20 mb-2 w-72 rounded-lg border border-gray-200 bg-white p-3 text-left text-xs text-gray-600 shadow-lg">
                    <div class="mb-1 font-semibold text-gray-700">Module</div>
                    <table class="w-full">
                        @foreach ($footerModule as $name => $version)
                            <tr><td class="py-0.5 pr-2">{{ $name }}</td><td class="py-0.5 text-right font-mono">{{ $version }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>
</footer>
