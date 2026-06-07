import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                heading: ['Poppins', 'sans-serif'],
                body: ['Inter', 'sans-serif'],
                number: ['Rajdhani', 'sans-serif'],
            },
            colors: {
                // ── v2 "Editorial / Prestige" palette (shared by public + admin) ──
                // primary = deep ink (dark surfaces, sidebar, primary buttons, headings)
                primary: {
                    DEFAULT: '#131C3D',   // ink-2 — rich dark navy ink
                    light: '#24315C',     // lifted ink for hover states
                    dark: '#0A1024',      // deepest ink for active states
                },
                // accent = saffron (CTA buttons, highlights)
                accent: {
                    DEFAULT: '#EE6A2C',
                    light: '#F2854E',
                    dark: '#C9501A',
                },
                // gold = prestige (medals, ranks, certificates)
                gold: {
                    DEFAULT: '#D6991F',
                    light: '#F2C84B',
                    dark: '#B45309',
                },
                royal: '#2C49A6',          // trust-blue accent
                success: '#168A66',        // emerald
                danger: '#DC2626',         // alerts / wrong answers
                bg: '#FBF6EC',             // warm paper — page background
                card: '#F3E9D6',           // deeper paper — panels
                'text-main': '#0A1024',    // ink text
                'text-muted': '#5B6373',   // muted slate on paper
            },
        },
    },

    plugins: [forms],
};
