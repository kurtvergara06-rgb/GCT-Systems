import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = window.Echo || new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});

window.realtimePageRouteMap = {
    'Warehouse:PurchaseRequest': ['/purchase-requests','/job-orders','/maintenance-requests','/part-requests','/purchase-orders','/inventory','/admin/dashboard'],
    'Warehouse:Inventory': ['/inventory','/part-requests','/maintenance-requests','/job-orders','/admin/dashboard'],
    'Maintenance:PurchaseRequest': ['/purchase-requests','/job-orders','/part-requests','/maintenance-requests','/admin/dashboard'],
    'Maintenance:JobOrder': ['/job-orders','/purchase-requests','/part-requests','/maintenance-requests','/admin/dashboard'],
    'Purchase:PurchaseOrder': ['/purchase-orders','/maintenance-requests','/part-requests','/job-orders','/inventory','/admin/dashboard'],
    'Purchase:MaintenanceRequest': ['/maintenance-requests','/purchase-orders','/part-requests','/purchase-requests','/job-orders','/inventory','/admin/dashboard'],
    'Admin:BatchUpload': ['/batch-file-processing','/dashboard-operation','/admin/dashboard'],
    'Operation:Attendance': ['/mechanic-attendance','/driver-attendance','/dashboard-operation','/admin/dashboard','/mechanic-list'],
    'Operation:Bus': ['/bus-master-list','/dashboard-operation','/job-orders','/pms-scheduling','/admin/dashboard'],
};

window.showSystemNotification = function (message) {
    try {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(message, 'warning', 'System Updated', { timeout: 8000, keepRealtime: true });
        }
    } catch (error) {
        console.warn('Realtime notification failed:', error);
    }
};

const normalizePath = (path) => {
    const normalized = `/${String(path || '').split('?')[0].replace(/^\/+/, '').replace(/\/+$/, '')}`;
    return normalized === '/' ? '/' : normalized;
};

const refreshAjaxRegions = async () => {
    const names = [...new Set(Array.from(document.querySelectorAll('[data-ajax-region]')).map((element) => element.dataset.ajaxRegion).filter(Boolean))];
    if (names.length === 0) return false;

    names.forEach((name) => window.GCTRegions?.setLoading?.(name, true));

    try {
        const response = await fetch(window.location.href, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        });
        if (!response.ok) throw new Error(`Realtime region refresh failed with status ${response.status}.`);

        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
        let replacedCount = 0;

        names.forEach((name) => {
            const selector = `[data-ajax-region="${CSS.escape(String(name))}"]`;
            const source = parsed.querySelector(selector);
            if (!source) return;

            if (window.GCTRegions?.replace) {
                if (window.GCTRegions.replace(name, source.outerHTML)) replacedCount += 1;
                return;
            }

            const current = document.querySelector(selector);
            if (current) {
                current.replaceWith(source.cloneNode(true));
                replacedCount += 1;
            }
        });

        if (replacedCount > 0) {
            window.dispatchEvent(new CustomEvent('system-regions-refreshed', { detail: { regions: names } }));
        }

        return replacedCount > 0;
    } catch (error) {
        console.warn('Realtime AJAX region refresh failed:', error);
        return false;
    } finally {
        names.forEach((name) => window.GCTRegions?.setLoading?.(name, false));
    }
};

window.listenForSystemUpdates = function () {
    if (!window.Echo || !window.Echo.channel) {
        console.warn('Realtime listener was not started because Echo is unavailable.');
        return;
    }
    if (window.systemUpdatesListenerStarted) return;

    window.systemUpdatesListenerStarted = true;

    if (!window.systemUpdatesConnectionBindingsStarted) {
        window.systemUpdatesConnectionBindingsStarted = true;
        window.Echo.connector.pusher.connection.bind('connected', () => console.log('Realtime connected to Reverb.'));
        window.Echo.connector.pusher.connection.bind('error', (error) => console.error('Realtime Reverb connection error:', error));
    }

    window.Echo.channel('system-updates').listen('.SystemDataUpdated', (payload) => {
        window.dispatchEvent(new CustomEvent('system-data-updated', { detail: payload }));

        try {
            window.showSystemNotification(payload?.message || 'System data was updated.');
            const currentPath = normalizePath(window.location.pathname);
            const routeKey = `${payload.module}:${payload.entity}`;
            const watched = (window.realtimePageRouteMap[routeKey] || []).map(normalizePath);
            if (!watched.includes(currentPath)) return;

            if (window.systemUpdatesRegionRefreshTimer) clearTimeout(window.systemUpdatesRegionRefreshTimer);
            window.systemUpdatesRegionRefreshTimer = window.setTimeout(async () => {
                window.systemUpdatesRegionRefreshTimer = null;
                await refreshAjaxRegions();
            }, 350);
        } catch (error) {
            console.warn('System updates listener error:', error);
        }
    });
};

window.refreshGCTAjaxRegions = refreshAjaxRegions;
window.listenForSystemUpdates();
