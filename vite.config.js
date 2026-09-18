import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Baloo 2', {
                    alias: 'sans',
                    weights: [400, 500, 600, 700, 800],
                    fallbacks: [
                        'ui-sans-serif',
                        'system-ui',
                        'sans-serif',
                        'Apple Color Emoji',
                        'Segoe UI Emoji',
                        'Segoe UI Symbol',
                        'Noto Color Emoji',
                    ],
                }),
                bunny('Nanum Gothic', {
                    alias: 'nanum',
                    weights: [400, 700, 800],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
