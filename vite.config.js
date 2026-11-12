import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/auth.css', 'resources/js/app.js', 'resources/js/auth.js', 'resources/js/home.js', 'resources/js/payout-rates.js', 'resources/js/blog.js', 'resources/js/legal.js', 'resources/css/member.css', 'resources/js/member.js', 'resources/js/member-settings.js', 'resources/js/member-statistic.js', 'resources/js/password-meter.js', 'resources/css/admin.css', 'resources/css/visit.css', 'resources/js/visit.js'],
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
