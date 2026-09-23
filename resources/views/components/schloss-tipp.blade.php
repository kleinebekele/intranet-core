{{-- Ausgegrautes Symbol mit Hinweis beim Überfahren (bzw. Antippen auf dem
     Handy – daher tabindex/focus). Der Text kommt als Slot. --}}
@props(['icon' => 'bx-lock-alt'])

<span tabindex="0" class="group relative inline-block cursor-help rounded-md p-1.5 text-gray-300 outline-none focus:text-gray-400">
    <i class='bx {{ $icon }}'></i>
    <span role="tooltip"
          class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 hidden w-72 rounded-lg bg-gray-800 px-3 py-2 text-left text-xs font-normal normal-case leading-snug tracking-normal text-white shadow-lg group-hover:block group-focus:block">
        {{ $slot }}
    </span>
</span>
