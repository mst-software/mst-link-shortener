import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/auth.css', 'resources/js/app.js', 'resources/js/auth.js', 'resources/js/home.js', 'resources/js/payout-rates.js', 'resources/js/blog.js', 'resources/js/legal.js', 'resources/css/member.css', 'resources/js/member.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
