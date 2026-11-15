// Loaded only by the local layout's explicit ?_gct_profile=1 opt-in.
// Aggregate metrics only: no query strings, headers, credentials or record data.
const round = value => Number.isFinite(value) ? Math.round(value * 10) / 10 : null;
const observed = { lcpMs: null, cls: 0, longTasks: 0, longTaskMs: 0 };
let shiftWindow = { first: 0, last: 0, value: 0 };
for (const type of ['largest-contentful-paint', 'layout-shift', 'longtask']) {
    if (!PerformanceObserver.supportedEntryTypes?.includes(type)) continue;
    const observer = new PerformanceObserver(list => {
        for (const entry of list.getEntries()) {
            if (type === 'largest-contentful-paint') observed.lcpMs = entry.startTime;
            if (type === 'longtask') { observed.longTasks++; observed.longTaskMs += entry.duration; }
            if (type === 'layout-shift' && !entry.hadRecentInput) {
                if (!shiftWindow.first || entry.startTime - shiftWindow.last > 1000 || entry.startTime - shiftWindow.first > 5000) {
                    shiftWindow = { first: entry.startTime, last: entry.startTime, value: 0 };
                }
                shiftWindow.last = entry.startTime;
                shiftWindow.value += entry.value;
                observed.cls = Math.max(observed.cls, shiftWindow.value);
            }
        }
    });
    observer.observe({ type, buffered: true });
}

function capture() {
    const navigation = performance.getEntriesByType('navigation')[0];
    const resources = performance.getEntriesByType('resource');
    const metrics = {
        path: location.pathname,
        sampledAtMs: round(performance.now()),
        ttfbMs: navigation ? round(navigation.responseStart - navigation.requestStart) : null,
        documentDownloadMs: navigation ? round(navigation.responseEnd - navigation.responseStart) : null,
        domInteractiveMs: round(navigation?.domInteractive),
        domContentLoadedMs: round(navigation?.domContentLoadedEventEnd),
        loadMs: round(navigation?.loadEventEnd),
        lcpMs: round(observed.lcpMs),
        cls: Math.round(observed.cls * 10000) / 10000,
        longTaskCount: observed.longTasks,
        longTaskMs: round(observed.longTaskMs),
        requestCount: resources.length + 1,
        observedTransferBytes: resources.reduce((sum, item) => sum + item.transferSize, navigation?.transferSize || 0),
        zeroTransferEntries: resources.filter(item => item.transferSize === 0).length,
        domNodes: document.getElementsByTagName('*').length,
        scriptNodes: document.scripts.length,
        stylesheetNodes: document.querySelectorAll('link[rel="stylesheet"]').length,
        heapBytes: performance.memory?.usedJSHeapSize ?? null,
        initializers: (window.GCTInitializationTimings || []).slice(-30),
        resources: resources.map(item => ({ path: new URL(item.name, location.href).pathname, type: item.initiatorType, durationMs: round(item.duration), transferBytes: item.transferSize })),
    };
    document.documentElement.dataset.gctPerformance = JSON.stringify(metrics);
    document.documentElement.dataset.gctPerformancePhase = 'sampled';
}
let captureTimer = null;
function scheduleCapture() {
    document.documentElement.dataset.gctPerformancePhase = 'pending';
    window.clearTimeout(captureTimer);
    captureTimer = window.setTimeout(capture, 1500);
}
if (document.readyState === 'complete') scheduleCapture();
else window.addEventListener('load', scheduleCapture, { once: true });
window.addEventListener('gct:navigation-ready', scheduleCapture);

document.addEventListener('system:table-rows-loaded', scheduleCapture);
