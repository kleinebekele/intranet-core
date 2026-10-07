@props(['user', 'groesse' => 'h-8 w-8 text-sm'])

{{-- Rundes Profilbild; ohne Bild der Anfangsbuchstabe wie bisher. --}}
@if ($url = \App\Support\Profilbild::url($user))
    <img src="{{ $url }}" alt="" {{ $attributes->merge(['class' => "$groesse inline-block rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$groesse inline-flex items-center justify-center rounded-full bg-gray-200 text-gray-600 font-semibold"]) }}>
        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
    </span>
@endif
