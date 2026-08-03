import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/js/pages/user.js',
                'resources/js/pages/member.js',
                'resources/js/pages/guarantor.js',
                'resources/js/pages/member-share.js',
                'resources/js/pages/cash-movement.js',
                'resources/js/pages/loan-simulation.js',
                'resources/js/pages/loan.js',
                'resources/js/pages/loan-payment.js',
                'resources/js/pages/loan-refinancing.js',
                'resources/js/pages/late-fee.js',
                'resources/js/pages/solidarity-movement.js',
                'resources/js/pages/administrative-fund.js',
                'resources/js/pages/activity.js',
                'resources/js/pages/profit-distribution.js',
                'resources/js/pages/member-account-closure.js',
                'resources/js/pages/receipt.js',
                'resources/js/pages/reports.js',
            ],
            refresh: true,
        }),
    ],
});
