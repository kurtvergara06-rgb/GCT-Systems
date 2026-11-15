import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/Main-js/partial-navigation.js', import.meta.url), 'utf8');
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b; }); return { promise, resolve, reject }; };

// Model the browser's native EventTarget boundary. Node's EventTarget implements
// AbortSignal registration in JS, which would recursively re-enter the runtime's
// temporary prototype interceptor; browser-native registration does not do that.
class BrowserEventTarget {
    listeners = [];
    addEventListener(type, listener, options = {}) {
        const capture = typeof options === 'boolean' ? options : Boolean(options.capture);
        const signal = options?.signal;
        if (!listener || signal?.aborted || this.listeners.some(item => item.type === type && item.listener === listener && item.capture === capture)) return;
        const item = { type, listener, capture, signal, once: Boolean(options?.once) };
        item.abort = () => this.removeEventListener(type, listener, capture);
        signal?.addEventListener('abort', item.abort, { once: true });
        this.listeners.push(item);
    }
    removeEventListener(type, listener, options = {}) {
        const capture = typeof options === 'boolean' ? options : Boolean(options.capture);
        this.listeners = this.listeners.filter(item => {
            if (item.type !== type || item.listener !== listener || item.capture !== capture) return true;
            item.signal?.removeEventListener('abort', item.abort);
            return false;
        });
    }
    dispatchEvent(event) {
        for (const item of [...this.listeners]) {
            if (item.type !== event.type || !this.listeners.includes(item)) continue;
            if (item.once) this.removeEventListener(item.type, item.listener, item.capture);
            if (typeof item.listener === 'function') item.listener.call(this, event);
            else item.listener.handleEvent(event);
        }
        return true;
    }
}

function runtime() {
    const jobs = [];
    const timers = new Set();
    const observers = [];
    let hasRoot = true;
    let nextTimer = 0;
    class Element extends BrowserEventTarget {
        dataset = {};
        children = [];
        classList = { remove() {}, add() {}, contains() { return false; } };
        style = { removeProperty() {} };
        querySelectorAll() { return []; }
        closest() { return null; }
    }
    const root = new Element();
    const document = new BrowserEventTarget();
    Object.assign(document, { readyState: 'complete', body: new Element(), documentElement: new Element(), querySelector: selector => selector === '.fixture' && hasRoot ? root : null, querySelectorAll: () => [] });
    const window = new BrowserEventTarget();
    Object.assign(window, {
        location: new URL('http://gct.test/fixture'),
        setTimeout: () => { const id = ++nextTimer; timers.add(id); return id; },
        setInterval: () => { const id = ++nextTimer; timers.add(id); return id; },
        clearTimeout: id => timers.delete(id), clearInterval: id => timers.delete(id),
        MutationObserver: class { constructor() { this.disconnected = false; observers.push(this); } disconnect() { this.disconnected = true; } },
    });
    const context = vm.createContext({ window, document, EventTarget: BrowserEventTarget, AbortController, HTMLElement: Element, URL, console, queueMicrotask: job => jobs.push(job), requestAnimationFrame() {} });
    vm.runInContext(source, context);
    return { window, document, timers, observers, leave() { hasRoot = false; window.dispatchEvent(new Event('gct:navigation-ready')); }, run() { jobs.shift()(); } };
}

test('revisiting a page replaces handlers, observers and timers instead of accumulating them', () => {
    const env = runtime();
    let calls = 0;
    env.window.GCTPartialNavigation.registerInitializer('fixture', '.fixture', () => {
        env.document.addEventListener('probe', () => calls++);
        new env.window.MutationObserver(() => {});
        env.window.setTimeout(() => {}, 10);
        env.window.setInterval(() => {}, 10);
    });
    env.run();
    env.document.dispatchEvent(new Event('probe'));
    env.window.dispatchEvent(new Event('gct:navigation-ready'));
    env.document.dispatchEvent(new Event('probe'));
    assert.equal(calls, 2);
    env.leave();
    env.document.dispatchEvent(new Event('probe'));
    assert.equal(calls, 2);
    assert.equal(env.timers.size, 0);
    assert.ok(env.observers.every(observer => observer.disconnected));
});

test('an initializer that throws also releases listeners with a caller-owned signal', () => {
    const env = runtime();
    const owner = new AbortController();
    let calls = 0;
    env.window.GCTPartialNavigation.registerInitializer('fixture', '.fixture', () => {
        env.document.addEventListener('probe', () => calls++, { signal: owner.signal });
        new env.window.MutationObserver(() => {});
        env.window.setTimeout(() => {}, 10);
        throw new Error('Initialization failed');
    });
    try {
        assert.throws(() => env.run(), /Initialization failed/);
        env.document.dispatchEvent(new Event('probe'));
        assert.equal(calls, 0);
        assert.equal(env.timers.size, 0);
        assert.ok(env.observers.every(observer => observer.disconnected));
        assert.equal(owner.signal.aborted, false);
    } finally { owner.abort(); }
});

test('leaving a page removes caller-signalled handlers without aborting the caller', () => {
    const env = runtime();
    const owner = new AbortController();
    let calls = 0;
    env.window.GCTPartialNavigation.registerInitializer('fixture', '.fixture', () => env.document.addEventListener('probe', () => calls++, { signal: owner.signal }));
    env.run();
    env.leave();
    try {
        env.document.dispatchEvent(new Event('probe'));
        assert.equal(calls, 0);
        assert.equal(owner.signal.aborted, false);
    } finally { owner.abort(); }
});

function navigationRuntime({ oldResponse, oldAssets } = {}) {
    const replacements = [], fallbacks = [];
    const assetStarted = deferred();
    const response = url => ({ ok: true, url, headers: { get: () => 'text/html' }, text: async () => url });
    const context = vm.createContext({
        activeRequest: null, MAIN_SELECTOR: 'main.main, main', AbortController, console,
        absoluteUrl: url => url, getNavigationClock: () => null,
        document: { querySelector: () => ({ classList: { remove() {} } }) },
        beginMainExit: async () => {},
        fetch: url => url === '/old' && oldResponse ? oldResponse.promise : Promise.resolve(response(url)),
        DOMParser: class { parseFromString(html) { return { html }; } },
        isCompatibleDocument: () => true,
        loadDocumentAssets: doc => { if (doc.html === '/old') { assetStarted.resolve(); return oldAssets?.promise ?? Promise.resolve(); } return Promise.resolve(); },
        replaceMain: async (_doc, url) => replacements.push(url),
        fallback: url => fallbacks.push(url), cleanupTransitionState() {},
    });
    const start = source.indexOf('const navigate = async');
    const end = source.indexOf('const eligibleLink', start);
    const navigate = vm.runInContext(source.slice(start, end) + '\nnavigate;', context);
    return { navigate, replacements, fallbacks, assetStarted, response };
}

test('a superseded asset failure cannot redirect away from the newer page', async () => {
    const oldAssets = deferred();
    const env = navigationRuntime({ oldAssets });
    const old = env.navigate('/old');
    await env.assetStarted.promise;
    await env.navigate('/new');
    oldAssets.reject(new Error('Old stylesheet failed'));
    await old;
    assert.deepEqual(env.replacements, ['/new']);
    assert.deepEqual(env.fallbacks, []);
});

test('a late superseded HTTP error cannot redirect away from the newer page', async () => {
    const oldResponse = deferred();
    const env = navigationRuntime({ oldResponse });
    const old = env.navigate('/old');
    await env.navigate('/new');
    oldResponse.resolve({ ok: false, headers: { get: () => 'text/html' } });
    await old;
    assert.deepEqual(env.replacements, ['/new']);
    assert.deepEqual(env.fallbacks, []);
});

test('an active request failure still falls back to a normal navigation', async () => {
    const oldAssets = deferred();
    const env = navigationRuntime({ oldAssets });
    const old = env.navigate('/old');
    await env.assetStarted.promise;
    oldAssets.reject(new Error('Stylesheet failed'));
    await old;
    assert.deepEqual(env.fallbacks, ['/old']);
});

function paginationRuntime() {
    const pending = deferred();
    const labels = { textContent: '' };
    const row = () => ({ classList: { contains: () => false }, querySelector: () => null });
    const table = { isConnected: true, tBodies: [{ rows: [row()], appendChild(value) { this.rows.push(value); } }] };
    const wrap = new BrowserEventTarget();
    Object.assign(wrap, { isConnected: true, scrollHeight: 100, scrollTop: 90, clientHeight: 100, classList: { toggle() {} }, querySelector: () => table });
    const footer = new BrowserEventTarget();
    Object.assign(footer, { isConnected: true, dataset: { total: '2', nextUrl: '/page2', lazyPagination: 'true' }, querySelector: selector => selector === '[data-entry-count]' ? labels : null });
    const parent = { querySelector: () => wrap, contains: () => true, tagName: 'SECTION' };
    footer.parentElement = parent;
    const parsedTable = { tBodies: [{ rows: [row()] }] };
    const parsedWrap = { querySelector: () => parsedTable };
    const parsedFooter = { dataset: { nextUrl: '' }, parentElement: { querySelector: () => parsedWrap, contains: () => true, tagName: 'SECTION' } };
    const modal = { id: 'ddrView-next', dataset: {} };
    const portals = [];
    const document = new BrowserEventTarget();
    Object.assign(document, { readyState: 'complete', body: { appendChild: node => portals.push(node) }, getElementById: id => portals.find(node => node.id === id), importNode: node => node, querySelectorAll: selector => selector === '[data-scroll-pagination]' ? [footer] : [] });
    const parsed = { body: {}, querySelectorAll: selector => selector === '[data-scroll-pagination]' ? [parsedFooter] : selector.includes('.ddr-record-overlay') ? [modal] : [] };
    const window = new BrowserEventTarget();
    let fetchCount = 0, requestSignal;
    const context = vm.createContext({ window, document, AbortController, console, CustomEvent: class extends Event { constructor(type, options) { super(type); this.detail = options?.detail; } }, DOMParser: class { parseFromString() { return parsed; } }, fetch: (_url, options) => { fetchCount++; requestSignal = options.signal; return pending.promise; } });
    const pagination = readFileSync(new URL('../../resources/js/Main-js/scroll-table-pagination.js', import.meta.url), 'utf8');
    vm.runInContext(pagination, context);
    return { window, footer, wrap, table, portals, pending, get fetchCount() { return fetchCount; }, get signal() { return requestSignal; } };
}

test('a pagination response after navigation cannot append stale rows or modal portals', async () => {
    const env = paginationRuntime();
    env.wrap.dispatchEvent(new Event('scroll'));
    assert.equal(env.fetchCount, 1);
    env.window.dispatchEvent(new Event('gct:navigation-before'));
    assert.equal(env.signal.aborted, true);
    env.pending.resolve({ ok: true, text: async () => '<page />' });
    await new Promise(setImmediate);
    assert.equal(env.table.tBodies[0].rows.length, 1);
    assert.equal(env.portals.length, 0);
});

test('scroll and load-all share one request and retain DDR record modal portals', async () => {
    const env = paginationRuntime();
    env.wrap.dispatchEvent(new Event('scroll'));
    env.footer.dispatchEvent(new Event('gct:load-all-records'));
    assert.equal(env.fetchCount, 1);
    env.pending.resolve({ ok: true, text: async () => '<page />' });
    await new Promise(setImmediate);
    assert.equal(env.fetchCount, 1);
    assert.equal(env.table.tBodies[0].rows.length, 2);
    assert.equal(env.portals[0].id, 'ddrView-next');
});

function filteredDdrPortals(isDdrPage = true) {
    const portals = [];
    const removed = [];
    const document = {
        querySelectorAll: () => [{ remove() { removed.push('old-details'); } }],
        importNode: node => ({ ...node, dataset: {} }),
        body: { appendChild: node => portals.push(node) },
    };
    const script = readFileSync(new URL('../../resources/js/Main-js/automatic-table-search.js', import.meta.url), 'utf8');
    const start = script.indexOf('  const syncDdrRecordModals =');
    const end = script.indexOf('  const replaceServerFilteredTable =', start);
    const sync = vm.runInNewContext(script.slice(start, end) + '\nsyncDdrRecordModals;', { document });
    sync({ closest: () => isDdrPage }, { querySelectorAll: () => [{ id: 'ddrView-filtered' }] });
    return { portals, removed };
}

test('server-filtered DDR rows replace their stale detail portals with page-owned details', () => {
    const env = filteredDdrPortals();
    assert.deepEqual(env.removed, ['old-details']);
    assert.equal(env.portals.length, 1);
    assert.equal(env.portals[0].id, 'ddrView-filtered');
    assert.equal(env.portals[0].dataset.pageOwned, 'true');
});

test('DDR filter portal synchronization leaves other departments untouched', () => {
    const env = filteredDdrPortals(false);
    assert.deepEqual(env.removed, []);
    assert.deepEqual(env.portals, []);
});