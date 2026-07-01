import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

window.Alpine = Alpine;

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
