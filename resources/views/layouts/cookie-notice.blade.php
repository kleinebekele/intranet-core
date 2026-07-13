{{--
    Schlichter Cookie-Hinweis: reine Information, dass die Seite Cookies nutzt,
    die aber ausnahmslos technisch notwendig sind. Es gibt nichts abzulehnen –
    nur einen "Verstanden"-Knopf, der den Hinweis wegklickt und die Bestätigung
    lokal im Browser merkt (localStorage, kein zusätzliches Cookie).
--}}
<div
    x-data="{ show: false }"
    x-init="show = ! localStorage.getItem('cookieHinweisOk')"
    x-show="show"
    x-cloak
    x-transition.opacity
    class="fixed inset-x-0 bottom-0 z-50 px-4 pb-4 sm:px-6"
    role="dialog"
    aria-label="Cookie-Hinweis"
>
    <div class="mx-auto max-w-3xl rounded-lg border border-gray-200 bg-white shadow-lg">
        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:py-4">
            <p class="flex-1 text-sm leading-relaxed text-gray-700">
                Diese Seite verwendet Cookies. Sie sind ausschließlich
                <strong class="font-semibold text-gray-900">technisch notwendig</strong>
                für den Betrieb der Seite – es werden keine Cookies zu Analyse- oder
                Marketingzwecken gesetzt. Es gibt daher nichts abzulehnen.
            </p>
            <button
                type="button"
                @click="localStorage.setItem('cookieHinweisOk', '1'); show = false"
                class="shrink-0 rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
            >
                Verstanden
            </button>
        </div>
    </div>
</div>
