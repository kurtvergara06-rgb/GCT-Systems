import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});

window.realtimePageRouteMap = {
    'Warehouse:PurchaseRequest': [
        '/purchase-requests',
        '/job-orders',
        '/maintenance-requests',
        '/part-requests',
        '/purchase-orders',
        '/admin/dashboard',
    ],

    'Warehouse:Inventory': [
        '/inventory',
        '/part-requests',
        '/maintenance-requests',
        '/job-orders',
        '/admin/dashboard',
    ],

    'Maintenance:PurchaseRequest': [
        '/purchase-requests',
        '/job-orders',
        '/part-requests',
        '/maintenance-requests',
        '/admin/dashboard',
    ],

    'Maintenance:JobOrder': [
        '/job-orders',
        '/purchase-requests',
        '/part-requests',
        '/maintenance-requests',
        '/admin/dashboard',
    ],

    'Purchase:PurchaseOrder': [
        '/purchase-orders',
        '/maintenance-requests',
        '/part-requests',
        '/job-orders',
        '/inventory',
        '/admin/dashboard',
    ],

    'Purchase:MaintenanceRequest': [
        '/maintenance-requests',
        '/purchase-orders',
        '/part-requests',
        '/purchase-requests',
        '/job-orders',
        '/inventory',
        '/admin/dashboard',
    ],

    'Admin:BatchUpload': [
        '/batch-file-processing',
        '/dashboard-operation',
        '/admin/dashboard',
    ],

    'Operation:Attendance': [
        '/mechanic-attendance',
        '/driver-attendance',
        '/dashboard-operation',
        '/admin/dashboard',
        '/mechanic-list',
    ],

    'Operation:Bus': [
        '/bus-master-list',
        '/dashboard-operation',
        '/job-orders',
        '/pms-scheduling',
        '/admin/dashboard',
    ],
};

window.showSystemNotification = function (message) {
    try {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(message, 'warning', 'System Updated', {
                timeout: 8000,
                keepRealtime: true,
            });
        }
    } catch (error) {
        console.warn('Realtime notification failed:', error);
    }
};

window.listenForSystemUpdates = function () {
    if (!window.Echo || !window.Echo.channel) {
        console.warn(
            'Realtime listener was not started because Echo is unavailable.'
        );

        return;
    }

    console.log('Realtime listener starting...');
    console.log('Reverb host:', import.meta.env.VITE_REVERB_HOST);
    console.log('Reverb port:', import.meta.env.VITE_REVERB_PORT);

    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('Realtime connected to Reverb.');
    });

    window.Echo.connector.pusher.connection.bind('error', (error) => {
        console.error('Realtime Reverb connection error:', error);
    });

    window.Echo.channel('system-updates')
        .listen('.SystemDataUpdated', (payload) => {
            console.log('SystemDataUpdated received:', payload);

            window.dispatchEvent(
                new CustomEvent('system-data-updated', {
                    detail: payload,
                })
            );

            try {
                const message =
                    payload?.message || 'System data was updated.';

                window.showSystemNotification(message);

                const currentPath = window.location.pathname;
                const routeKey = `${payload.module}:${payload.entity}`;

                const watchRoutes =
                    window.realtimePageRouteMap[routeKey] || [];

                console.log('Current path:', currentPath);
                console.log('Route key:', routeKey);
                console.log('Watch routes:', watchRoutes);

                if (watchRoutes.includes(currentPath)) {
                    console.log(
                        'Reloading page after showing realtime notification.'
                    );

                    setTimeout(() => {
                        window.location.reload();
                    }, 8000);
                }
            } catch (error) {
                console.warn('System updates listener error:', error);
            }
        });
};

window.listenForSystemUpdates();