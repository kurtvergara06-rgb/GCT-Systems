import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo =
    window.Echo ||
    new Echo({
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
        '/inventory',
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

    if (window.systemUpdatesListenerStarted) {
        console.log('Realtime listener already started.');

        return;
    }

    window.systemUpdatesListenerStarted = true;

    console.log('Realtime listener starting...');
    console.log('Reverb host:', import.meta.env.VITE_REVERB_HOST);
    console.log('Reverb port:', import.meta.env.VITE_REVERB_PORT);

    if (!window.systemUpdatesConnectionBindingsStarted) {
        window.systemUpdatesConnectionBindingsStarted = true;

        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('Realtime connected to Reverb.');
        });

        window.Echo.connector.pusher.connection.bind('error', (error) => {
            console.error('Realtime Reverb connection error:', error);
        });
    }

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

                const normalizePath = (path) => {
                    const normalized = `/${String(path || '')
                        .split('?')[0]
                        .replace(/^\/+/, '')
                        .replace(/\/+$/, '')}`;

                    return normalized === '/' ? '/' : normalized;
                };

                const currentPath = normalizePath(window.location.pathname);
                const routeKey = `${payload.module}:${payload.entity}`;

                const watchRoutes =
                    window.realtimePageRouteMap[routeKey] || [];
                const normalizedWatchRoutes = watchRoutes.map(normalizePath);

                console.log('Current path:', currentPath);
                console.log('Route key:', routeKey);
                console.log('Watch routes:', normalizedWatchRoutes);

                if (normalizedWatchRoutes.includes(currentPath)) {
                    const now = Date.now();
                    const lastReloadAt = Number(
                        sessionStorage.getItem('systemUpdatesLastReloadAt') || 0
                    );

                    if (window.systemUpdatesReloadTimer) {
                        console.log('Realtime reload is already scheduled.');

                        return;
                    }

                    if (now - lastReloadAt < 3000) {
                        console.log(
                            'Realtime reload skipped to prevent a reload loop.'
                        );

                        return;
                    }

                    console.log(
                        'Reloading page after showing realtime notification.'
                    );

                    window.systemUpdatesReloadTimer = setTimeout(() => {
                        sessionStorage.setItem(
                            'systemUpdatesLastReloadAt',
                            String(Date.now())
                        );

                        window.location.reload();
                    }, 650);
                }
            } catch (error) {
                console.warn('System updates listener error:', error);
            }
        });
};

window.listenForSystemUpdates();
