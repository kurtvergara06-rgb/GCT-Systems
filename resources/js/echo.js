import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME
    || (window.location.protocol === 'https:' ? 'https' : 'http');
const reverbHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const configuredReverbPort = Number(import.meta.env.VITE_REVERB_PORT);
const reverbPort = Number.isFinite(configuredReverbPort) && configuredReverbPort > 0
    ? configuredReverbPort
    : (reverbScheme === 'https' ? 443 : 8080);
const reverbUsesTls = reverbScheme === 'https';

window.GCTRealtimeConfig = Object.freeze({
    host: reverbHost,
    port: reverbPort,
    scheme: reverbScheme,
    channel: 'system-updates',
    event: 'SystemDataUpdated',
});

window.Echo = window.Echo || new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbUsesTls,
    enabledTransports: ['ws', 'wss'],
});

window.realtimePageRouteMap = {
    'Warehouse:PurchaseRequest': ['/warehouse/dashboard','/inventory','/part-requests','/warehouse/stock-movements','/purchase-requests','/job-orders','/maintenance-requests','/maintenance-dashboard','/purchase-orders','/admin/dashboard'],
    'Warehouse:Inventory': ['/warehouse/dashboard','/inventory','/part-requests','/warehouse/stock-movements','/warehouse/incoming-deliveries','/maintenance-requests','/job-orders','/maintenance-dashboard','/admin/dashboard'],
    'Warehouse:PurchaseOrder': ['/warehouse/dashboard','/warehouse/incoming-deliveries','/inventory','/warehouse/stock-movements','/part-requests','/purchase-orders','/maintenance-requests','/job-orders','/maintenance-dashboard','/admin/dashboard'],
    'Warehouse:RolePermission': ['/warehouse/dashboard','/inventory','/part-requests','/warehouse/incoming-deliveries','/warehouse/stock-movements'],
    'Maintenance:PurchaseRequest': ['/purchase-requests','/job-orders','/part-requests','/maintenance-requests','/maintenance-dashboard','/admin/dashboard'],
    'Maintenance:JobOrder': ['/job-orders','/purchase-requests','/part-requests','/maintenance-requests','/maintenance-dashboard','/admin/dashboard'],
    'Maintenance:RolePermission': ['/maintenance-dashboard','/maintenance-referrals','/pms-scheduling','/fuel-reports','/job-orders','/purchase-requests','/mechanic-list'],
    'Maintenance:FuelReport': ['/fuel-reports','/maintenance-dashboard','/admin/dashboard'],
    'Maintenance:PmsSchedule': ['/pms-scheduling','/job-orders','/maintenance-dashboard','/admin/dashboard'],
    'Maintenance:MaintenanceReferral': ['/maintenance-referrals','/job-orders','/maintenance-dashboard','/admin/dashboard'],
    'Purchase:PurchaseOrder': ['/purchase-orders','/warehouse/dashboard','/warehouse/incoming-deliveries','/warehouse/stock-movements','/maintenance-requests','/part-requests','/job-orders','/inventory','/admin/dashboard'],
    'Purchase:MaintenanceRequest': ['/maintenance-requests','/purchase-orders','/part-requests','/purchase-requests','/job-orders','/inventory','/admin/dashboard'],
    'Admin:BatchUpload': ['/batch-file-processing','/dashboard-operation','/admin/dashboard'],
    'Operation:Attendance': ['/mechanic-attendance','/driver-attendance','/dashboard-operation','/admin/dashboard','/mechanic-list'],
    'Operation:Bus': ['/bus-master-list','/dashboard-operation','/operation/auto-scheduling','/job-orders','/pms-scheduling','/maintenance-dashboard','/admin/dashboard'],
    'Operation:Incident': ['/operation/incidents','/dashboard-operation','/admin/dashboard'],
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

const realtimeNotificationState = {
    messages: [],
    timer: null,
};

const shouldSuppressRealtimeNotification = () => {
    const state = window.GCTRealtimeLocalMutation;
    if (!state) return false;

    return Number(state.pending || 0) > 0
        || Date.now() < Number(state.quietUntil || 0);
};

const flushRealtimeNotifications = () => {
    realtimeNotificationState.timer = null;

    if (realtimeNotificationState.messages.length === 0) return;

    const messages = [...new Set(realtimeNotificationState.messages.map((message) => String(message || '').trim()).filter(Boolean))];
    realtimeNotificationState.messages = [];

    if (messages.length === 0) return;

    const visibleMessages = messages.slice(0, 3);
    let combinedMessage = visibleMessages.join(' ');

    if (messages.length > visibleMessages.length) {
        combinedMessage += ` ${messages.length - visibleMessages.length} more update${messages.length - visibleMessages.length === 1 ? '' : 's'} completed.`;
    }

    window.showSystemNotification(combinedMessage);
};

const queueRealtimeNotification = (message) => {
    if (shouldSuppressRealtimeNotification()) return;

    const cleanMessage = String(message || 'System data was updated.').trim();
    if (!cleanMessage) return;

    if (!realtimeNotificationState.messages.includes(cleanMessage)) {
        realtimeNotificationState.messages.push(cleanMessage);
    }

    if (realtimeNotificationState.timer) {
        window.clearTimeout(realtimeNotificationState.timer);
    }

    // Related backend broadcasts often arrive within the same workflow action.
    // Keep data refresh immediate, but group their user-facing feedback.
    realtimeNotificationState.timer = window.setTimeout(flushRealtimeNotifications, 650);
};

const normalizePath = (path) => {
    const normalized = `/${String(path || '').split('?')[0].replace(/^\/+/, '').replace(/\/+$/, '')}`;
    return normalized === '/' ? '/' : normalized;
};

let realtimeRegionRefreshController = null;

const refreshAjaxRegions = async () => {
    const names = [...new Set(Array.from(document.querySelectorAll('[data-ajax-region]')).map((element) => element.dataset.ajaxRegion).filter(Boolean))];
    if (names.length === 0) return false;

    realtimeRegionRefreshController?.abort();
    const controller = new AbortController();
    const requestUrl = window.location.href;
    realtimeRegionRefreshController = controller;

    names.forEach((name) => window.GCTRegions?.setLoading?.(name, true));

    try {
        const response = await fetch(requestUrl, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
            signal: controller.signal,
        });
        if (!response.ok) throw new Error(`Realtime region refresh failed with status ${response.status}.`);

        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');

        if (
            controller.signal.aborted
            || realtimeRegionRefreshController !== controller
            || window.location.href !== requestUrl
        ) {
            return false;
        }

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
            const detail = { regions: names };
            window.dispatchEvent(new CustomEvent('system-regions-refreshed', { detail }));
            window.dispatchEvent(new CustomEvent('ajax:content-updated', { detail }));
        }

        return replacedCount > 0;
    } catch (error) {
        if (error?.name === 'AbortError') return false;
        console.warn('Realtime AJAX region refresh failed:', error);
        return false;
    } finally {
        if (realtimeRegionRefreshController === controller) {
            realtimeRegionRefreshController = null;
            names.forEach((name) => window.GCTRegions?.setLoading?.(name, false));
        }
    }
};

window.addEventListener('gct:navigation-before', () => {
    realtimeRegionRefreshController?.abort();
    realtimeRegionRefreshController = null;

    if (window.systemUpdatesRegionRefreshTimer) {
        window.clearTimeout(window.systemUpdatesRegionRefreshTimer);
        window.systemUpdatesRegionRefreshTimer = null;
    }
});

window.listenForSystemUpdates = function () {
    if (!window.Echo || !window.Echo.channel) {
        console.warn('Realtime listener was not started because Echo is unavailable.');
        return;
    }
    if (window.systemUpdatesListenerStarted) return;

    window.systemUpdatesListenerStarted = true;

    if (!window.systemUpdatesConnectionBindingsStarted) {
        window.systemUpdatesConnectionBindingsStarted = true;
        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('Realtime connected to Reverb.', window.GCTRealtimeConfig);
        });
        window.Echo.connector.pusher.connection.bind('error', (error) => console.error('Realtime Reverb connection error:', error));
    }

    window.Echo.channel('system-updates').listen('.SystemDataUpdated', (payload) => {
        window.dispatchEvent(new CustomEvent('system-data-updated', { detail: payload }));

        try {
            if (payload?.entity !== 'RolePermission') {
                queueRealtimeNotification(payload?.message || 'System data was updated.');
            }

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
