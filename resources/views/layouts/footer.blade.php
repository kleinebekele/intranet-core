{{-- Fußzeile: Copyright links, Links aus Systemeinstellungen → Footer mittig,
     Versionsangabe rechts. Auf schmalen Bildschirmen untereinander. --}}
@php
    $footerLinks = \App\Models\FooterLink::fuerFooter();
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

        <span class="text-center sm:text-right text-gray-400">Intranet-Plattform &middot; v1.0</span>
    </div>
</footer>
