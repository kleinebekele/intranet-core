{{--
    Linke Navigation mit zwei Zuständen:
    1. Startseite  -> Liste aller aktiven Module
    2. Im Modul    -> Modulname + "Zurück" + Unterseiten des Moduls

    $sidebarModules und $currentModule kommen aus dem NavigationComposer.
--}}
<nav class="h-full py-4 px-3 flex flex-col">

    @if ($currentModule)
        {{-- ===== Modul-Kontext ===== --}}
        <a href="{{ route('dashboard') }}"
           class="group flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-800">
            <i class='bx bx-chevron-left text-lg leading-none'></i>
            Zurück zur Startseite
        </a>

        <div class="mt-2 mb-3 px-3 flex items-center gap-2.5">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">
                <x-module-icon :name="$currentModule->icon" class="text-xl" />
            </span>
            <span class="text-base font-semibold text-gray-800">{{ $currentModule->name }}</span>
        </div>

        @php $activeItem = $currentModule->activeMenuItem(); @endphp
        <div class="space-y-1">
            @foreach ($currentModule->menuTree() as $node)
                @if ($node['label'] === null)
                    @include('layouts.partials.module-menu-item', ['item' => $node['items']->first(), 'activeItem' => $activeItem])
                @else
                    {{-- Aufklappbare Gruppe; offen, wenn der aktive Punkt darin liegt. --}}
                    <div x-data="{ open: @js($node['items']->contains(fn ($i) => $i->is($activeItem))) }">
                        <button type="button" x-on:click="open = ! open"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                                x-bind:aria-expanded="open ? 'true' : 'false'">
                            <x-module-icon name="category" class="text-lg text-gray-400" />
                            <span class="flex-1 text-left">{{ $node['label'] }}</span>
                            <i class='bx bx-chevron-down text-lg leading-none transition-transform'
                               x-bind:class="open && 'rotate-180'"></i>
                        </button>

                        <div x-show="open" x-cloak class="mt-1 space-y-1 border-l border-gray-200 pl-3 ml-4">
                            @foreach ($node['items'] as $item)
                                @include('layouts.partials.module-menu-item', ['item' => $item, 'activeItem' => $activeItem])
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

    @else
        {{-- ===== Startseiten-Kontext ===== --}}
        <a href="{{ route('dashboard') }}"
           @class([
               'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
               'bg-indigo-50 text-indigo-700' => request()->routeIs('dashboard'),
               'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! request()->routeIs('dashboard'),
           ])>
            <x-module-icon name="home" class="text-xl" />
            Startseite
        </a>

        <p class="mt-6 mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
            Module
        </p>

        <div class="space-y-1">
            @forelse ($sidebarModules as $module)
                <a href="{{ $module->homeUrl() ?? '#' }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition">
                    <x-module-icon :name="$module->icon" class="text-xl text-gray-400" />
                    {{ $module->name }}
                </a>
            @empty
                <p class="px-3 py-2 text-sm text-gray-400">
                    Noch keine Module installiert.
                </p>
            @endforelse
        </div>

        @auth
            @if (auth()->user()->isAdmin())
                <div class="mt-auto pt-4 border-t border-gray-100">
                    <a href="{{ route('admin.modules.index') }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition">
                        <x-module-icon name="cog" class="text-xl text-gray-400" />
                        Modul-Verwaltung
                    </a>
                </div>
            @endif
        @endauth
    @endif
</nav>
