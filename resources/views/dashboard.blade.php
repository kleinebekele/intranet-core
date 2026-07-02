@inject('nav', 'App\Modules\Support\Navigation')

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Startseite</h1>
    </x-slot>

    <!-- Begrüßung -->
    <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-2xl font-semibold">Willkommen, {{ auth()->user()->name }}!</h2>
        <p class="mt-1 text-indigo-100">
            Wähle links oder unten ein Modul, um loszulegen.
        </p>
    </div>

    <!-- Modul-Kacheln -->
    <div class="mt-6">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400 mb-3">Module</h3>

        @php $modules = $nav->modules(); @endphp

        @if ($modules->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                <x-module-icon name="default" class="text-4xl text-gray-300" />
                <p class="mt-2 text-gray-500">Es sind noch keine Module installiert.</p>
                @if (auth()->user()->isAdmin())
                    <p class="mt-1 text-sm text-gray-400">
                        Installiere ein Modul und führe <code class="rounded bg-gray-100 px-1.5 py-0.5">php artisan modules:sync</code> aus.
                    </p>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($modules as $module)
                    <a href="{{ $module->homeUrl() ?? '#' }}"
                       class="group rounded-xl border border-gray-200 bg-white p-5 hover:border-indigo-300 hover:shadow-md transition flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                            <x-module-icon :name="$module->icon" class="text-2xl" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-gray-800">{{ $module->name }}</span>
                            <span class="block text-sm text-gray-500">
                                {{ $module->menuItems->count() }} {{ $module->menuItems->count() === 1 ? 'Seite' : 'Seiten' }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
