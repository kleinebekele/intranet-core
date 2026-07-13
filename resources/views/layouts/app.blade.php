<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Intranet') }}</title>

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

            <!-- Linke Navigation -->
            <aside
                class="fixed top-16 bottom-0 left-0 z-20 w-64 bg-white border-r border-gray-200 overflow-y-auto transform transition-transform duration-200 ease-in-out lg:translate-x-0"
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
            <div class="lg:pl-64 pt-16 min-h-screen flex flex-col">
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

        @stack('scripts')
    </body>
</html>
