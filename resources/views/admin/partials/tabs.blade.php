{{-- Umschalter zwischen den Verwaltungs-Bereichen.

     Reihenfolge ist bewusst gesetzt: erst was das ganze Intranet betrifft
     (Einstellungen, Adressen), dann wer es benutzen darf (Benutzer, Rollen),
     dann der Betrieb (Maillog, Module). --}}
@php
    $bereiche = [
        ['route' => 'admin.settings.index', 'muster' => 'admin.settings.*', 'label' => 'Einstellungen'],
        ['route' => 'admin.seo.index', 'muster' => 'admin.seo.*', 'label' => 'SEO'],
        ['route' => 'admin.users.index', 'muster' => 'admin.users.*', 'label' => 'Benutzer'],
        ['route' => 'admin.roles.index', 'muster' => 'admin.roles.*', 'label' => 'Rollen'],
        ['route' => 'admin.einladungen.index', 'muster' => 'admin.einladungen.*', 'label' => 'Einladungen',
         'zaehler' => \App\Models\Einladung::wartend()->count()],
        ['route' => 'admin.mail.index', 'muster' => 'admin.mail.*', 'label' => 'Maillog'],
        ['route' => 'admin.modules.index', 'muster' => 'admin.modules.*', 'label' => 'Module'],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-1 border-b border-gray-200">
    @foreach ($bereiche as $bereich)
        <a href="{{ route($bereich['route']) }}"
           @class([
               'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
               'border-indigo-600 text-indigo-700' => request()->routeIs($bereich['muster']),
               'border-transparent text-gray-500 hover:text-gray-700' => ! request()->routeIs($bereich['muster']),
           ])>{{ $bereich['label'] }}@if (! empty($bereich['zaehler']))<span
                class="ml-1.5 inline-flex rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">{{ $bereich['zaehler'] }}</span>@endif</a>
    @endforeach
</nav>
