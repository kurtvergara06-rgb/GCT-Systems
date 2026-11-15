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
        const oldNotification = document.querySelector(
            '.system-data-updated-notification'
        );

        if (oldNotification) {
            oldNotification.remove();
        }

        const notification = document.createElement('div');

        notification.className = 'system-data-updated-notification';

        notification.innerHTML = `
            <div style="
                width: 34px;
                height: 34px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(255, 193, 7, 0.16);
                color: #facc15;
                flex-shrink: 0;
            ">
                <i class="fa-solid fa-bell"></i>
            </div>

            <div style="min-width: 0; flex: 1;">
                <strong style="
                    display: block;
                    color: #ffffff;
                    font-size: 14px;
                    margin-bottom: 3px;
                ">
                    System Updated
                </strong>

                <span style="
                    display: block;
                    color: #cbd5e1;
                    font-size: 13px;
                    line-height: 1.4;
                ">
                    ${message}
                </span>
            </div>

            <button
                type="button"
                aria-label="Close notification"
                style="
                    border: none;
                    background: transparent;
                    color: #94a3b8;
                    cursor: pointer;
                    font-size: 16px;
                    padding: 2px 4px;
                    margin-left: 4px;
                "
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '9999999',
            width: 'min(390px, calc(100vw - 40px))',
            display: 'flex',
            alignItems: 'flex-start',
            gap: '12px',
            padding: '14px 15px',
            borderRadius: '14px',
            background: 'linear-gradient(135deg, #081a32, #102b4d)',
            border: '1px solid rgba(250, 204, 21, 0.45)',
            boxShadow: '0 18px 45px rgba(0, 0, 0, 0.45)',
            fontFamily: 'Arial, sans-serif',
            opacity: '0',
            transform: 'translateY(-12px)',
            transition: 'opacity 0.25s ease, transform 0.25s ease',
        });

        const closeButton = notification.querySelector('button');

        const removeNotification = function () {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-12px)';

            setTimeout(() => {
                notification.remove();
            }, 250);
        };

        closeButton?.addEventListener('click', removeNotification);

        document.body.appendChild(notification);

        requestAnimationFrame(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateY(0)';
        });

        setTimeout(removeNotification, 8000);
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