import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Vite only builds the admin panel assets. The public website keeps the
// approved static CSS/JS in public/ (style.css, css/*.css, script.js).
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/admin.css', 'resources/js/admin.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
