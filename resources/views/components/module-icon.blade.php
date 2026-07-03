@props(['name' => null])

{{--
    Rendert ein Icon (Boxicons) anhand eines Namens. Module geben in ihrem
    Manifest einen dieser Namen an (icon: 'newspaper'). Unbekannte/leere Namen
    fallen auf ein neutrales Standard-Icon zurück.

    Größe und Farbe werden über Utility-Klassen gesteuert – Icon-Fonts skalieren
    über die Schriftgröße, z. B. <x-module-icon name="cog" class="text-xl" />.
--}}
@php
    $icons = [
        'home'      => 'bx-home',
        'newspaper' => 'bx-news',
        'users'     => 'bx-group',
        'cog'       => 'bx-cog',
        'folder'    => 'bx-folder',
        'chart'     => 'bx-bar-chart-alt-2',
        'calendar'  => 'bx-calendar',
        'document'  => 'bx-file',
        'chat'      => 'bx-chat',
        'restaurant' => 'bx-restaurant',
        'edit'      => 'bx-edit',
        'back'      => 'bx-arrow-back',
        'plus'      => 'bx-plus',
        'trash'     => 'bx-trash',
        'download'  => 'bx-download',
        'search'    => 'bx-search',
        'save'      => 'bx-save',
        'x'         => 'bx-x',
        'default'   => 'bx-grid-alt',
    ];
    $icon = $icons[$name] ?? $icons['default'];
@endphp

<i {{ $attributes->merge(['class' => "bx {$icon} leading-none align-middle"]) }} aria-hidden="true"></i>
