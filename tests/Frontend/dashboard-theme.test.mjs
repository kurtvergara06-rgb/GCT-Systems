import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/Operation/dashboard-operation.js', import.meta.url), 'utf8').replace(/^import Chart from 'chart.js\/auto';\s*/, '');
function dashboard() {
    const charts = new Map();
    let initialize, observer, observed, disconnected = false;
    let theme = { '--gct-ui-text': '#0f172a', '--gct-ui-muted': '#64748b' };
    const data = { dataset: {
        fleetReadiness: JSON.stringify({ labels: ['Active', 'Maintenance'], values: [36, 4] }),
        tripPerformance: JSON.stringify([{ label: 'Nov 3', date: '2026-11-03', scheduled: 5, completed: 4 }]),
        incidentPerformance: JSON.stringify([{ label: 'Nov 3', date: '2026-11-03', total: 1 }]),
    } };
    const canvases = new Map(['#operationFleetChart', '#operationTripPerformanceChart', '#operationIncidentTrendChart'].map(id => [id, { id }]));
    const root = { querySelector: selector => selector === '[data-operation-dashboard]' ? data : canvases.get(selector) };
    class Chart {
        static getChart(canvas) { return charts.get(canvas); }
        constructor(canvas, config) { Object.assign(this, config); this.updates = []; charts.set(canvas, this); }
        update(mode) { this.updates.push(mode); }
        destroy() { this.destroyed = true; }
    }
    class MutationObserver {
        constructor(callback) { observer = callback; }
        observe(target, options) { observed = options; }
        disconnect() { disconnected = true; }
    }
    vm.runInNewContext(source, {
        Chart, MutationObserver, getComputedStyle: () => ({ getPropertyValue: name => theme[name] || '' }),
        document: { documentElement: {} },
        window: { GCTPartialNavigation: { registerInitializer(key, selector, callback) { initialize = callback; } } },
    });
    const cleanup = initialize(root);
    return { charts: [...charts.values()], observed, cleanup, isDisconnected: () => disconnected,
        dark() { theme = { '--gct-ui-text': '#edf3fc', '--gct-ui-muted': '#b1bfd2' }; observer(); } };
}

test('dashboard canvas text follows account theme without replacing data or chart instances', () => {
    const fixture = dashboard();
    const before = fixture.charts.map(chart => JSON.stringify(chart.data));
    assert.equal(fixture.charts.length, 3);
    for (const chart of fixture.charts.filter(chart => chart.type === 'bar')) {
        assert.equal(chart.options.scales.x.ticks.color, '#64748b');
        assert.equal(chart.options.plugins.operationBarValueLabels.textColor, '#0f172a');
    }
    fixture.dark();
    assert.equal(fixture.observed.attributeFilter.join(','), 'data-gct-theme');
    fixture.charts.forEach((chart, index) => assert.equal(JSON.stringify(chart.data), before[index]));
    for (const chart of fixture.charts.filter(chart => chart.type === 'bar')) {
        assert.equal(chart.options.scales.x.ticks.color, '#b1bfd2');
        assert.equal(chart.options.scales.y.ticks.color, '#b1bfd2');
        assert.equal(chart.options.plugins.operationBarValueLabels.textColor, '#edf3fc');
        assert.equal(chart.options.plugins.operationBarValueLabels.zeroColor, '#b1bfd2');
        assert.equal(chart.updates.join(','), 'none');
    }
    fixture.cleanup();
    assert.equal(fixture.isDisconnected(), true);
    assert.ok(fixture.charts.every(chart => chart.destroyed));
});

test('bar labels draw real zero and nonzero counts using theme colors', () => {
    const { charts, cleanup } = dashboard();
    const chart = charts.find(chart => chart.type === 'bar');
    let color;
    const drawn = [];
    chart.ctx = { save() {}, restore() {}, set fillStyle(value) { color = value; }, fillText(value) { drawn.push([value, color]); } };
    chart.chartArea = { top: 0 };
    chart.getDatasetMeta = () => ({ data: [{ x: 10, y: 50 }] });
    chart.data.datasets[0].data = [0];
    chart.plugins[0].afterDatasetsDraw(chart, {}, chart.options.plugins.operationBarValueLabels);
    assert.equal(JSON.stringify(drawn), JSON.stringify([['0', '#64748b'], ['4', '#0f172a']]));
    cleanup();
});
