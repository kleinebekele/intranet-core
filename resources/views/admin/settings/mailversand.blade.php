<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Systemeinstellungen</h1>
    </x-slot>
    <x-slot name="titel">Mailversand</x-slot>

    <div class="w-full">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.mailversand.update') }}"
              class="grid gap-6 lg:grid-cols-2">
            @csrf
            @method('PUT')

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
                               placeholder="0"
                               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p class="mt-1.5 text-xs text-gray-500">
                            Höchstzahl Mails je Stunde, wie vom Mailprovider vorgegeben.
                            <span class="font-medium">0 oder leer = kein Limit</span> – das ist der
                            Normalfall. Gezählt wird gleitend über die letzten 60 Minuten.
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
