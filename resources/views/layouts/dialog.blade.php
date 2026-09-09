{{--
    Der eine Dialog für Rückfragen und Hinweise – Ersatz für confirm()/alert().
    Gefüttert wird er über den Alpine-Store „dialog" (resources/js/app.js):
    window.bestaetige(), window.hinweis(), data-bestaetigen="…" an Formularen
    und Knöpfen, sowie – ungeändert – onsubmit="return confirm('…')".
--}}
<div x-data x-show="$store.dialog.offen" x-cloak
     x-transition.opacity.duration.150ms
     @keydown.escape.window="$store.dialog.offen && $store.dialog.antworte(false)"
     class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50"
     role="dialog" aria-modal="true" :aria-label="$store.dialog.titel">
    <div @click.outside="$store.dialog.antworte(false)"
         x-show="$store.dialog.offen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="px-5 py-4">
            <h3 class="font-semibold text-gray-800" x-text="$store.dialog.titel"></h3>
            <p class="mt-2 text-sm text-gray-700 whitespace-pre-line" x-text="$store.dialog.text"></p>
        </div>
        <div class="flex justify-end gap-2 border-t px-5 py-3">
            <button type="button" x-show="$store.dialog.abbrechen"
                    @click="$store.dialog.antworte(false)"
                    class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                Abbrechen
            </button>
            <button type="button" x-ref="ok"
                    x-effect="if ($store.dialog.offen) $nextTick(() => $refs.ok.focus())"
                    @click="$store.dialog.antworte(true)"
                    :class="$store.dialog.gefaehrlich ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                    class="rounded-md px-4 py-2 text-sm font-medium text-white"
                    x-text="$store.dialog.knopf"></button>
        </div>
    </div>
</div>
