import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

window.Alpine = Alpine;

/**
 * Eigener Dialog statt der Browser-Popups (confirm/alert).
 *
 * Drei Wege, alle landen im selben Modal (layouts/dialog.blade.php):
 *  1. `window.bestaetige(text, {titel, knopf, gefaehrlich})` → Promise<boolean>
 *     und `window.hinweis(text, {titel})` → Promise<void> – für eigenes JS.
 *  2. Formulare/Knöpfe mit `data-bestaetigen="Frage?"` (optional `data-titel`,
 *     `data-knopf`) – der bevorzugte Weg in Blade.
 *  3. Bestandsschutz: `onsubmit="return confirm('…')"` und `onclick="return
 *     confirm('…')"` werden abgefangen, der Text aus dem Attribut gelesen und
 *     im Modal gestellt. Module müssen dafür nichts ändern. `window.alert`
 *     zeigt ebenfalls das Modal.
 */
Alpine.store('dialog', {
    offen: false,
    titel: '',
    text: '',
    knopf: 'OK',
    abbrechen: true,
    gefaehrlich: false,
    _resolve: null,

    frage(text, { titel = 'Bitte bestätigen', knopf = 'OK', abbrechen = true, gefaehrlich = false } = {}) {
        this.titel = titel;
        this.text = text;
        this.knopf = knopf;
        this.abbrechen = abbrechen;
        this.gefaehrlich = gefaehrlich;
        this.offen = true;

        return new Promise((resolve) => { this._resolve = resolve; });
    },

    antworte(ja) {
        this.offen = false;
        const resolve = this._resolve;
        this._resolve = null;
        if (resolve) resolve(ja);
    },
});

window.bestaetige = (text, optionen = {}) =>
    Alpine.store('dialog').frage(text, { gefaehrlich: /löschen|entfernen|verwerfen|zurücksetzen/i.test(text), ...optionen });

window.hinweis = (text, optionen = {}) =>
    Alpine.store('dialog').frage(text, { titel: 'Hinweis', abbrechen: false, ...optionen }).then(() => undefined);

window.alert = (text) => { window.hinweis(String(text)); };

/** Text aus `return confirm('…')` bzw. `confirm("…")` herauslesen. */
function confirmText(quelle) {
    const m = /confirm\(\s*(['"])([\s\S]*?)\1\s*\)/.exec(quelle ?? '');

    return m ? m[2].replace(/\\(['"])/g, '$1') : null;
}

/**
 * Abfangen, BEVOR der Inline-Handler läuft: Ein capturing-Listener am
 * Dokument sieht das Ereignis vor dem Ziel; stopPropagation dort verhindert,
 * dass der Inline-`onsubmit`/`onclick` (und damit das native confirm) je
 * ausgeführt wird. Nach dem Ja wird das Inline-Attribut entfernt und das
 * Ereignis erneut ausgelöst.
 */
function bestaetigeUndWiederhole(el, text, optionen, wiederholen) {
    window.bestaetige(text, optionen).then((ja) => {
        if (! ja) return;
        el.dataset.bestaetigt = '1';
        try {
            wiederholen();
        } finally {
            // Marke sofort wieder weg: Ein Knopf, der per Fetch arbeitet und auf
            // der Seite bleibt, soll beim nächsten Klick wieder fragen.
            delete el.dataset.bestaetigt;
        }
    });
}

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (! (form instanceof HTMLFormElement) || form.dataset.bestaetigt === '1') return;

    const text = form.dataset.bestaetigen ?? confirmText(form.getAttribute('onsubmit'));
    if (text === null || text === undefined || text === '') return;

    e.preventDefault();
    e.stopPropagation();

    bestaetigeUndWiederhole(form, text, { titel: form.dataset.titel, knopf: form.dataset.knopf }, () => {
        form.removeAttribute('onsubmit');
        form.onsubmit = null;
        form.requestSubmit();
    });
}, true);

document.addEventListener('click', (e) => {
    const el = e.target instanceof Element ? e.target.closest('[data-bestaetigen], [onclick]') : null;
    if (! el || el instanceof HTMLFormElement || el.dataset.bestaetigt === '1') return;

    const text = el.dataset.bestaetigen ?? confirmText(el.getAttribute('onclick'));
    if (text === null || text === undefined || text === '') return;

    e.preventDefault();
    e.stopPropagation();

    bestaetigeUndWiederhole(el, text, { titel: el.dataset.titel, knopf: el.dataset.knopf }, () => {
        if (el.getAttribute('onclick')) {
            el.removeAttribute('onclick');
            el.onclick = null;
        }
        el.click();
    });
}, true);

Alpine.start();

/**
 * Generic drag-and-drop ordering.
 *
 * Any element with `data-sortable="<url>"` becomes sortable. When the order
 * changes, the ids of its direct children (each carrying `data-id`) are POSTed
 * to that url as { ids: [...] }. An optional `data-handle="<selector>"` limits
 * dragging to a handle element.
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function initSortable(el) {
    Sortable.create(el, {
        handle: el.dataset.handle || '[data-drag-handle]',
        animation: 150,
        onEnd() {
            const ids = [...el.querySelectorAll(':scope > [data-id]')].map((node) => node.dataset.id);

            fetch(el.dataset.sortable, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ ids }),
            });
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sortable]').forEach(initSortable);
});
