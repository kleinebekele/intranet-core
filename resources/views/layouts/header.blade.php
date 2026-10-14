<!-- Kopfzeile: Logo links, Benutzersteuerung rechts -->
<header class="fixed top-0 inset-x-0 z-30 h-16 bg-white border-b border-gray-200">
    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">

        <!-- Links: Hamburger (mobil) + Logo + Name -->
        {{-- min-w-0: Wird es eng, gibt die linke Seite nach (Titel kürzt sich),
             damit rechts Glocke, Hilfe und Profil immer sichtbar bleiben. --}}
        <div class="flex items-center gap-3 min-w-0">
            <button
                @click="sidebarOpen = ! sidebarOpen"
                class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none"
                aria-label="Navigation öffnen"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <x-marken-logo class="min-w-0 shrink" />
                <span class="text-lg font-semibold text-gray-800 hidden sm:block truncate">
                    {{ \App\Support\Seitentitel::haupttitel() }}
                </span>
            </a>
        </div>

        <!-- Rechts: Admin-Zugang + Benutzermenü -->
        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            @auth
                {{-- Kein Verwaltungs-Link mehr hier: Er fuehrte zum selben Ziel wie der
                     Punkt unten in der Seitenleiste. Ein Ziel, ein Weg. --}}
                {{-- Symbole von Modulen (App\Support\Kopfleiste), z. B. Webmail.
                     Unter md wandern sie hinter einen eigenen Knopf (Kachel-Symbol,
                     umrandet – bewusst anders als der Hamburger der Navigation),
                     damit Glocke, Hilfe und Profil auf schmalen Handys sichtbar
                     bleiben. Die Symbole werden nur EINMAL gerendert: dieselbe
                     Leiste ist ab md eingereiht und darunter ein Klappfeld. --}}
                @php($kopfSymbole = \App\Support\Kopfleiste::fuer(auth()->user()))
                @if ($kopfSymbole)
                    <div class="relative" x-data="{ symboleOffen: false }"
                         @click.outside="symboleOffen = false" @keydown.escape.window="symboleOffen = false">
                        <button type="button" @click="symboleOffen = ! symboleOffen"
                                :aria-expanded="symboleOffen.toString()"
                                title="Weitere Dienste"
                                class="md:hidden inline-flex items-center justify-center h-9 w-9 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <circle cx="3" cy="3" r="1.6"/><circle cx="8" cy="3" r="1.6"/><circle cx="13" cy="3" r="1.6"/>
                                <circle cx="3" cy="8" r="1.6"/><circle cx="8" cy="8" r="1.6"/><circle cx="13" cy="8" r="1.6"/>
                                <circle cx="3" cy="13" r="1.6"/><circle cx="8" cy="13" r="1.6"/><circle cx="13" cy="13" r="1.6"/>
                            </svg>
                            <span class="sr-only">Weitere Dienste</span>
                        </button>

                        {{-- flex! (wichtig) schlägt das hidden darunter verlässlich –
                             zwei gleichrangige display-Klassen hingen sonst von der
                             Reihenfolge im CSS ab. --}}
                        <div :class="symboleOffen ? 'flex!' : ''"
                             class="hidden md:flex absolute right-0 top-full mt-2 z-40 w-max max-w-[calc(100vw-2rem)] flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-lg
                                    md:static md:mt-0 md:w-auto md:max-w-none md:flex-nowrap md:rounded-none md:border-0 md:bg-transparent md:p-0 md:shadow-none">
                            @foreach ($kopfSymbole as $symbol)
                                {!! $symbol !!}
                            @endforeach
                        </div>
                    </div>
                @endif
                <x-hinweis-glocke />
                <x-hilfe-knopf />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1 sm:gap-2 rounded-md px-1.5 sm:px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 focus:outline-none">
                            <x-profilbild :user="auth()->user()" />
                            <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                            <svg class="h-4 w-4 fill-current text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <div class="text-sm font-medium text-gray-800">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</div>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profil') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Abmelden') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            @endauth
        </div>
    </div>
</header>
