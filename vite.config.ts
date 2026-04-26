import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        // Bind to 0.0.0.0 so the dev server is reachable from outside
        // the container. usePolling is required for HMR to detect file
        // changes through Docker bind mounts on Windows/macOS — without
        // it, edits don't trigger reloads. ~3% CPU overhead, worth it.
        host: '0.0.0.0',
        watch: {
            usePolling: true,
        },
    },
});
