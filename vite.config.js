import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// No view uses @vite today (see resources/js/app.js); kept for future bundles.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
    ],
});
