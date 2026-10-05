@php($farbschema = auth()->user()?->farbschema ?? 'hell')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $farbschema === 'dunkel'])>
    <head>
        @if ($farbschema === 'system')
            {{-- Wie System: vor dem ersten Zeichnen setzen, sonst blitzt die Seite hell auf. --}}
            <script>
                (() => {
                    const dunkel = window.matchMedia('(prefers-color-scheme: dark)');
                    const setzen = () => document.documentElement.classList.toggle('dark', dunkel.matches);
                    setzen();
                    dunkel.addEventListener('change', setzen);
                })();
            </script>
        @endif
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Konvention: {Haupttitel} – {Modul} – {Seite}. Modul und Seite leitet
             der Core selbst ab; eine View kann die Seite überschreiben:
             <x-slot name="titel">Schüler bearbeiten</x-slot> --}}
        <title>{{ \App\Support\Seitentitel::bauen(isset($titel) ? trim($titel) : null) }}</title>

        @include('layouts.favicon')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-gray-100">

            <!-- Header: Logo + Usercontrol (immer oben, volle Breite) -->
            @include('layouts.header')

            @if ($leisteSchmal ?? false)
                {{-- Schmale Leiste (AppLayout::$leisteSchmal): ab Desktop-Breite nur die
                     Symbole; beim Darüberfahren klappt sie überlappend auf. Reines CSS, damit
                     es nicht am Tailwind-Build hängt. Mobil bleibt alles wie gewohnt. --}}
                <style>
                    @media (min-width: 1024px) {
                        aside.leiste-schmal { width: 4rem; overflow-x: hidden; transition: width .15s ease, box-shadow .15s ease; }
                        aside.leiste-schmal:hover { width: 16rem; z-index: 30; box-shadow: 0 10px 30px rgba(0, 0, 0, .15); }
                        aside.leiste-schmal a, aside.leiste-schmal button { white-space: nowrap; }
                        aside.leiste-schmal:not(:hover) p { visibility: hidden; }
                        /* Eingeklappt nur die Symbole: Beschriftung (Textknoten) auf Schriftgröße 0,
                           die Symbole bringen ihre eigene Größe mit (text-xl/text-lg). */
                        aside.leiste-schmal:not(:hover) a, aside.leiste-schmal:not(:hover) button { font-size: 0; }
                        aside.leiste-schmal:not(:hover) a i, aside.leiste-schmal:not(:hover) button i { font-size: 1.25rem; }
                        aside.leiste-schmal:not(:hover) button i.bx-chevron-down { display: none; }
                        .inhalt-schmal { padding-left: 4rem !important; }
                    }
                </style>
            @endif

            <!-- Linke Navigation -->
            <aside
                @class([
                    'fixed top-16 bottom-0 left-0 z-20 w-64 bg-white border-r border-gray-200 overflow-y-auto transform transition-transform duration-200 ease-in-out lg:translate-x-0',
                    'leiste-schmal' => $leisteSchmal ?? false,
                ])
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            >
                @include('layouts.sidebar')
            </aside>

            <!-- Abdunklung hinter der mobilen Navigation -->
            <div
                x-show="sidebarOpen"
                x-cloak
                @click="sidebarOpen = false"
                class="fixed inset-0 z-10 bg-gray-900/40 lg:hidden"
            ></div>

            <!-- Inhaltsbereich rechts neben der Navigation -->
            <div @class(['lg:pl-64 pt-16 min-h-screen flex flex-col', 'inhalt-schmal' => $leisteSchmal ?? false])>
                <main class="flex-1">
                    @isset($header)
                        <header class="bg-white border-b border-gray-200">
                            <div class="px-4 sm:px-6 lg:px-8 py-5">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    @if (session('status'))
                        <div class="px-4 sm:px-6 lg:px-8 pt-4">
                            <div class="flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                                <i class='bx bx-check-circle text-lg leading-none'></i>
                                <span>{{ session('status') }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="px-4 sm:px-6 lg:px-8 py-6">
                        {{ $slot }}
                    </div>
                </main>

                <!-- Footer -->
                @include('layouts.footer')
            </div>
        </div>

        @include('layouts.cookie-notice')
        @include('layouts.dialog')
        @include('layouts.passkey-angebot')

        @stack('scripts')
    </body>
</html>
