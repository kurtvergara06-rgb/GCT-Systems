import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// A legend can remain visible while disagreeing with a correctly drawn chart.
// Compare the two actual consumers rather than asserting arbitrary brand colors.
test('fleet legend tones match the corresponding Chart.js series', () => {
    const script = readFileSync(new URL('../../resources/js/Operation/dashboard-operation.js', import.meta.url), 'utf8');
    const css = readFileSync(new URL('../../resources/css/Operation/dashboard-operation.css', import.meta.url), 'utf8');
    for (const [state, series] of Object.entries({ active: 'green', maintenance: 'amber', inactive: 'red', other: 'slate' })) {
        const chartColor = script.match(new RegExp(`${series}: '(#[a-f0-9]+)'`, 'i'))?.[1];
        const legendColor = css.match(new RegExp(`\\.fleet-dot\\.${state}\\s*\\{[^}]*background:\\s*(#[a-f0-9]+)`, 'i'))?.[1];
        assert.ok(chartColor, `Missing chart tone: ${series}`);
        assert.equal(legendColor, chartColor, `Legend disagrees for ${state}`);
    }
});