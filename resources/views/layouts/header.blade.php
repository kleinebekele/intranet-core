<!-- Kopfzeile: Logo links, Benutzersteuerung rechts -->
<header class="fixed top-0 inset-x-0 z-30 h-16 bg-white border-b border-gray-200">
    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">

        <!-- Links: Hamburger (mobil) + Logo + Name -->
        <div class="flex items-center gap-3">
            <button
                @click="sidebarOpen = ! sidebarOpen"
                class="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none"
                aria-label="Navigation öffnen"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <x-marken-logo />
                <span class="text-lg font-semibold text-gray-800 hidden sm:block">
                    {{ \App\Support\Seitentitel::haupttitel() }}
                </span>
            </a>
        </div>

        <!-- Rechts: Admin-Zugang + Benutzermenü -->
        <div class="flex items-center gap-2">
            @auth
                @if (auth()->user()->isAdmin())
                    <a
                        href="{{ route('admin.users.index') }}"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-100' }}"
                        title="Verwaltung"
                    >
                        <x-module-icon name="cog" class="text-xl" />
                        <span class="hidden sm:inline">Verwaltung</span>
                    </a>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 focus:outline-none">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-600 text-sm font-semibold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
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
