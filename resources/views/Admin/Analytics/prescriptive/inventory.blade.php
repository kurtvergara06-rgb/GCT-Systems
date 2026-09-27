@php
    $invData = $prescriptive?->inventory;
    $kpis = collect($invData?->kpis ?? []);
    $poBatches = collect($invData?->po_batches ?? []);
@endphp

<div class="prescriptive-page prescriptive-inventory-page">

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip" aria-label="Inventory Prescriptive KPIs">
        @foreach($kpis as $kpi)
            <x-analytics.kpi
                :label="$kpi['label']"
                :value="$kpi['value']"
                :description="$kpi['caption']"
                :icon="$kpi['icon']"
                :icon-variant="match ($kpi['tone']) {
                    'danger' => 'red',
                    'warning' => 'yellow',
                    'success' => 'green',
                    'purple' => 'purple',
                    default => 'blue',
                }"
            />
        @endforeach
    </section>

    {{-- MIDDLE GRID --}}
    <section class="prescriptive-domain-grid">

        {{-- Inventory Restock Simulation Chart --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-chart-card"
            title="Depletion vs Prescribed Restock Buffer"
            description="Simulated on-hand quantities before and after executing prescriptive purchase order batches."
        >
            <x-slot:headerActions>
                <span class="ft-telemetry-badge">
                    <i class="fa-solid fa-shield-check"></i>
                    <span>100% Stockout Interception</span>
                </span>
            </x-slot:headerActions>

            <div class="prescriptive-chart-legend" aria-hidden="true">
                <span><i class="legend-bar red"></i> Current On Hand (Qty)</span>
                <span><i class="legend-bar green"></i> Restocked Buffer (Qty)</span>
            </div>

            <div class="prescriptive-chart-container">
                <canvas id="inventoryPrescriptiveChart" role="img" aria-label="Inventory reorder simulation chart"></canvas>
            </div>
        </x-analytics.card>

        {{-- Expedited PO Batch Cards --}}
        <x-analytics.card
            class="prescriptive-card"
            title="Emergency Restock Batches"
            description="Priority orders grouped by primary supplier and component criticality."
        >
            <div class="prescriptive-queue-list">
                @foreach($poBatches as $batch)
                    <div class="prescriptive-queue-item">
                        <div class="queue-item-header">
                            <div class="queue-route-label">
                                <i class="fa-solid fa-barcode"></i>
                                <span>{{ $batch['item_code'] }}</span>
                            </div>
                            <span class="queue-priority-indicator {{ strtolower($batch['priority']) === 'critical' ? 'high' : 'medium' }}">
                                <span class="priority-dot"></span>
                                {{ $batch['priority'] }} Priority
                            </span>
                        </div>
                        <h4 class="queue-item-title">{{ $batch['item_name'] }}</h4>
                        <p class="queue-item-impact">
                            {{ $batch['supplier'] }} &bull; Lead Time: {{ $batch['lead_time'] }}
                        </p>
                        <div class="queue-item-footer">
                            <div class="queue-impact-metric">
                                <i class="fa-solid fa-boxes-stacked"></i>
                                <span>+{{ $batch['reorder_qty'] }} {{ $batch['unit_of_measurement'] ?? 'units' }} &bull; Min: {{ $batch['safety_buffer'] ?? 'Buffer' }}</span>
                            </div>
                            <a href="{{ route('inventory') }}" class="btn-queue-action">
                                <i class="fa-solid fa-plus"></i>
                                <span>Create PO</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- CONSOLIDATED PURCHASE ORDER TABLE --}}
    <x-analytics.card
        class="prescriptive-table-card"
        title="Prescribed Purchase Order Batch & Supplier Dispatch Queue"
        description="Calculated replenishment quantities, safety buffers, and supplier fulfillment routing."
    >
        <div class="analytics-table-wrapper">
            <table class="analytics-data-table prescriptive-table">
                <thead>
                    <tr>
                        <th>Item SKU</th>
                        <th>Item Name & Category</th>
                        <th>Current On-Hand</th>
                        <th>Prescribed Qty</th>
                        <th>Safety Buffer (Min)</th>
                        <th>Storage Location</th>
                        <th>Preferred Supplier</th>
                        <th>Lead Time</th>
                        <th>Priority</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($poBatches as $batch)
                        <tr>
                            <td>
                                <strong class="table-bus-chip">{{ $batch['item_code'] }}</strong>
                            </td>
                            <td>
                                <div class="item-name-wrap">
                                    <strong class="item-primary-name">{{ $batch['item_name'] }}</strong>
                                    <small class="item-category-sub">{{ $batch['category'] }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="stock-badge {{ $batch['current_stock'] <= 0 ? 'zero' : 'low' }}">
                                    {{ $batch['current_stock'] }} in stock
                                </span>
                            </td>
                            <td>
                                <span class="reorder-qty-pill">
                                    +{{ $batch['reorder_qty'] }} {{ $batch['unit_of_measurement'] ?? 'units' }}
                                </span>
                            </td>
                            <td>{{ $batch['safety_buffer'] }}</td>
                            <td>
                                <span class="lead-time-text">
                                    <i class="fa-solid fa-warehouse"></i>
                                    {{ $batch['storage_location'] }}
                                </span>
                            </td>
                            <td>
                                <span class="supplier-pill">
                                    <i class="fa-solid fa-truck"></i>
                                    {{ $batch['supplier'] }}
                                </span>
                            </td>
                            <td>
                                <span class="lead-time-text">
                                    <i class="fa-solid fa-clock"></i>
                                    {{ $batch['lead_time'] }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill {{ strtolower($batch['priority']) === 'critical' ? 'critical' : 'warning' }}">
                                    {{ $batch['priority'] }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('inventory') }}" class="btn-table-action">
                                    <span>Generate PO</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    {{-- PROCUREMENT GOVERNANCE --}}
    <section class="prescriptive-governance-section">
        <h3 class="gov-heading">
            <i class="fa-solid fa-shield-halved"></i>
            Procurement Governance & Reorder Thresholds
        </h3>
        <div class="gov-grid">
            <div class="gov-card">
                <div class="gov-card__icon blue"><i class="fa-solid fa-calculator"></i></div>
                <div>
                    <h4>Dynamic Safety Buffer Algorithm</h4>
                    <p>Safety stock levels automatically float upwards by +15% during peak quarterly inspection periods to prevent stockout spikes.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon green"><i class="fa-solid fa-handshake"></i></div>
                <div>
                    <h4>Pre-Negotiated Volume Pricing</h4>
                    <p>Batch ordering across brake sets and engine oils captures pre-negotiated tier discounts from Cebu Auto Supply Corp.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon purple"><i class="fa-solid fa-truck-fast"></i></div>
                <div>
                    <h4>Emergency Same-Day Dispatch SLA</h4>
                    <p>Critical tier orders triggered before 11:00 AM qualify for expedited hot-shot local courier delivery within 6 hours.</p>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
    window.inventoryPrescriptiveData = {
        labels: @json($poBatches->pluck('item_code')),
        current: @json($poBatches->pluck('current_stock')),
        restocked: @json($poBatches->map(fn($b) => $b['current_stock'] + $b['reorder_qty']))
    };
</script>
