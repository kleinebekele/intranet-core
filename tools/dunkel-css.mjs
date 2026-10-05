// Erzeugt resources/css/dunkel.css – das dunkle Farbschema.
//
//   node tools/dunkel-css.mjs
//
// Idee: Tailwind 4 führt jede Farbe als CSS-Variable (--color-gray-100 …). Unter
// <html class="dark"> legen wir die Variablen um (Grau invertiert, helle Farbtöne
// werden dunkle Tönungen, dunkle Textfarben werden hell). Damit wird jede View
// dunkel, ohne dass Core oder Module dark:-Klassen brauchen.
//
// Dieselbe Variable dient aber als Hintergrund UND als Textfarbe: text-indigo-700
// soll hell werden, ein Knopf mit bg-indigo-700 soll dunkel bleiben. Deshalb
// werden kräftige Hintergründe (bg-*, from-/via-/to-*) zusätzlich als Klasse auf
// den Originalwert festgenagelt. Die Werte liest das Skript aus Tailwinds theme.css,
// nach einem Tailwind-Update also neu erzeugen.

import { readFileSync, writeFileSync } from 'node:fs';

const theme = readFileSync(new URL('../node_modules/tailwindcss/theme.css', import.meta.url), 'utf8');
const farbe = {};
for (const [, name, wert] of theme.matchAll(/--color-([a-z]+-\d+):\s*([^;]+);/g)) farbe[name] = wert.trim();

const NEUTRAL = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'mauve', 'olive', 'mist', 'taupe'].filter(h => farbe[`${h}-500`]);
const BUNT = [...new Set(Object.keys(farbe).map(n => n.split('-')[0]))].filter(h => !NEUTRAL.includes(h));

const mix = (a, b, anteil) => `color-mix(in oklab, ${a} ${anteil}%, ${b})`;
const zeilen = [];
const v = (name, wert) => zeilen.push(`    --color-${name}: ${wert};`);

// Fläche einer Karte (bg-white) und der Seite (bg-gray-100) im Dunkeln.
const KARTE = farbe['gray-900'];

// --- Variablen umlegen ----------------------------------------------------
for (const h of NEUTRAL) {
    const f = s => farbe[`${h}-${s}`];
    v(`${h}-50`, mix(f(900), f(800), 75));   // Tabellenkopf, Streifen: knapp heller als die Karte
    v(`${h}-100`, mix(f(950), f(900), 55));  // Seitenhintergrund: dunkler als die Karte
    v(`${h}-200`, f(800));                   // Rahmen, Trennlinien
    v(`${h}-300`, f(700));                   // Eingabefeld-Rahmen
    v(`${h}-400`, f(500));
    v(`${h}-500`, f(400));                   // Nebentext
    v(`${h}-600`, f(300));
    v(`${h}-700`, f(200));
    v(`${h}-800`, f(100));
    v(`${h}-900`, f(50));
    v(`${h}-950`, '#fff');
}
for (const h of BUNT) {
    const f = s => farbe[`${h}-${s}`];
    v(`${h}-50`, mix(f(950), KARTE, 45));    // Hinweisflächen, aktive Menüpunkte
    v(`${h}-100`, mix(f(900), KARTE, 55));   // Abzeichen
    v(`${h}-200`, mix(f(800), KARTE, 70));
    v(`${h}-300`, f(700));
    v(`${h}-500`, f(400));                   // Symbole, Links
    v(`${h}-600`, f(400));
    v(`${h}-700`, f(300));                   // Text auf Abzeichen/Hinweisen
    v(`${h}-800`, f(200));
    v(`${h}-900`, f(100));
    v(`${h}-950`, f(50));
}
v('white', KARTE);

// --- Kräftige Hintergründe festnageln ----------------------------------------
const esc = s => s.replace(/:/g, '\\:');
const varianten = ['', 'hover:', 'focus:', 'active:', 'group-hover:', 'peer-checked:'];
const sel = (klasse, variante) => {
    const pseudo = { 'hover:': ':hover', 'focus:': ':focus', 'active:': ':active' }[variante] ?? '';
    if (variante === 'group-hover:') return `.dark .group:hover .${esc(variante + klasse)}`;
    if (variante === 'peer-checked:') return `.dark .peer:checked ~ .${esc(variante + klasse)}`;
    return `.dark .${esc(variante + klasse)}${pseudo}`;
};
const regeln = [];
const nagel = (klassen, decl) => regeln.push(`${klassen.join(',\n')} {\n    ${decl}\n}`);

for (const h of BUNT) {
    for (const s of [500, 600, 700, 800, 900, 950]) {
        const wert = farbe[`${h}-${s}`];
        nagel(varianten.map(x => sel(`bg-${h}-${s}`, x)), `background-color: ${wert};`);
        for (const g of ['from', 'via', 'to']) {
            regeln.push(`.dark .${g}-${h}-${s} {\n    --tw-gradient-${g}: ${wert};\n}`);
        }
    }
}
// Dunkle Knöpfe/Leisten in Grau: bleiben dunkel, eine Stufe heller als die Karte.
for (const h of NEUTRAL) {
    const f = s => farbe[`${h}-${s}`];
    const ziel = { 600: f(500), 700: f(600), 800: f(700), 900: f(800), 950: f(950) };
    for (const [s, wert] of Object.entries(ziel)) {
        nagel(varianten.map(x => sel(`bg-${h}-${s}`, x)), `background-color: ${wert};`);
    }
}
// Weiße Schrift steht fast immer auf kräftigem Grund – bleibt weiß, auch mit Deckkraft.
nagel(varianten.map(x => sel('text-white', x)), 'color: #fff;');
for (let a = 5; a < 100; a += 5) {
    nagel(varianten.map(x => sel(`text-white/${a}`, x)).map(s => s.replace('/', '\\/')), `color: rgb(255 255 255 / ${a}%);`);
}
// Inseln: Innerhalb eines festgenagelten dunklen Grunds (Banner, Codeblock, dunkle Leiste)
// gilt wieder die Original-Palette. Sonst würde text-indigo-100 auf dem Banner oder
// text-gray-200 im Codeblock mit umgelegt – dunkel auf dunkel. Außerhalb solcher Flächen
// bleibt text-gray-200 dagegen das, was es im hellen Schema ist: ein blasser Strich.
const inselKlassen = [
    ...BUNT.flatMap(h => [500, 600, 700, 800, 900, 950].flatMap(s => [`bg-${h}-${s}`, `from-${h}-${s}`])),
    ...NEUTRAL.flatMap(h => [600, 700, 800, 900, 950].map(s => `bg-${h}-${s}`)),
    'bg-black',
];
const original = Object.entries(farbe).map(([name, wert]) => `    --color-${name}: ${wert};`);
regeln.push(`.dark :is(${inselKlassen.map(k => '.' + k).join(', ')}) {\n${original.join('\n')}\n    --color-white: #fff;\n}`);

const css = `/*
 * Dunkles Farbschema – ERZEUGT von tools/dunkel-css.mjs, nicht von Hand ändern.
 * Greift, sobald <html> die Klasse "dark" trägt (Profil → Darstellung).
 */

.dark {
    color-scheme: dark;
${zeilen.join('\n')}
}

${regeln.join('\n\n')}
`;
writeFileSync(new URL('../resources/css/dunkel.css', import.meta.url), css);
console.log(`resources/css/dunkel.css: ${zeilen.length} Variablen, ${regeln.length} Regeln`);
