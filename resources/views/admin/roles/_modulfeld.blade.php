{{-- Handzuordnung einer Rolle zu einem Modul. Erwartet $module (key => Module)
     und $aktuell (Modul-Key oder null). --}}
<div>
    <label for="modul" class="block text-sm font-medium text-gray-700">Modul</label>
    <select id="modul" name="modul"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">– keins (gilt immer) –</option>
        @foreach ($module as $key => $modul)
            <option value="{{ $key }}" @selected(old('modul', $aktuell) === $key)>
                {{ $modul->name }}@unless ($modul->is_enabled) (abgeschaltet)@endunless
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-400">
        Eine Modulrolle steht beim Modul zur Auswahl und ruht, solange das Modul abgeschaltet ist.
    </p>
    @error('modul') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
