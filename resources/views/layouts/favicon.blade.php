{{-- Favicon aus den Einstellungen (Verwaltung → Einstellungen).

     Ohne hochgeladenes Bild geben wir bewusst GAR NICHTS aus: Der Browser sucht
     dann von selbst nach /favicon.ico – so bleibt ein per Hand dort abgelegtes
     Symbol weiter gültig. --}}
@php
    $faviconPfad = \App\Models\Setting::get('favicon');
@endphp

@if ($faviconPfad)
    @php
        $faviconUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPfad);
        $endung = strtolower(pathinfo($faviconPfad, PATHINFO_EXTENSION));
        $typ = match ($endung) {
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        // Der Dateiname bleibt beim Austausch oft gleich – ohne Stempel zeigt der
        // Browser tagelang das alte Symbol aus seinem Cache.
        $stempel = substr(md5($faviconPfad), 0, 8);
    @endphp

    <link rel="icon" type="{{ $typ }}" href="{{ $faviconUrl }}?v={{ $stempel }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}?v={{ $stempel }}">
@endif
