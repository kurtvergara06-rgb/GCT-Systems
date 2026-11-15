import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ======================================================
                // MAIN APPLICATION
                // ======================================================

                'resources/js/app.js',

                // ======================================================
                // SHARED LAYOUT ASSETS
                // ======================================================

                'resources/css/Main-styles/theme.css',
                'resources/css/Main-styles/main.css',
                'resources/css/Main-styles/sidebar.css',
                'resources/css/Main-styles/admin-records.css',
                'resources/css/Main-styles/form-components.css',
                'resources/css/Main-styles/system-toast.css',
                'resources/css/Account/account.css',

                'resources/js/Main-js/sidebar.js',
                'resources/js/Main-js/confirmation-modal.js',
                'resources/js/Main-js/system-toast.js',

                // ======================================================
                // LOGIN
                // ======================================================

                'resources/css/Login/login.css',
                'resources/js/Login/login.js',

                // ======================================================
                // ADMIN — DASHBOARD
                // ======================================================

                'resources/css/Admin/admin-dashboard.css',
                'resources/js/Admin/admin-dashboard.js',

                // ======================================================
                // ADMIN — USER MANAGEMENT
                // ======================================================

                'resources/css/Admin/User_Management/users.css',
                'resources/css/Admin/User_Management/permissions.css',

                'resources/js/Admin/User_Management/users.js',

                // ======================================================
                // ADMIN — DATA MANAGEMENT
                // ======================================================

                'resources/css/Admin/Data_Management/batch-file-processing.css',
                'resources/css/Admin/Data_Management/generic-batch-review.css',
                'resources/css/Admin/Data_Management/data-history.css',
                'resources/css/Admin/Data_Management/uploading-data.css',

                'resources/js/Admin/Data_Management/batch-file-processing.js',

                // ======================================================
                // ADMIN — SYSTEM MONITORING
                // ======================================================

                'resources/css/Admin/System_Monitoring/activity-logs.css',
                'resources/css/Admin/System_Monitoring/notifications.css',
                'resources/js/Admin/System_Monitoring/activity-logs.js',

                // ======================================================
                // ADMIN — SETTINGS
                // ======================================================

                'resources/css/Admin/Settings/general-settings.css',
                'resources/css/Admin/Settings/notification-settings.css',
                'resources/css/Admin/Settings/security-settings.css',

                // ======================================================
                // ADMIN — ANALYTICS
                // ======================================================

                'resources/css/Admin/Analytics/overview/overview.css',
                'resources/css/Admin/Analytics/overview/analytics-stage-hub.css',
                'resources/css/Admin/Analytics/overview/fleet-trip.css',
                'resources/css/Admin/Analytics/overview/fleet-trip-redesign.css',
                'resources/css/Admin/Analytics/overview/fleet-trip-rankings.css',
                'resources/css/Admin/Analytics/descriptive/all.css',
                'resources/css/Admin/Analytics/descriptive/fleet-trip.css',
                'resources/css/Admin/Analytics/descriptive/fuel.css',
                'resources/css/Admin/Analytics/descriptive/bus-health.css',
                'resources/css/Admin/Analytics/descriptive/inventory.css',
                'resources/css/Admin/Analytics/diagnostic/all.css',
                'resources/css/Admin/Analytics/diagnostic/fleet-trip.css',
                'resources/css/Admin/Analytics/diagnostic/fuel.css',
                'resources/css/Admin/Analytics/diagnostic/bus-health.css',
                'resources/css/Admin/Analytics/diagnostic/inventory.css',
                'resources/css/Admin/Analytics/predictive/all.css',
                'resources/css/Admin/Analytics/predictive/fleet-trip.css',
                'resources/css/Admin/Analytics/predictive/fuel.css',
                'resources/css/Admin/Analytics/predictive/bus-health.css',
                'resources/css/Admin/Analytics/predictive/inventory.css',

                'resources/js/Admin/Analytics/predictive/charts.js',

                // ======================================================
                // MAINTENANCE
                // ======================================================

                'resources/css/Maintenance/maintenance-dashboard.css',
                'resources/css/Maintenance/fuel-reports.css',
                'resources/css/Maintenance/job-order.css',
                'resources/css/Maintenance/mechanic-list.css',
                'resources/css/Maintenance/pms-scheduling.css',
                'resources/css/Maintenance/purchase-requests.css',

                'resources/js/Maintenance/fuel-reports.js',
                'resources/js/Maintenance/job-order.js',
                'resources/js/Maintenance/pms-scheduling.js',
                'resources/js/Maintenance/purchase-requests.js',

                // ======================================================
                // OPERATION — DASHBOARD
                // ======================================================

                'resources/css/Operation/dashboard-operation.css',

                // ======================================================
                // OPERATION — ATTENDANCE
                // ======================================================

                'resources/css/Operation/Attendance/driver-attendance.css',
                'resources/css/Operation/Attendance/available-mechanics.css',

                'resources/js/Operation/Attendance/driver-attendance.js',
                'resources/js/Operation/Attendance/mechanic-attendance.js',

                // ======================================================
                // OPERATION — ROUTES AND STOPS
                // ======================================================

                'resources/css/Operation/Routes/routes-stops.css',
                'resources/js/Operation/Routes/routes-stops.js',

                // ======================================================
                // OPERATION — SHUTTLE BUS MANAGEMENT
                // ======================================================

                'resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css',
                'resources/js/Operation/Shuttle_Bus_Management/bus-master-list.js',

               // ======================================================
                // OPERATION — SCHEDULING AND DISPATCH
                // ======================================================

                'resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css',
                'resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css',
                'resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css',

                'resources/js/Operation/Scheduling_And_Dispatch/auto-scheduling.js',
                'resources/js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js',
                'resources/js/Operation/Scheduling_And_Dispatch/trip-schedule.js',

                // ======================================================
                // OPERATION — TRIP RECORDS
                // ======================================================

                'resources/css/Operation/Trip_Records/trip-records.css',

                // ======================================================
                // PURCHASE
                // ======================================================

                'resources/css/Purchase/dashboard-purchase.css',
                'resources/css/Purchase/purchase-orders.css',
                'resources/css/Purchase/scheduled-purchase.css',

                'resources/css/Purchase/Requested_Purchase/maintenance-requests.css',
                'resources/css/Purchase/Requested_Purchase/inventory-restock.css',

                'resources/js/Purchase/purchase-orders.js',
                'resources/js/Purchase/scheduled-purchase.js',
                'resources/js/Purchase/Requested_Purchase/maintenance-requests.js',
                'resources/js/Purchase/Requested_Purchase/inventory-restock.js',

                // ======================================================
                // WAREHOUSE
                // ======================================================

                'resources/css/Warehouse/dashboard-warehouse.css',
                'resources/css/Warehouse/inventory.css',
                'resources/css/Warehouse/part-requests.css',
                'resources/css/Warehouse/stock-movements.css',
                'resources/css/Warehouse/incoming-deliveries.css',

                'resources/js/Warehouse/inventory.js',
                'resources/js/Warehouse/part-requests.js',
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
