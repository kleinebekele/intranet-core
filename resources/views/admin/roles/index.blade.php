<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Systemeinstellungen</h1>
    </x-slot>

    <div x-data="{ detachOpen: false, detachAction: '', detachRole: '', detachCount: 0 }">
        @include('admin.partials.tabs')

        @if ($errors->any())
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <i class='bx bx-error-circle text-lg leading-none'></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="mb-6 flex items-center justify-between">
            <p class="text-gray-600">
                Rollen bündeln Berechtigungen. <span class="font-medium">admin</span> und
                <span class="font-medium">user</span> sind feste System-Rollen; jeder Benutzer hat automatisch
                <span class="font-medium">user</span>.
            </p>
            <a href="{{ route('admin.roles.create') }}"
               class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <i class='bx bx-plus text-lg'></i>
                Neue Rolle
            </a>
        </div>

        @if ($roles->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Es sind noch keine Rollen angelegt.
                <div class="mt-1 text-sm text-gray-400">
                    Lege eine an – oder importiere Benutzer, dann entstehen unbekannte Rollen automatisch.
                </div>
            </div>
        @else
            @foreach ($gruppen as $gruppenName => $rollenDerGruppe)
            @php
                // System immer sichtbar, von Hand angelegte offen, der Rest zugeklappt.
                $klappbar = $gruppenName !== 'System';
                $offen = ! $klappbar || $gruppenName === 'Von Hand angelegt';
            @endphp
            <div x-data="{ offen: {{ $offen ? 'true' : 'false' }} }" class="{{ $loop->first ? '' : 'mt-6' }}">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                @if ($klappbar)
                    <button type="button" @click="offen = ! offen" :aria-expanded="offen"
                            class="flex w-full items-center gap-2 rounded-lg py-1 text-left uppercase hover:text-gray-700">
                        <i class='bx bx-chevron-right text-lg transition-transform' :class="offen && 'rotate-90'"></i>
                        <i class='bx {{ $rollenDerGruppe->first()->gehoertZuModul() ? 'bx-cube' : 'bx-group' }}'></i>
                        {{ $gruppenName }}
                        <span class="font-normal text-gray-400">({{ $rollenDerGruppe->count() }})</span>
                    </button>
                @else
                    <span class="flex items-center gap-2 py-1">
                        <i class='bx bx-lock-alt'></i>
                        {{ $gruppenName }}
                        <span class="font-normal text-gray-400">({{ $rollenDerGruppe->count() }})</span>
                    </span>
                @endif
            </h2>
            <ul class="space-y-3" x-show="offen" @unless ($offen) x-cloak @endunless>
                @foreach ($rollenDerGruppe as $role)
                    <li class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 text-xl">
                            <i class='bx bx-id-card leading-none'></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-800">
                                {{ $role->name }}
                                @if ($role->gehoertZuModul())
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium align-middle {{ $role->istAktiv() ? 'bg-indigo-50 text-indigo-700' : 'bg-amber-50 text-amber-700' }}"
                                          title="{{ ! $role->istAktiv() ? 'Das Modul ist deaktiviert oder nicht installiert – die Rolle gilt gerade nicht' : ($role->modul_von_hand ? 'Von Hand diesem Modul zugeordnet' : 'Diese Rolle bringt das Modul mit') }}">
                                        <i class='bx bx-cube'></i> {{ $role->modul }}@if ($role->plattformweit) · plattformweit @endif
                                        @if ($role->modul_von_hand) · von Hand @endif
                                        @unless ($role->istAktiv()) · inaktiv @endunless
                                    </span>
                                @endif
                                @if ($role->isSystem() && ! $role->gehoertZuModul())
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 align-middle">
                                        <i class='bx bx-lock-alt'></i> System
                                    </span>
                                @elseif ($role->istVerwaltet())
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 align-middle"
                                          title="Mitglieder pflegt der Abgleich „{{ $role->quelle }}“ automatisch">
                                        <i class='bx bx-refresh'></i> {{ $role->quelle }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $role->role_id }}</code>
                                &middot; <a href="{{ route('admin.roles.mitglieder', $role) }}" class="hover:text-indigo-600 hover:underline">{{ $role->users_count }} Mitglieder</a>
                            </div>
                        </div>

                        <div class="flex items-center gap-1 text-xl">
                            <a href="{{ route('admin.roles.mitglieder', $role) }}" title="Mitglieder"
                               class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                <i class='bx bx-group'></i>
                            </a>
                            @if ($role->role_id !== 'admin')
                                <a href="{{ route('admin.roles.sichtbarkeit', $role) }}" title="Sichtbarkeit"
                                   @click.prevent="$dispatch('sichtbarkeit-oeffnen', @js($role->role_id))"
                                   class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <i class='bx bx-show'></i>
                                </a>
                            @else
                                <x-schloss-tipp icon="bx-show">
                                    Administratoren sehen ohnehin alle Seiten – eine Sichtbarkeit lässt sich hier nicht einstellen.
                                </x-schloss-tipp>
                            @endif
                            {{-- Rollen, die ein Abgleich pflegt oder ein Modul mitbringt, gehören
                                 nicht dem Panel: nur ansehen, nichts ändern. --}}
                            @if ($role->istVerwaltet() || $role->stammtAusManifest())
                                <x-schloss-tipp>
                                    @if ($role->istVerwaltet())
                                        Diese Rolle legt der Abgleich „{{ $role->quelle }}“ an. Name und Mitglieder
                                        kommen von dort und können hier nicht verändert werden.
                                    @else
                                        Systemrolle des Moduls „{{ $modulNamen[$role->modul] ?? $role->modul }}“. Name und Bestand
                                        kommen aus dem Modul und können hier nicht verändert werden – Mitglieder und
                                        Sichtbarkeit schon.
                                    @endif
                                </x-schloss-tipp>
                            @else
                            <a href="{{ route('admin.roles.edit', $role) }}" title="Bearbeiten"
                               class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                <i class='bx bx-edit'></i>
                            </a>
                            @endif

                            @if (($role->istVerwaltet() && $role->users_count > 0) || ($role->stammtAusManifest() && $role->users_count === 0))
                                {{-- Schloss steht schon oben. Modulrollen behalten „Alle
                                     Zuweisungen aufheben" – ihre Mitglieder pflegt man von Hand.
                                     Abgeglichene Rollen OHNE Mitglieder darf man löschen: der
                                     Abgleich leert verwaiste Gruppen (alte Klassen), löscht sie
                                     aber nicht. --}}
                            @elseif ($role->isSystem() && ! $role->gehoertZuModul())
                                <x-schloss-tipp>
                                    Feste System-Rolle der Plattform – sie kann nicht gelöscht und ihre Zuweisungen
                                    können nicht gesammelt aufgehoben werden.
                                </x-schloss-tipp>
                            @elseif ($role->users_count > 0)
                                <button type="button" title="Alle Zuweisungen aufheben"
                                        @click="detachOpen = true; detachAction = '{{ route('admin.roles.detach-all', $role) }}'; detachRole = @js($role->name); detachCount = {{ $role->users_count }}"
                                        class="rounded-md p-1.5 text-amber-500 hover:bg-amber-50 hover:text-amber-700">
                                    <i class='bx bx-unlink'></i>
                                </button>
                            @else
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                      onsubmit="return confirm('Rolle „{{ $role->name }}“ wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Löschen"
                                            class="block rounded-md p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            </div>
            @endforeach
        @endif

        {{-- Hinweisfenster: Alle Zuweisungen aufheben --}}
        <div x-show="detachOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/40" @click="detachOpen = false"></div>

            <div class="relative z-50 w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 text-2xl">
                        <i class='bx bx-error leading-none'></i>
                    </span>
                    <div>
                        <h3 class="text-lg font-medium text-gray-800">Alle Zuweisungen aufheben?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Du hebst alle Zuweisungen der Rolle
                            <span class="font-medium" x-text="detachRole"></span>
                            (<span x-text="detachCount"></span> Benutzer) auf.
                        </p>
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            Achtung: Das Aufheben von Rollen kann dazu führen, dass gewisse Module nicht mehr
                            ordnungsgemäß arbeiten.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="detachOpen = false"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        Abbrechen
                    </button>
                    <form :action="detachAction" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            <i class='bx bx-unlink text-base'></i>
                            Ja, aufheben
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Sichtbarkeit als Modal: Formular per fetch nachladen und speichern,
             ohne die Seite neu zu laden; mit Blättern zur vorigen/nächsten Rolle. --}}
        <div x-data="sichtbarkeitsModal(@js($sichtbarkeitsFolge))"
             @sichtbarkeit-oeffnen.window="oeffnen($event.detail)"
             @keydown.escape.window="escape()">
            <div x-show="offen" x-cloak class="fixed inset-0 z-50 flex p-2 sm:p-6">
                <div class="fixed inset-0 bg-gray-900/40" @click="schliessen()"></div>

                <div class="relative flex max-h-full w-full flex-col rounded-xl bg-gray-50 shadow-xl">
                    <div class="flex flex-wrap items-center gap-3 rounded-t-xl border-b border-gray-200 bg-white px-4 py-3">
                        <i class='bx bx-show text-xl text-indigo-600'></i>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-lg font-medium text-gray-800">
                                Sichtbarkeit: <span x-text="rolle?.name"></span>
                            </h3>
                            <div class="text-xs text-gray-400">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5" x-text="rolle?.id"></code>
                                · Rolle <span x-text="index + 1"></span> von <span x-text="folge.length"></span>
                                · Lesen &lt; Bearbeiten &lt; Verwalten (anlegen/löschen)
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="blaettern(-1)" :disabled="index <= 0 || laedt || speichert"
                                    class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                                    :title="dirty ? 'Speichern und zur vorigen Rolle' : 'Vorige Rolle'">
                                <i class='bx bx-chevron-left text-lg'></i>
                                <span x-text="dirty ? 'Speichern & zurück' : 'Vorige'"></span>
                            </button>
                            <button type="button" @click="blaettern(1)" :disabled="index >= folge.length - 1 || laedt || speichert"
                                    class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                                    :title="dirty ? 'Speichern und zur nächsten Rolle' : 'Nächste Rolle'">
                                <span x-text="dirty ? 'Speichern & weiter' : 'Nächste'"></span>
                                <i class='bx bx-chevron-right text-lg'></i>
                            </button>
                            <button type="button" @click="schliessen()" title="Schließen"
                                    class="ml-1 rounded-lg p-2 text-xl text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                    </div>

                    <div class="relative min-h-40 flex-1 overflow-y-auto p-4" x-ref="inhalt"
                         @change="dirty = true" @submit.prevent="speichern()"></div>
                    <div x-show="laedt" class="pointer-events-none absolute inset-x-0 top-24 flex justify-center">
                        <span class="rounded-full bg-white px-3 py-1 text-sm text-gray-500 shadow">
                            <i class='bx bx-loader-alt bx-spin'></i> lädt …
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 rounded-b-xl border-t border-gray-200 bg-white px-4 py-3">
                        <button type="button" @click="speichern()" :disabled="laedt || speichert"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40">
                            <i class='bx text-base' :class="speichert ? 'bx-loader-alt bx-spin' : 'bx-save'"></i>
                            Speichern
                        </button>
                        <span x-show="dirty" class="text-xs text-amber-600">Ungespeicherte Änderungen</span>
                        <span x-show="meldung" x-transition.opacity x-text="meldung"
                              class="text-sm" :class="fehler ? 'text-red-600' : 'text-green-700'"></span>
                        <a :href="rolle?.url" class="ml-auto text-xs text-gray-400 hover:text-gray-600">Als eigene Seite öffnen</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.sichtbarkeitsModal = (folge) => ({
                folge,
                offen: false,
                index: -1,
                laedt: false,
                speichert: false,
                dirty: false,
                meldung: '',
                fehler: false,
                fragt: false,
                meldungsUhr: null,

                get rolle() {
                    return this.folge[this.index] ?? null;
                },

                oeffnen(id) {
                    const i = this.folge.findIndex((r) => r.id === id);
                    if (i < 0) return;
                    this.offen = true;
                    this.meldung = '';
                    this.laden(i);
                },

                async laden(i) {
                    this.index = i;
                    this.dirty = false;
                    this.laedt = true;
                    try {
                        const antwort = await fetch(this.rolle.url, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        });
                        if (!antwort.ok) throw new Error('HTTP ' + antwort.status);
                        this.$refs.inhalt.innerHTML = await antwort.text();
                        this.$refs.inhalt.scrollTop = 0;
                    } catch (e) {
                        this.$refs.inhalt.innerHTML = '';
                        this.zeige('Laden fehlgeschlagen (' + e.message + ').', true);
                    } finally {
                        this.laedt = false;
                    }
                },

                async speichern() {
                    const formular = this.$refs.inhalt.querySelector('form[data-sichtbarkeit-formular]');
                    if (!formular) return true;
                    this.speichert = true;
                    try {
                        const antwort = await fetch(formular.action, {
                            method: 'POST',
                            body: new FormData(formular),
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        });
                        const daten = await antwort.json().catch(() => ({}));
                        if (!antwort.ok) throw new Error(daten.message || 'HTTP ' + antwort.status);
                        this.dirty = false;
                        this.zeige(daten.status || 'Gespeichert.');
                        return true;
                    } catch (e) {
                        this.zeige('Speichern fehlgeschlagen: ' + e.message, true);
                        return false;
                    } finally {
                        this.speichert = false;
                    }
                },

                async blaettern(schritt) {
                    const ziel = this.index + schritt;
                    if (ziel < 0 || ziel >= this.folge.length) return;
                    if (this.dirty && !(await this.speichern())) return;
                    this.laden(ziel);
                },

                // Escape erst nach dem Tastendruck auswerten: sonst schließt der
                // Core-Dialog die gerade geöffnete Rückfrage mit derselben Taste.
                // Läuft die Rückfrage schon, gehört Escape ihr allein.
                escape() {
                    if (this.offen && !this.fragt) setTimeout(() => this.schliessen());
                },

                async schliessen() {
                    if (this.fragt) return;
                    if (this.dirty) {
                        this.fragt = true;
                        try {
                            if (!(await window.bestaetige('Die Änderungen an dieser Rolle sind nicht gespeichert. Verwerfen?', { knopf: 'Verwerfen' }))) {
                                return;
                            }
                        } finally {
                            setTimeout(() => (this.fragt = false));
                        }
                    }
                    this.offen = false;
                    this.dirty = false;
                    this.$refs.inhalt.innerHTML = '';
                },

                zeige(text, fehler = false) {
                    this.meldung = text;
                    this.fehler = fehler;
                    clearTimeout(this.meldungsUhr);
                    if (!fehler) this.meldungsUhr = setTimeout(() => (this.meldung = ''), 4000);
                },
            });
        </script>
    @endpush
</x-app-layout>
