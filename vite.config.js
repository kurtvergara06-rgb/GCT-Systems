import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Main application assets
                'resources/css/app.css',
                'resources/js/app.js',

                // Shared layout CSS
                'resources/css/Main-styles/main.css',
                'resources/css/Main-styles/sidebar.css',
                'resources/css/Main-styles/system-toast.css',

                // Login
                'resources/css/Login/login.css',
                'resources/js/Login/login.js',

                // Maintenance
                'resources/css/Maintenance/job-order.css',
                'resources/css/Maintenance/mechanic-list.css',
                'resources/css/Maintenance/pms-scheduling.css',
                'resources/css/Maintenance/purchase-requests.css',
                'resources/css/Maintenance/fuel-reports.css',

                'resources/js/Maintenance/job-order.js',
                'resources/js/Maintenance/mechanic-list.js',
                'resources/js/Maintenance/pms-scheduling.js',
                'resources/js/Maintenance/purchase-requests.js',
                'resources/js/Maintenance/fuel-reports.js',

                // Purchase
                'resources/css/Purchase/purchase-orders.css',
                'resources/css/Purchase/Requested_Purchase/maintenance-requests.css',
                'resources/css/Purchase/Requested_Purchase/inventory-restock.css',
                'resources/css/Purchase/scheduled-purchase.css',

                'resources/js/Purchase/purchase-orders.js',
                'resources/js/Purchase/Requested_Purchase/maintenance-requests.js',
                'resources/js/Purchase/Requested_Purchase/inventory-restock.js',
                'resources/js/Purchase/scheduled-purchase.js',

                // Warehouse
                'resources/css/Warehouse/inventory.css',
                'resources/css/Warehouse/part-requests.css',

                'resources/js/Warehouse/inventory.js',
                'resources/js/Warehouse/part-requests.js',

                // Admin
                'resources/css/Admin/User_Management/users.css',
                'resources/css/Admin/admin-dashboard.css',
                'resources/css/Admin/User_Management/permissions.css',
                'resources/css/Admin/Data_Management/batch-file-processing.css',

                'resources/js/Admin/User_Management/users.js',
                'resources/js/Admin/admin-dashboard.js',
                'resources/js/Admin/analytics.js',
                'resources/js/Admin/batch-file-processing.js',

                // Operation
                'resources/css/Operation/dashboard-operation.css',
                'resources/css/Operation/attendance.css',
                'resources/css/Operation/available-mechanics.css',

                'resources/js/Operation/bus-master-list.js',
                'resources/js/Operation/mechanic-attendance.js',
            ],

            refresh: true,

            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
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