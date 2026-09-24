@php
    $inventoryMlRows = collect($predictive->inventory->rows ?? [])->take(4);
    $inventoryMlCodes = $inventoryMlRows
        ->map(fn ($row) => $row['item_code'] ?? $row[0] ?? null)
        ->filter()
        ->values();
@endphp

<section class="predictive-main-grid-two" id="inventory-model4-section">
    <x-analytics.card
        class="predictive-card"
        title="Inventory Model #4 — Next-Week Demand Forecast"
        description="Live Random Forest forecasts for the same inventory records shown in the Warehouse frontend. Demo-mode results remain synthetic/development-only."
    >
        <div id="inventory-model4-status" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
            <span style="font-size:12px;font-weight:700;">Checking Model #4…</span>
        </div>

        <div id="inventory-model4-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            @foreach($inventoryMlRows as $row)
                @php
                    $code = $row['item_code'] ?? $row[0] ?? '—';
                    $name = $row['name'] ?? $row[1] ?? 'Inventory Item';
                @endphp
                <article data-inventory-ml-code="{{ $code }}" style="border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:14px;min-height:128px;">
                    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                        <div>
                            <strong style="display:block;font-size:13px;">{{ $code }}</strong>
                            <span style="display:block;font-size:11px;color:var(--text-muted,#6b7280);margin-top:2px;">{{ $name }}</span>
                        </div>
                        <span data-ml-risk style="font-size:10px;font-weight:700;">Loading</span>
                    </div>
                    <div data-ml-body style="margin-top:14px;font-size:12px;color:var(--text-muted,#6b7280);">
                        Waiting for Model #4 prediction…
                    </div>
                </article>
            @endforeach
        </div>

        <p id="inventory-model4-disclaimer" style="font-size:10px;color:var(--text-muted,#6b7280);margin:12px 0 0;"></p>
    </x-analytics.card>

    <x-analytics.card
        class="predictive-card"
        title="How to Read This Forecast"
        description="Model #4 predicts expected quantity issued for the next week; reorder advice remains an explainable business-rule layer."
    >
        <div style="display:grid;gap:10px;font-size:12px;line-height:1.5;">
            <div><strong>Forecast:</strong> ML estimate of next-week part demand.</div>
            <div><strong>Stockout risk:</strong> Current stock is below predicted demand.</div>
            <div><strong>Suggested order:</strong> Rule-based replenishment quantity using forecast, on-hand stock, and reorder level.</div>
            <div><strong>Demo policy:</strong> Synthetic client-demo data may be shown in development, but production still requires genuine GCT stock history.</div>
        </div>
    </x-analytics.card>
</section>

<script>
(() => {
    const section = document.getElementById('inventory-model4-section');
    if (!section) return;

    const itemCodes = @json($inventoryMlCodes);
    const statusEl = document.getElementById('inventory-model4-status');
    const disclaimerEl = document.getElementById('inventory-model4-disclaimer');

    const setStatus = (label, detail = '') => {
        statusEl.innerHTML = '';
        const badge = document.createElement('span');
        badge.textContent = label;
        badge.style.cssText = 'display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:10px;font-weight:800;background:#f3f4f6;';
        statusEl.appendChild(badge);

        if (detail) {
            const text = document.createElement('span');
            text.textContent = detail;
            text.style.cssText = 'font-size:11px;color:var(--text-muted,#6b7280);';
            statusEl.appendChild(text);
        }
    };

    if (!Array.isArray(itemCodes) || itemCodes.length === 0) {
        setStatus('NO INVENTORY ITEMS', 'No records are available for Model #4 forecasting.');
        return;
    }

    fetch(@json(route('analytics.inventory-predictions', [], false)), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': @json(csrf_token()),
        },
        body: JSON.stringify({ item_codes: itemCodes }),
    })
        .then(async response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const production = data.is_production_model === true;
            const ready = data.model_ready === true;
            const dataset = data.dataset_type || data.data_source || 'Source not reported';

            if (ready) {
                setStatus(production ? 'PRODUCTION MODEL READY' : 'DEMO MODEL READY', dataset);
            } else {
                setStatus('MODEL NOT READY', data.message || dataset);
            }

            disclaimerEl.textContent = data.disclaimer || '';

            itemCodes.forEach(code => {
                const card = section.querySelector(`[data-inventory-ml-code="${CSS.escape(code)}"]`);
                if (!card) return;

                const record = data.predictions?.[code];
                const body = card.querySelector('[data-ml-body]');
                const risk = card.querySelector('[data-ml-risk]');

                if (!record?.available || !record.prediction) {
                    risk.textContent = ready ? 'Unavailable' : 'Not Ready';
                    body.textContent = ready
                        ? 'No forecast was returned for this item.'
                        : 'Model #4 is not ready in the current Python runtime.';
                    return;
                }

                const prediction = record.prediction;
                const quantity = Number(prediction.predicted_quantity_issued ?? 0);
                const orderQty = Number(prediction.suggested_order_qty ?? 0);
                const unit = prediction.unit || record.context?.unit || 'units';
                const riskStatus = String(prediction.risk_status || 'NORMAL').replaceAll('_', ' ');

                risk.textContent = riskStatus;
                body.innerHTML = `
                    <div style="display:grid;gap:5px;">
                        <div><strong style="color:var(--text-color,#111827);">${quantity.toFixed(2)} ${unit}</strong> predicted next week</div>
                        <div>On hand: <strong>${record.context?.on_hand ?? '—'}</strong> · Reorder: <strong>${record.context?.reorder_level ?? '—'}</strong></div>
                        <div>${prediction.recommended_action || 'No action required.'}</div>
                        <div>Suggested order: <strong>${orderQty.toFixed(2)} ${unit}</strong></div>
                    </div>`;
            });
        })
        .catch(error => {
            setStatus('MODEL SERVICE UNAVAILABLE', error.message);
            disclaimerEl.textContent = 'Start the local Python engine with demo mode enabled to display Model #4 forecasts.';
        });
})();
</script>
