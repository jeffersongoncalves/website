import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/filament/admin/theme.css',
                'resources/css/site.css',
                'resources/css/fonts/dm-sans.css',
                'resources/css/fonts/fraunces.css',
                'resources/css/fonts/jetbrains-mono.css',
                'resources/js/app.js',
                'resources/js/site.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
