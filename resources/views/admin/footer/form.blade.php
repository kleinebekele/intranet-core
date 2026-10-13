<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Systemeinstellungen</h1>
    </x-slot>
    <x-slot name="titel">Footer</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ implode(' ', $errors->all()) }}</div>
        @endif

        <form method="POST" action="{{ $link->exists ? route('admin.footer.update', $link) : route('admin.footer.store') }}"
              x-data="{ art: @js(old('art', $link->art)) }" class="space-y-6">
            @csrf
            @if ($link->exists)
                @method('PUT')
            @endif

            <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-6 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="font-medium text-gray-700">Titel im Footer</span>
                    <input name="titel" value="{{ old('titel', $link->titel) }}" required maxlength="100" placeholder="Impressum"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                </label>

                <div class="text-sm">
                    <span class="font-medium text-gray-700">Art</span>
                    <div class="mt-2 flex gap-4">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="art" value="intern" x-model="art"> Eigene Seite</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="art" value="extern" x-model="art"> Externer Link</label>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm sm:pt-6">
                    <input type="checkbox" name="aktiv" value="1" @checked(old('aktiv', $link->aktiv)) class="rounded border-gray-300 text-indigo-600">
                    Im Footer anzeigen
                </label>

                <label class="block text-sm sm:col-span-3" x-show="art === 'extern'" x-cloak>
                    <span class="font-medium text-gray-700">Adresse</span>
                    <input name="url" value="{{ old('url', $link->url) }}" placeholder="https://…"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                    <span class="mt-1 block text-xs text-gray-500">Öffnet sich in einem neuen Tab.</span>
                </label>

                <label class="block text-sm sm:col-span-3" x-show="art === 'intern'">
                    <span class="font-medium text-gray-700">Adresse der Seite</span>
                    <span class="mt-1 flex items-center gap-1">
                        <span class="text-gray-500">{{ url('/seite') }}/</span>
                        <input name="slug" value="{{ old('slug', $link->slug) }}" placeholder="aus dem Titel" maxlength="100"
                               class="block w-64 rounded-lg border-gray-300 text-sm shadow-sm">
                    </span>
                    <span class="mt-1 block text-xs text-gray-500">Kleinbuchstaben, Ziffern, Bindestrich. Leer = aus dem Titel. Die Seite ist auch ohne Anmeldung erreichbar.</span>
                </label>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-6" x-show="art === 'intern'">
                <div>
                    <h2 class="mb-2 text-sm font-semibold text-gray-700">Inhalt</h2>
                    <x-html-editor name="inhalt" :wert="old('inhalt', $link->inhalt)" />
                </div>

                <label class="block text-sm">
                    <span class="font-medium text-gray-700">Einbindung (optional)</span>
                    <span class="mt-1 block text-xs text-gray-500">
                        HTML oder <code>&lt;script&gt;</code> eines Dienstleisters, z. B. eine Datenschutzerklärung, die sich selbst aktuell hält.
                        Wird unverändert unter dem Inhalt eingefügt und ausgeführt – nur Code aus vertrauenswürdiger Quelle eintragen.
                    </span>
                    <textarea name="einbindung" rows="5" spellcheck="false" placeholder='&lt;div id="datenschutz"&gt;&lt;/div&gt;&lt;script src="https://…"&gt;&lt;/script&gt;'
                              class="mt-2 block w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('einbindung', $link->einbindung) }}</textarea>
                </label>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save text-base'></i> Speichern
                </button>
                <a href="{{ route('admin.footer.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                @if ($link->exists && $link->istIntern())
                    <a href="{{ $link->href() }}" target="_blank" rel="noopener" class="ml-auto text-sm text-indigo-700 hover:underline">Seite ansehen ↗</a>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
