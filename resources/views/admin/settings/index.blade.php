<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Verwaltung</h1>
    </x-slot>
    <x-slot name="titel">Einstellungen</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
              class="grid gap-6 lg:grid-cols-2">
            @csrf
            @method('PUT')

            {{-- Erscheinungsbild --}}
            <section class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-medium text-gray-800">Erscheinungsbild</h2>
                <p class="mt-1 text-sm text-gray-500">Wie sich das Intranet im Browser zeigt.</p>

                <div class="mt-5 space-y-5">
                    <div>
                        <label for="haupttitel" class="block text-sm font-medium text-gray-700">Haupttitel</label>
                        <input type="text" name="haupttitel" id="haupttitel" maxlength="60"
                               value="{{ old('haupttitel', $haupttitel) }}"
                               placeholder="{{ $haupttitelStandard }}"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p class="mt-1.5 text-xs text-gray-500">
                            Steht am Anfang jedes Browser-Tabs. Leer lassen für den Standard
                            <span class="font-medium">{{ $haupttitelStandard }}</span>.
                        </p>
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-gray-700">Logo</span>

                        <div class="mt-2 flex items-center gap-4">
                            <span class="inline-flex h-12 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 px-2">
                                @if ($logoPfad)
                                    <img src="{{ parse_url(Storage::disk('public')->url($logoPfad), PHP_URL_PATH) }}"
                                         alt="Aktuelles Logo" class="max-h-10 max-w-full object-contain">
                                @else
                                    <i class='bx bx-image text-2xl text-gray-300'></i>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <input type="file" name="logo" accept=".png,.svg,.jpg,.jpeg,.webp"
                                       class="block w-full text-sm text-gray-600
                                              file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2
                                              file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1.5 text-xs text-gray-500">
                                    Steht in der Kopfzeile und auf der Anmeldeseite. PNG, SVG, JPG oder WebP,
                                    höchstens 1 MB. Ein breites Bild wirkt besser als ein quadratisches.
                                </p>
                            </div>
                        </div>

                        @if ($logoPfad)
                            <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="logo_entfernen" value="1"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Logo entfernen und Standard verwenden
                            </label>
                        @endif
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-gray-700">Favicon</span>

                        <div class="mt-2 flex items-center gap-4">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                @if ($faviconPfad)
                                    {{-- Wurzelrelativ, siehe layouts/favicon.blade.php --}}
                                    <img src="{{ parse_url(Storage::disk('public')->url($faviconPfad), PHP_URL_PATH) }}"
                                         alt="Aktuelles Favicon" class="max-h-10 max-w-10">
                                @else
                                    <i class='bx bx-image text-2xl text-gray-300'></i>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <input type="file" name="favicon" accept=".png,.ico,.svg,.jpg,.jpeg,.webp"
                                       class="block w-full text-sm text-gray-600
                                              file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2
                                              file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1.5 text-xs text-gray-500">
                                    PNG, ICO, SVG, JPG oder WebP, höchstens 512 KB. Quadratisch wirkt am besten.
                                </p>
                            </div>
                        </div>

                        @if ($faviconPfad)
                            <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="favicon_entfernen" value="1"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Favicon entfernen und Standard verwenden
                            </label>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Mailversand --}}
            <section class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-medium text-gray-800">Mailversand</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Wie viele Mails die Plattform je Stunde verschicken darf.
                </p>

                <div class="mt-5 space-y-5">
                    <div>
                        <label for="mail_stundenlimit" class="block text-sm font-medium text-gray-700">
                            Stundenlimit
                        </label>
                        <input type="number" name="mail_stundenlimit" id="mail_stundenlimit" min="0" step="1"
                               value="{{ old('mail_stundenlimit', $stundenlimit) }}"
                               placeholder="{{ $stundenlimitEnv ?: '0' }}"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p class="mt-1.5 text-xs text-gray-500">
                            Höchstzahl Mails je Stunde, wie vom Mailprovider vorgegeben.
                            <span class="font-medium">0 oder leer = kein Limit.</span>
                            Ohne Eintrag gilt der Wert aus der <code class="rounded bg-gray-100 px-1">.env</code>
                            (<code class="rounded bg-gray-100 px-1">MAIL_STUNDENLIMIT={{ $stundenlimitEnv }}</code>).
                        </p>
                    </div>

                    @unless ($outboxAktiv)
                        <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            <i class='bx bx-error text-base leading-none'></i>
                            <span>
                                Der Ausgangskorb ist abgeschaltet
                                (<code class="rounded bg-amber-100 px-1">MAIL_OUTBOX=false</code>) –
                                das Limit hat derzeit keine Wirkung.
                            </span>
                        </div>
                    @endunless

                    <p class="text-xs text-gray-400">
                        Was tatsächlich rausging, zeigt der Reiter
                        <a href="{{ route('admin.mail.index') }}" class="font-medium text-indigo-600 hover:underline">Maillog</a>.
                    </p>
                </div>
            </section>

            <div class="lg:col-span-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save text-base'></i>
                    Speichern
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
