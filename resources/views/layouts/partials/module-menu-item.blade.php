{{--
    Eine Zeile im Modul-Menü.

    $item       – der ModuleMenuItem
    $activeItem – der gerade aktive Punkt (oder null)
--}}
<a href="{{ $item->url() ?? '#' }}"
   @class([
       'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
       'bg-indigo-50 text-indigo-700' => $item->is($activeItem),
       'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $item->is($activeItem),
   ])>
    @if ($item->icon)
        <x-module-icon :name="$item->icon" @class([
            'text-lg',
            'text-indigo-600' => $item->is($activeItem),
            'text-gray-400' => ! $item->is($activeItem),
        ]) />
    @else
        <span @class([
            'h-1.5 w-1.5 rounded-full',
            'bg-indigo-600' => $item->is($activeItem),
            'bg-gray-300' => ! $item->is($activeItem),
        ])></span>
    @endif
    {{ $item->label }}
</a>
