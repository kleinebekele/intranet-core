{{-- Logo der Instanz: entweder das in der Verwaltung hochgeladene Bild oder das
     mitgelieferte Standard-Zeichen.

     @props:
       gross  – grosse Darstellung (Anmeldeseite) statt klein (Kopfzeile)

     Bewusst wurzelrelativ statt Storage::url(): Das Intranet ist oft ueber
     mehrere Adressen erreichbar (interne IP und Domain), eine absolute URL aus
     APP_URL zeigt dann in einem der Faelle ins Leere. --}}
@props(['gross' => false])

@php
    $logoPfad = \App\Models\Setting::get('logo');
    $logoUrl = $logoPfad
        ? parse_url(\Illuminate\Support\Facades\Storage::disk('public')->url($logoPfad), PHP_URL_PATH)
        : null;
    // Dateiname bleibt beim Austausch oft gleich - ohne Stempel zeigt der
    // Browser tagelang das alte Bild aus seinem Cache.
    $stempel = $logoPfad ? substr(md5($logoPfad), 0, 8) : null;
@endphp

@if ($logoUrl)
    <img src="{{ $logoUrl }}?v={{ $stempel }}"
         alt="{{ \App\Support\Seitentitel::haupttitel() }}"
         {{ $attributes->merge(['class' => $gross ? 'h-20 w-auto max-w-56 object-contain' : 'h-9 w-auto max-w-40 object-contain']) }}>
@elseif ($gross)
    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
@else
    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white">
        <x-application-logo class="h-5 w-5 fill-current" />
    </span>
@endif
