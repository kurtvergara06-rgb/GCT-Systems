const DELAY_ENDPOINT = '/analytics/delay-predictions';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function delayRiskClass(label) {
    const normalized = String(label ?? '').trim().toLowerCase();

    if (normalized.includes('high')) return 'high';
    if (normalized.includes('moderate') || normalized.includes('minor')) return 'medium';
    return 'low';
}

function findEtaColumnIndex(headerRow) {
    return Array.from(headerRow.cells).findIndex((cell) =>
        cell.textContent.trim().toLowerCase() === 'ml eta'
    );
}

function prepareDelayColumn(table) {
    const headerRow = table.tHead?.rows?.[0];
    const body = table.tBodies?.[0];

    if (!headerRow || !body) {
        return new Map();
    }

    const existing = Array.from(headerRow.cells).findIndex((cell) =>
        cell.dataset.delayModelColumn === 'true'
    );

    if (existing >= 0) {
        return collectRows(body, existing);
    }

    const etaIndex = findEtaColumnIndex(headerRow);
    const insertIndex = etaIndex >= 0 ? etaIndex : Math.max(0, headerRow.cells.length - 1);
    const header = document.createElement('th');
    header.textContent = 'Model #3 Delay';
    header.dataset.delayModelColumn = 'true';
    header.title = 'Expected arrival delay from Delay Model #3. Source badges identify sample versus genuine models.';

    headerRow.insertBefore(header, headerRow.cells[insertIndex] ?? null);

    const rows = new Map();

    Array.from(body.rows).forEach((row) => {
        if (row.cells.length === 1 && row.cells[0].colSpan > 1) {
            row.cells[0].colSpan += 1;
            return;
        }

        const tripCode = row.cells[0]?.textContent?.trim();
        if (!tripCode) return;

        const cell = row.insertCell(insertIndex);
        cell.className = 'ft-delay-model-cell';
        cell.dataset.delayTripCode = tripCode;
        cell.innerHTML = `
            <div class="ft-delay-model ft-delay-model--loading" aria-label="Loading Delay Model #3 prediction">
                <span class="ft-delay-model__value">Loading…</span>
            </div>
        `;

        rows.set(tripCode, cell);
    });

    return rows;
}

function collectRows(body, columnIndex) {
    const rows = new Map();

    Array.from(body.rows).forEach((row) => {
        if (row.cells.length <= columnIndex) return;
        const tripCode = row.cells[0]?.textContent?.trim();
        if (tripCode) rows.set(tripCode, row.cells[columnIndex]);
    });

    return rows;
}

function renderUnavailable(cell, message = 'Unavailable') {
    if (!cell) return;

    cell.innerHTML = `
        <div class="ft-delay-model ft-delay-model--unavailable">
            <span class="ft-delay-model__value">—</span>
            <small class="ft-delay-model__meta">${escapeHtml(message)}</small>
        </div>
    `;
}

function renderPrediction(cell, prediction, response) {
    if (!cell || !prediction) {
        renderUnavailable(cell);
        return;
    }

    const rawMinutes = prediction.predicted_delay_minutes
        ?? prediction.predicted_arrival_delay_minutes;
    const minutes = Number(rawMinutes);
    const minutesText = Number.isFinite(minutes) ? `${minutes.toFixed(1)} min` : '—';
    const risk = prediction.risk_status ?? prediction.risk_level ?? 'Unknown';
    const riskClass = delayRiskClass(risk);
    const production = prediction.is_production_model === true;
    const sourceLabel = production ? 'Genuine ML' : 'Sample ML';
    const sourceClass = production ? 'genuine' : 'sample';
    const disclaimer = prediction.disclaimer
        || response.disclaimer
        || response.warning
        || '';

    cell.title = disclaimer;
    cell.innerHTML = `
        <div class="ft-delay-model">
            <div class="ft-delay-model__topline">
                <strong class="ft-delay-model__value">${escapeHtml(minutesText)}</strong>
                <span class="ft-badge ${escapeHtml(riskClass)}">${escapeHtml(risk)}</span>
            </div>
            <small class="ft-delay-model__source ft-delay-model__source--${escapeHtml(sourceClass)}">
                ${escapeHtml(sourceLabel)}
            </small>
        </div>
    `;
}

async function loadDelayPredictions() {
    const card = document.getElementById('tripPredictionsTable');
    const table = card?.querySelector('table.ft-table');

    if (!table) return;

    const rows = prepareDelayColumn(table);
    const tripCodes = Array.from(rows.keys());

    if (tripCodes.length === 0) return;

    const params = new URLSearchParams();
    tripCodes.forEach((tripCode) => params.append('trip_codes[]', tripCode));

    try {
        const response = await fetch(`${DELAY_ENDPOINT}?${params.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Delay Model #3 endpoint returned ${response.status}`);
        }

        const payload = await response.json();
        const predictions = payload?.predictions ?? {};

        rows.forEach((cell, tripCode) => {
            const item = predictions[tripCode];

            if (!payload.model_ready) {
                renderUnavailable(cell, 'Model not ready');
                cell.title = payload.warning || payload.disclaimer || '';
                return;
            }

            if (!item?.available || !item?.prediction) {
                renderUnavailable(cell, item?.schedule_found === false ? 'Schedule not found' : 'No prediction');
                return;
            }

            renderPrediction(cell, item.prediction, payload);
        });
    } catch (error) {
        rows.forEach((cell) => renderUnavailable(cell, 'Service unavailable'));
        console.warn('Delay Model #3 frontend integration unavailable:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDelayPredictions, { once: true });
} else {
    loadDelayPredictions();
}
