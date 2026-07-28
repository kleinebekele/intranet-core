{{--
    Der „?"-Knopf in der Kopfzeile. Er erscheint nur, wenn ein Modul (das Wiki)
    zur aktuellen Route eine Hilfeseite anbietet – ohne Anbieter rendert die
    Komponente nichts. Siehe App\Support\Hilfe.
--}}
@php($hilfeUrl = \App\Support\Hilfe::url())

@if ($hilfeUrl)
    <a href="{{ $hilfeUrl }}"
       title="Hilfe zu dieser Seite"
       class="inline-flex items-center justify-center h-9 w-9 rounded-md text-gray-500 hover:bg-gray-100 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <x-module-icon name="help" class="text-xl" />
        <span class="sr-only">Hilfe zu dieser Seite</span>
    </a>
@endif
