{{-- Eine Rolle an einem Menüpunkt: keine / lesen / bearbeiten / verwalten.
     `name` ist das Formularfeld (z. B. item_roles[12][lehrer]), `stufe` der
     aktuelle Wert (null = nicht zugeordnet). `nurEntfernen` bietet nur „–" und
     den aktuellen Wert an (Rolle eines fremden Moduls). Der Rollenname kommt als Slot. --}}
@props(['name', 'stufe' => null, 'nurEntfernen' => false, 'hinweis' => null])

@php
    $stufen = collect(\App\Modules\Support\Zugriffsstufe::cases())
        ->when($nurEntfernen, fn ($c) => $c->filter(fn ($s) => $s->value === $stufe));
@endphp

<label x-data="{ s: @js((string) $stufe) }"
       @if ($hinweis) title="{{ $hinweis }}" @endif
       class="inline-flex items-center gap-1 rounded-lg border py-0.5 pl-2.5 pr-0.5 text-sm transition-colors"
       :class="{
           'border-gray-200 bg-white text-gray-500': s === '',
           'border-sky-300 bg-sky-100 text-sky-900': s === 'lesen',
           'border-amber-300 bg-amber-100 text-amber-900': s === 'bearbeiten',
           'border-indigo-300 bg-indigo-100 text-indigo-900': s === 'verwalten',
       }">
    <span>{{ $slot }}</span>
    <select name="{{ $name }}" x-model="s"
            class="cursor-pointer rounded-md border-0 bg-transparent py-0.5 pl-1 pr-7 text-xs font-medium focus:ring-1 focus:ring-indigo-500">
        <option value="">–</option>
        @foreach ($stufen as $option)
            <option value="{{ $option->value }}" @selected($option->value === $stufe)>{{ $option->label() }}</option>
        @endforeach
    </select>
</label>
