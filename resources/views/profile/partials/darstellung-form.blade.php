@php
    $aktuell = $user->farbschema ?? 'hell';
    $wahl = [
        'hell' => ['Hell', 'bx-sun', 'Die gewohnte helle Darstellung.'],
        'dunkel' => ['Dunkel', 'bx-moon', 'Dunkler Hintergrund, schont die Augen am Abend.'],
        'system' => ['Wie System', 'bx-desktop', 'Folgt der Einstellung deines Geräts.'],
    ];
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Darstellung</h2>

        <p class="mt-1 text-sm text-gray-600">
            Helles oder dunkles Design für das ganze Intranet. Gilt für dein Konto auf allen Geräten.
        </p>
    </header>

    <form method="post" action="{{ route('profile.darstellung') }}" class="mt-6"
          x-data="{ wahl: @js($aktuell) }">
        @csrf
        @method('patch')

        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($wahl as $wert => [$titel, $icon, $text])
                <label class="flex cursor-pointer flex-col gap-1 rounded-lg border p-4"
                       :class="wahl === @js($wert) ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300'">
                    <span class="flex items-center gap-2 font-medium text-gray-900">
                        <input type="radio" name="farbschema" value="{{ $wert }}" x-model="wahl"
                               class="text-indigo-600 focus:ring-indigo-500">
                        <i class="bx {{ $icon }} text-lg text-gray-500"></i>
                        {{ $titel }}
                    </span>
                    <span class="text-sm text-gray-500">{{ $text }}</span>
                </label>
            @endforeach
        </div>

        <x-input-error class="mt-2" :messages="$errors->get('farbschema')" />

        <div class="mt-4 flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
