<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Profilbild</h2>
        <p class="mt-1 text-sm text-gray-600">
            Erscheint oben rechts neben deinem Namen. Das Bild wird quadratisch zugeschnitten (Mitte).
        </p>
    </header>

    <div class="mt-6 flex items-center gap-6">
        <x-profilbild :user="$user" groesse="h-24 w-24 text-3xl" />

        <div class="space-y-3">
            <form method="post" action="{{ route('profile.profilbild') }}" enctype="multipart/form-data">
                @csrf
                <label class="inline-flex cursor-pointer items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500">
                    {{ $user->profilbild ? 'Bild ändern' : 'Bild hochladen' }}
                    <input type="file" name="profilbild" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="this.form.submit()">
                </label>
                <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP oder GIF, höchstens 8 MB.</p>
                <x-input-error class="mt-2" :messages="$errors->get('profilbild')" />
            </form>

            @if ($user->profilbild)
                <form method="post" action="{{ route('profile.profilbild') }}">
                    @csrf
                    <input type="hidden" name="entfernen" value="1">
                    <button type="submit" class="text-sm text-red-600 hover:underline">Bild entfernen</button>
                </form>
            @endif
        </div>
    </div>
</section>
