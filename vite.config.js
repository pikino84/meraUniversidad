import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Tablero de reportes: entradas propias (sin código compartido con app.js) para
                // que "Descargar HTML" pueda incrustarlas tal cual.
                'resources/css/report.css',
                'resources/js/report-dashboard.js',
            ],
            refresh: true,
        }),
    ],
});
