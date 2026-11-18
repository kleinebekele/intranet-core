// Erzeugt resources/css/farben-alt.css – Ersatzfarben für ältere Browser.
//
//   node tools/farben-alt-css.mjs
//
// Tailwind 4 schreibt jede Farbe als oklch(...) (dazu color-mix in oklab im dunklen
// Schema). Browser ohne oklch-Unterstützung – etwa Samsung Internet auf älteren
// Tablets oder alte Android-WebViews – verwerfen diese Werte: Knöpfe verlieren ihre
// Hintergrundfarbe, weiße Schrift steht auf Weiß, der Knopf ist „weg".
//
// Das Skript rechnet alle Farbwerte aus Tailwinds theme.css und aus dunkel.css in
// sRGB-Hex um und legt sie in einen @supports-Block, der nur greift, wenn der
// Browser oklch nicht kennt. Moderne Browser sehen davon nichts. Nach einem
// Tailwind-Update oder einer neuen dunkel.css erneut ausführen.

import { readFileSync, writeFileSync } from 'node:fs';

const theme = readFileSync(new URL('../node_modules/tailwindcss/theme.css', import.meta.url), 'utf8');
const dunkel = readFileSync(new URL('../resources/css/dunkel.css', import.meta.url), 'utf8');

// ---------------------------------------------------------------- Farbrechnung

const zahl = (s, prozentSkala = 1) => (s.trim().endsWith('%') ? parseFloat(s) / 100 * prozentSkala : parseFloat(s));

/** oklch(L C H [/ A]) → [L, a, b, alpha] in Oklab. */
function oklchZuOklab(innen) {
    const [farbe, alpha] = innen.split('/');
    const [l, c, h] = farbe.trim().split(/\s+/);
    const L = zahl(l), C = zahl(c, 0.4), H = (parseFloat(h) || 0) * Math.PI / 180;
    return [L, C * Math.cos(H), C * Math.sin(H), alpha === undefined ? 1 : zahl(alpha)];
}

function hexZuOklab(hex) {
    let h = hex.replace('#', '');
    if (h.length === 3) h = h.split('').map(x => x + x).join('');
    const lin = v => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    const [r, g, b] = [0, 2, 4].map(i => lin(parseInt(h.slice(i, i + 2), 16)));
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    return [0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
        1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s,
        0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s, 1];
}

function oklabZuHex([L, a, b, alpha]) {
    const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;
    const lin = [4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s];
    const kanal = v => {
        v = Math.min(1, Math.max(0, v));
        v = v <= 0.0031308 ? 12.92 * v : 1.055 * v ** (1 / 2.4) - 0.055;
        return Math.round(v * 255).toString(16).padStart(2, '0');
    };
    const a8 = alpha < 1 ? Math.round(alpha * 255).toString(16).padStart(2, '0') : '';
    return '#' + lin.map(kanal).join('') + a8;
}

/** Einen Farbausdruck (oklch, #hex, color-mix in oklab) nach Oklab. */
function parse(ausdruck) {
    const e = ausdruck.trim();
    if (e.startsWith('#')) return hexZuOklab(e);
    if (e.startsWith('oklch(')) return oklchZuOklab(e.slice(6, -1));
    const mix = e.match(/^color-mix\(\s*in oklab\s*,([\s\S]*)\)$/);
    if (mix) {
        const teile = teilen(mix[1]);
        const [f1, p1] = farbeUndAnteil(teile[0]);
        const [f2, p2] = farbeUndAnteil(teile[1]);
        const w1 = p1 ?? (p2 !== null ? 1 - p2 : 0.5);
        const a = parse(f1), b = parse(f2);
        return a.map((x, i) => x * w1 + b[i] * (1 - w1));
    }
    return null;
}

/** Kommaliste auf oberster Klammerebene trennen. */
function teilen(s) {
    const teile = [];
    let tiefe = 0, start = 0;
    for (let i = 0; i < s.length; i++) {
        if (s[i] === '(') tiefe++;
        else if (s[i] === ')') tiefe--;
        else if (s[i] === ',' && tiefe === 0) { teile.push(s.slice(start, i)); start = i + 1; }
    }
    teile.push(s.slice(start));
    return teile.map(t => t.trim());
}

function farbeUndAnteil(teil) {
    const m = teil.match(/^([\s\S]*?)\s+(\d+(?:\.\d+)?)%$/);
    return m ? [m[1], parseFloat(m[2]) / 100] : [teil, null];
}

/** Wert umrechnen, wenn er oklch/color-mix enthält – sonst null. */
function umrechnen(wert) {
    if (!/oklch\(|color-mix\(/.test(wert)) return null;
    const ok = parse(wert);
    return ok ? oklabZuHex(ok) : null;
}

// ---------------------------------------------------------------- Ausgabe

const zeilen = [];
const blockAus = (selektor, dekl) => {
    if (dekl.length) zeilen.push(`    ${selektor} {`, ...dekl.map(d => `        ${d}`), '    }');
};

// 1. Tailwinds Palette (:root)
const root = [];
for (const [, name, wert] of theme.matchAll(/(--color-[a-z0-9-]+):\s*([^;]+);/g)) {
    const hex = umrechnen(wert);
    if (hex) root.push(`${name}: ${hex};`);
}
blockAus(':root', root);

// 2. dunkles Schema: jede Regel, deren Werte umgerechnet werden müssen
const ohneKommentare = dunkel.replace(/\/\*[\s\S]*?\*\//g, '');
for (const [, selektor, rumpf] of ohneKommentare.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
    const dekl = [];
    for (const d of rumpf.split(/;(?![^(]*\))/)) {
        const i = d.indexOf(':');
        if (i < 0) continue;
        const eig = d.slice(0, i).trim(), wert = d.slice(i + 1).trim();
        const hex = umrechnen(wert);
        if (hex) dekl.push(`${eig}: ${hex};`);
    }
    blockAus(selektor.trim().replace(/\s+/g, ' '), dekl);
}

writeFileSync(new URL('../resources/css/farben-alt.css', import.meta.url), `/*
 * Ersatzfarben für Browser ohne oklch – ERZEUGT von tools/farben-alt-css.mjs, nicht von Hand ändern.
 * Greift nur, wenn der Browser oklch nicht kennt (z. B. Samsung Internet auf älteren Tablets).
 */

@supports not (color: oklch(0% 0 0)) {
${zeilen.join('\n')}
}
`);
console.log(`farben-alt.css: ${root.length} Palettenfarben, ${zeilen.length} Zeilen.`);
