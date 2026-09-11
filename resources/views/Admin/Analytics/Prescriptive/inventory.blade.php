@php
    $invData = $prescriptive?->inventory;
    $kpis = collect($invData?->kpis ?? []);
    $poBatches = collect($invData?->po_batches ?? []);
@endphp

<div class="prescriptive-page prescriptive-inventory-page">

    {{-- EXECUTIVE AI BANNER --}}
    <div class="predictive-ai-banner prescriptive-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">Procurement Prescriptive Engine</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    Consolidated Reorder Batch Formulated
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                The prescriptive inventory model formulated a <strong>consolidated emergency purchase order for 10 depleted parts</strong> totaling <strong>₱62,400</strong>. Immediate dispatch to primary vendors (Cebu Auto Supply & Metro Fleet Parts) prevents <strong>zero-stockout groundings</strong> on scheduled brake and filtration overhaul intervals.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('inventory') }}" class="btn-ai-reorder">
                <i class="fa-solid fa-cart-plus"></i>
                <span>Open Purchase Orders</span>
            </a>
        </div>
    </div>

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
                            <span class="queue-domain-pill">
                                <i class="fa-solid fa-barcode"></i>
                                {{ $batch['item_code'] }}
                            </span>
                            <span class="queue-urgency-badge {{ strtolower($batch['priority']) === 'critical' ? 'danger' : 'warning' }}">
                                {{ $batch['priority'] }} Priority
                            </span>
                        </div>
                        <h4 class="queue-item-title">{{ $batch['item_name'] }}</h4>
                        <p class="queue-item-impact">
                            <strong>Vendor:</strong> {{ $batch['supplier'] }} &bull; Lead Time: {{ $batch['lead_time'] }}
                        </p>
                        <div class="queue-item-footer">
                            <span class="queue-savings">
                                <i class="fa-solid fa-coins"></i>
                                {{ $batch['reorder_qty'] }} units &bull; {{ $batch['total_cost'] }}
                            </span>
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
        description="Calculated replenishment quantities, pricing, and supplier fulfillment routing."
    >
        <div class="analytics-table-wrapper">
            <table class="analytics-data-table prescriptive-table">
                <thead>
                    <tr>
                        <th>Item SKU</th>
                        <th>Item Name & Category</th>
                        <th>Current On-Hand</th>
                        <th>Prescribed Qty</th>
                        <th>Est. Unit Cost</th>
                        <th>Total Cost</th>
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
                                    +{{ $batch['reorder_qty'] }} units
                                </span>
                            </td>
                            <td>{{ $batch['unit_cost'] }}</td>
                            <td>
                                <strong class="table-cost-total">{{ $batch['total_cost'] }}</strong>
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
