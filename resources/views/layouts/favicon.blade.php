{{-- Favicon aus den Einstellungen (Verwaltung → Einstellungen).

     Ohne hochgeladenes Bild geben wir bewusst GAR NICHTS aus: Der Browser sucht
     dann von selbst nach /favicon.ico – so bleibt ein per Hand dort abgelegtes
     Symbol weiter gültig. --}}
@php
    $faviconPfad = \App\Models\Setting::get('favicon');
@endphp

@if ($faviconPfad)
    @php
        // Bewusst wurzelrelativ statt Storage::url(): Das liefert eine absolute
        // URL aus APP_URL. Dieses Intranet ist aber oft über mehrere Adressen
        // erreichbar (interne IP und Domain) – eine feste absolute URL zeigt
        // dann in einem der Fälle ins Leere.
        $faviconUrl = parse_url(
            \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPfad),
            PHP_URL_PATH
        );
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
