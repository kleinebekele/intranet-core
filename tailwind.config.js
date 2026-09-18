import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './vendor/do1emu/module-*/resources/views/**/*.blade.php',
        // Module dürfen Tailwind-Klassen auch aus PHP-Code liefern
        // (z. B. Farbcodierung der Ekkon-Laufzeiten in EkkonTask).
        './vendor/do1emu/module-*/src/**/*.php',
        // Ekkon liegt seit 2026-09 im Core: die Farbcodierung der Laufzeiten
        // steht als Klassennamen in app/Ekkon/Tasks/EkkonTask.php.
        './app/Ekkon/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
