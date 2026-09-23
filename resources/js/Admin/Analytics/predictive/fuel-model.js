const FUEL_MODEL_ENDPOINT = '/analytics/fuel-predictions';

function escapeFuelHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function prepareFuelModelColumn(table) {
    const headerRow = table.tHead?.rows?.[0];
    const body = table.tBodies?.[0];
    if (!headerRow || !body) return new Map();

    let modelIndex = Array.from(headerRow.cells).findIndex(
        (cell) => cell.dataset.fuelModelColumn === 'true'
    );

    if (modelIndex < 0) {
        const fuelUsedIndex = Array.from(headerRow.cells).findIndex(
            (cell) => cell.textContent.trim().toLowerCase() === 'fuel used'
        );
        modelIndex = fuelUsedIndex >= 0 ? fuelUsedIndex + 1 : 3;

        const header = document.createElement('th');
        header.dataset.fuelModelColumn = 'true';
        header.textContent = 'Model #2 (Latest Trip)';
        header.title = 'Fuel Random Forest prediction using the latest linked genuine GCT fuel/GPS trip in the selected period.';
        headerRow.insertBefore(header, headerRow.cells[modelIndex] ?? null);
    }

    const rows = new Map();

    Array.from(body.rows).forEach((row) => {
        if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
            row.cells[0].colSpan += 1;
            return;
        }

        const busNo = row.cells[0]?.textContent?.trim();
        if (!busNo) return;

        let cell = row.querySelector('[data-fuel-model-bus]');
        if (!cell) {
            cell = row.insertCell(modelIndex);
            cell.dataset.fuelModelBus = busNo;
        }

        cell.className = 'fuel-model-cell';
        cell.innerHTML = `
            <div class="fuel-model-result fuel-model-result--loading">
                <strong>Loading…</strong>
                <small>Model #2</small>
            </div>
        `;
        rows.set(busNo.toUpperCase(), cell);
    });

    return rows;
}

function renderFuelUnavailable(cell, message = 'Unavailable') {
    if (!cell) return;
    cell.innerHTML = `
        <div class="fuel-model-result fuel-model-result--unavailable">
            <strong>—</strong>
            <small>${escapeFuelHtml(message)}</small>
        </div>
    `;
}

function renderFuelPrediction(cell, item, response) {
    const prediction = item?.prediction;
    const context = item?.context || {};
    const predicted = Number(prediction?.predicted_fuel_liters);
    const actual = Number(item?.actual_fuel_liters);
    const variance = Number(item?.variance_liters);
    const variancePct = Number(item?.variance_percent);

    if (!Number.isFinite(predicted)) {
        renderFuelUnavailable(cell, 'No prediction');
        return;
    }

    const varianceTone = Number.isFinite(variance)
        ? (variance > 0.05 ? 'above' : (variance < -0.05 ? 'below' : 'normal'))
        : 'normal';
    const varianceText = Number.isFinite(variance)
        ? `${variance >= 0 ? '+' : ''}${variance.toFixed(2)} L${Number.isFinite(variancePct) ? ` (${variancePct >= 0 ? '+' : ''}${variancePct.toFixed(1)}%)` : ''}`
        : '—';
    const actualText = Number.isFinite(actual) ? `${actual.toFixed(2)} L actual` : 'Actual unavailable';
    const sourceLabel = response?.is_production_model === true ? 'Genuine ML' : 'ML';
    const trainingCount = Number(response?.sample_count || prediction?.sample_count || 0);
    const date = context?.report_date || '';
    const route = context?.route || '';

    cell.title = [date, route].filter(Boolean).join(' · ');
    cell.innerHTML = `
        <div class="fuel-model-result">
            <div class="fuel-model-result__value-row">
                <strong>${escapeFuelHtml(predicted.toFixed(2))} L</strong>
                <span class="fuel-model-source">${escapeFuelHtml(sourceLabel)}</span>
            </div>
            <small>${escapeFuelHtml(actualText)}</small>
            <span class="fuel-model-variance fuel-model-variance--${escapeFuelHtml(varianceTone)}">${escapeFuelHtml(varianceText)}</span>
            <em>${escapeFuelHtml(date || 'Latest linked trip')}${trainingCount > 0 ? ` · ${trainingCount} training rows` : ''}</em>
        </div>
    `;
}

async function loadFuelModelPredictions() {
    const table = document.querySelector('.predictive-fuel-page .predictions-card table.predictive-table');
    if (!table) return;

    const rows = prepareFuelModelColumn(table);
    const busNos = Array.from(rows.keys());
    if (busNos.length === 0) return;

    const params = new URLSearchParams();
    busNos.forEach((busNo) => params.append('bus_nos[]', busNo));

    const currentParams = new URLSearchParams(window.location.search);
    params.set('period', currentParams.get('period') || 'this-month');

    try {
        const response = await fetch(`${FUEL_MODEL_ENDPOINT}?${params.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Fuel Model #2 endpoint returned ${response.status}`);
        }

        const payload = await response.json();
        const predictions = payload?.predictions ?? {};

        rows.forEach((cell, busNo) => {
            const item = predictions[busNo];

            if (!payload.model_ready) {
                renderFuelUnavailable(cell, 'Model not ready');
                cell.title = payload.reason || '';
                return;
            }

            if (!item?.report_found) {
                renderFuelUnavailable(cell, 'No linked fuel trip');
                return;
            }

            if (!item?.gps_linked) {
                renderFuelUnavailable(cell, 'GPS link missing');
                return;
            }

            if (!item?.available || !item?.prediction) {
                renderFuelUnavailable(cell, 'Prediction unavailable');
                return;
            }

            renderFuelPrediction(cell, item, payload);
        });
    } catch (error) {
        rows.forEach((cell) => renderFuelUnavailable(cell, 'Service unavailable'));
        console.warn('Fuel Model #2 frontend integration unavailable:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadFuelModelPredictions, { once: true });
} else {
    loadFuelModelPredictions();
}
