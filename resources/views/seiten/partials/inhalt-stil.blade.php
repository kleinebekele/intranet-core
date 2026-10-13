{{-- Gestaltung für HTML aus dem Editor (eigene Seiten wie Impressum, Datenschutz).
     Echtes CSS statt Tailwind-Klassen: die Regeln greifen auf Elemente, die erst
     im Editor entstehen. Farben über die Variablen, damit das dunkle Design passt. --}}
<style>
    .seiten-inhalt { line-height: 1.65; }
    .seiten-inhalt > * + * { margin-top: 0.75rem; }
    .seiten-inhalt h2 { font-size: 1.35rem; font-weight: 600; margin-top: 1.5rem; color: var(--color-gray-900, #111827); }
    .seiten-inhalt h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1.25rem; color: var(--color-gray-800, #1f2937); }
    .seiten-inhalt h2:first-child, .seiten-inhalt h3:first-child { margin-top: 0; }
    .seiten-inhalt ul { list-style: disc; padding-left: 1.5rem; }
    .seiten-inhalt ol { list-style: decimal; padding-left: 1.5rem; }
    .seiten-inhalt a { color: var(--color-indigo-600, #4f46e5); text-decoration: underline; }
    .seiten-inhalt img, .seiten-inhalt table, .seiten-inhalt iframe { max-width: 100%; }
    .seiten-inhalt img { height: auto; }
    .seiten-inhalt table { border-collapse: collapse; }
    .seiten-inhalt td, .seiten-inhalt th { border: 1px solid var(--color-gray-200, #e5e7eb); padding: 0.25rem 0.5rem; }
</style>
