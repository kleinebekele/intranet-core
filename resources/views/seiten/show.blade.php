{{-- Eigene Seite aus dem Footer (Impressum, Datenschutz …). Angemeldet im normalen
     Rahmen mit Seitenleiste, sonst als schlichte Seite mit Logo und Footer-Links. --}}
@auth
    <x-app-layout>
        <x-slot name="header">
            <h1 class="text-xl font-semibold text-gray-800">{{ $seite->titel }}</h1>
        </x-slot>
        <x-slot name="titel">{{ $seite->titel }}</x-slot>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @include('seiten.partials.inhalt')
        </div>
    </x-app-layout>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{{ $seite->titel }} – {{ \App\Support\Seitentitel::haupttitel() }}</title>
            @include('layouts.favicon')
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body class="font-sans text-gray-900 antialiased bg-gray-100">
            <div class="min-h-screen flex flex-col">
                <header class="border-b border-gray-200 bg-white">
                    <div class="flex items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                        <a href="{{ route('login') }}"><x-marken-logo /></a>
                        <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-700 hover:underline">Anmelden</a>
                    </div>
                </header>
                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        @include('seiten.partials.inhalt')
                    </div>
                </main>
                @include('layouts.footer')
            </div>
        </body>
    </html>
@endauth
