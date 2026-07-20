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
        // Menü-Icons (Modul-Unterpunkte)
        'grid'      => 'bx-grid-alt',
        'cart'      => 'bx-cart',
        'diet'      => 'bx-heart',
        'serving'   => 'bx-bowl-hot',
        'list'      => 'bx-list-check',
        'category'  => 'bx-category',
        'dish'      => 'bx-dish',
        'menu-card' => 'bx-food-menu',
        'user'      => 'bx-user',
        'like'      => 'bx-like',
        'trophy'    => 'bx-trophy',
        'book'      => 'bx-book-open',
        // Weitere Menü-Icons – damit Module mit vielen Unterpunkten nicht
        // mehrere Zeilen mit demselben Symbol zeigen müssen.
        'door'         => 'bx-door-open',
        'layers'       => 'bx-layer',
        'chalkboard'   => 'bx-chalkboard',
        'book-content' => 'bx-book-content',
        'quote'        => 'bx-comment-detail',
        'layout'       => 'bx-layout',
        'import'       => 'bx-import',
        'transfer'     => 'bx-transfer-alt',
        'envelope'     => 'bx-envelope',
        'send'         => 'bx-send',
        'default'   => 'bx-grid-alt',
    ];
    $icon = $icons[$name] ?? $icons['default'];
@endphp

<i {{ $attributes->merge(['class' => "bx {$icon} leading-none align-middle"]) }} aria-hidden="true"></i>
