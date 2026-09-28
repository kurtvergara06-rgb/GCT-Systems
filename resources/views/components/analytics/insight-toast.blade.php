@props([
    'stage' => 'descriptive',
    'domain' => 'all',
])

@php
    $stage = strtolower(trim((string) $stage));
    $domain = strtolower(trim((string) $domain));

    $insights = [
        'overview' => [
            'label' => 'Cross-Domain Insight',
            'domains' => [
                'all' => [
                    'title' => 'Cross-Domain Analytics Summary',
                    'message' => 'Fleet operations stable; 3 maintenance actions and 2 inventory reorders require attention.',
                    'metric' => '8 Open Actions · 91.7% Fleet',
                    'priority' => 'info',
                    'icon' => 'fa-solid fa-chart-pie',
                    'action' => 'View Details',
                    'target' => '.priority-findings-grid .priority-finding:not(.empty):first-child, .priority-findings-grid .priority-finding:first-child, .health-module.fleet, .executive-snapshot',
                ],
            ],
        ],
        'descriptive' => [
            'label' => 'Descriptive Insight',
            'domains' => [
                'all' => [
                    'title' => 'Operational Volume Summary',
                    'message' => '286 total trips recorded across active routes with 91.7% fleet availability.',
                    'metric' => '286 Trips · 42.6 km/h',
                    'priority' => 'info',
                    'icon' => 'fa-solid fa-chart-column',
                    'action' => 'View Details',
                    'target' => '.descriptive-overview-lower-grid .refined-ranking-row:first-child, .descriptive-alerts-table tbody tr:first-child, .analytics-kpi-strip .analytics-kpi:first-child',
                ],
                'fleet-trip' => [
                    'title' => 'Trip Volume & Speed Baseline',
                    'message' => 'Average trip velocity recorded at 42.6 km/h with 91.7% fleet availability.',
                    'metric' => '+12.3% trip volume',
                    'priority' => 'info',
                    'icon' => 'fa-solid fa-route',
                    'action' => 'View Details',
                    'target' => '.fleet-trip-alerts-table tbody tr:first-child, .refined-ranking-row:first-child, .fleet-trip-chart-card',
                ],
                'fuel' => [
                    'title' => 'Fuel Usage Summary',
                    'message' => 'Fuel consumption increased 8% this period across 3,842 L recorded burn.',
                    'metric' => 'Fuel +8% · 6.8 km/L',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-gas-pump',
                    'action' => 'View Details',
                    'target' => '.analytics-fuel-table tbody tr[data-fuel-bus]:first-child, .analytics-fuel-table tbody tr:first-child, .fuel-details-card',
                ],
                'bus-health' => [
                    'title' => 'Maintenance & PMS Overview',
                    'message' => '2 priority buses are nearing their mandatory PMS mileage threshold.',
                    'metric' => '1,580 km PMS runway',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-heart-pulse',
                    'action' => 'View Details',
                    'target' => '.bus-health-attention-list .bus-health-attention-row:first-child, .bus-health-unit-list .bus-health-unit-row:first-child, .bus-health-status-panel',
                ],
                'inventory' => [
                    'title' => 'Inventory Stock Levels',
                    'message' => '81.1% threshold compliance recorded; 2 critical parts depleted to zero on-hand.',
                    'metric' => '10 items below buffer',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-boxes-stacked',
                    'action' => 'View Details',
                    'target' => '.inventory-atrisk-table tbody tr:first-child, .inventory-table tbody tr:first-child, .inventory-table-section',
                ],
            ],
        ],
        'diagnostic' => [
            'label' => 'Diagnostic Finding',
            'domains' => [
                'all' => [
                    'title' => 'Delay Cause Detected',
                    'message' => 'Traffic congestion is the primary contributor to recent delays across active routes.',
                    'metric' => '68% delay anomaly factor',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-triangle-exclamation',
                    'action' => 'View Details',
                    'target' => '.diag-primary-causes .diag-list-row:first-child, .diag-table-detailed tbody tr:first-child, .diag-card',
                ],
                'fleet-trip' => [
                    'title' => 'Delay Cause Detected',
                    'message' => 'Traffic congestion is the primary contributor to recent delays.',
                    'metric' => '+14.2 min peak delay',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-triangle-exclamation',
                    'action' => 'View Details',
                    'target' => '.diag-table tbody tr:first-child, .fleet-main-grid .diag-list-row:first-child, .diag-evidence-card',
                ],
                'fuel' => [
                    'title' => 'Fuel Wastage Factor Identified',
                    'message' => 'Depot idling and low-speed traffic account for 42% of excess fuel consumption.',
                    'metric' => '3 buses flagged for idle burn',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-fire-flame-curved',
                    'action' => 'View Details',
                    'target' => '.diag-table tbody tr:first-child, .fuel-secondary-grid .diag-list-row:first-child, .diag-card',
                ],
                'bus-health' => [
                    'title' => 'Recurring Component Wear',
                    'message' => 'Brake wear variance is 1.8x higher on steep regional routes with frequent stops.',
                    'metric' => 'Bus #015 PMS threshold breached',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-screwdriver-wrench',
                    'action' => 'View Details',
                    'target' => '.health-card-attention .diag-list-row:first-child, .health-table tbody tr:first-child, .diag-card',
                ],
                'inventory' => [
                    'title' => 'Stockout Root Cause',
                    'message' => 'Accelerated brake wear cycles depleted safety stock faster than supplier replenishment.',
                    'metric' => 'Supplier lead gap: 5 days',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-box-open',
                    'action' => 'View Details',
                    'target' => '.inv-card-reorder .diag-list-row:first-child, .inv-table tbody tr:first-child, .diag-card',
                ],
            ],
        ],
        'predictive' => [
            'label' => 'Predictive Alert',
            'domains' => [
                'all' => [
                    'title' => 'Delay Risk Detected',
                    'message' => 'Bus 07 has an 82% predicted delay risk during the upcoming evening peak window.',
                    'metric' => '82% risk · Bus 07',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'action' => 'View Details',
                    'target' => '.risk-list .risk-item:first-child, .all-domain-table tbody tr:first-child, .risk-card',
                ],
                'fleet-trip' => [
                    'title' => 'Delay Risk Detected',
                    'message' => 'Bus 07 has an 82% predicted delay risk.',
                    'metric' => '82% risk · Bus 07',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'action' => 'View Details',
                    'target' => '#tripPredictionsTable .ft-table tbody tr:first-child, .ft-issues-list .ft-issue:first-child, .ft-table tbody tr:first-child',
                ],
                'fuel' => [
                    'title' => 'Fuel Depletion Horizon',
                    'message' => 'Projected fleet fuel consumption will reach 4,120 L next cycle (+7.2% vs baseline).',
                    'metric' => '+278 L projected increase',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-chart-line',
                    'action' => 'View Details',
                    'target' => '.predictive-table tbody tr:first-child, .fuel-burn-card, .predictive-card',
                ],
                'bus-health' => [
                    'title' => 'PMS Timing Alert',
                    'message' => 'Bus #015 projected to breach safety tolerance within 48 operational service hours.',
                    'metric' => '1,580 km remaining runway',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-gauge-high',
                    'action' => 'View Details',
                    'target' => '.predictive-health-table tbody tr:first-child, .predictive-card',
                ],
                'inventory' => [
                    'title' => 'Stockout Runway Forecast',
                    'message' => 'Transmission Fluid 4L and AC Refrigerant forecast zero days operating buffer remaining.',
                    'metric' => 'Immediate stockout horizon',
                    'priority' => 'critical',
                    'icon' => 'fa-solid fa-hourglass-end',
                    'action' => 'View Details',
                    'target' => '.predictive-inventory-table tbody tr:first-child, .predictive-card',
                ],
            ],
        ],
        'prescriptive' => [
            'label' => 'Prescriptive Recommendation',
            'domains' => [
                'all' => [
                    'title' => 'Recommended Actions',
                    'message' => '4 operational recommendations are ready for review.',
                    'metric' => '4 actions ready · High impact',
                    'priority' => 'info',
                    'icon' => 'fa-solid fa-lightbulb',
                    'action' => 'Review Recommendations',
                    'target' => '.prescriptive-queue-list .prescriptive-queue-item:first-child, .prescriptive-table tbody tr:first-child, .prescriptive-playbooks-card',
                ],
                'fleet-trip' => [
                    'title' => 'Schedule & Route Optimization',
                    'message' => 'Stagger Corridor B departures by 10 mins to absorb traffic and mitigate delay cascade.',
                    'metric' => '+5.8% on-time recovery',
                    'priority' => 'info',
                    'icon' => 'fa-solid fa-shuffle',
                    'action' => 'Review Recommendations',
                    'target' => '.prescriptive-table tbody tr:first-child, .prescriptive-card:first-child',
                ],
                'fuel' => [
                    'title' => 'Fuel Optimization',
                    'message' => '4 recommended actions are ready to reduce idling and optimize route assignments.',
                    'metric' => '4 actions ready · 168 L/mo savings',
                    'priority' => 'info',
                    'icon' => 'fa-solid fa-gas-pump',
                    'action' => 'Review Recommendations',
                    'target' => '.prescriptive-table tbody tr:first-child, .prescriptive-card:first-child',
                ],
                'bus-health' => [
                    'title' => 'Preventive Maintenance Scheduling',
                    'message' => 'Assign Bus #015 to Maintenance Bay 2 for PMS Service A before tomorrow 06:00.',
                    'metric' => 'PMS Service A · Bay 2',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-calendar-check',
                    'action' => 'Review Recommendations',
                    'target' => '.prescriptive-table tbody tr:first-child, .prescriptive-card:first-child',
                ],
                'inventory' => [
                    'title' => 'Automated Reorder Dispatch',
                    'message' => 'Generate PO for 15 cans of Transmission Fluid 4L and 20 cans of R134a refrigerant.',
                    'metric' => '2 PO requisitions prepared',
                    'priority' => 'warning',
                    'icon' => 'fa-solid fa-cart-shopping',
                    'action' => 'Review Recommendations',
                    'target' => '.prescriptive-table tbody tr:first-child, .prescriptive-card:first-child',
                ],
            ],
        ],
    ];

    $stageData = $insights[$stage] ?? $insights['descriptive'];
    $domainData = $stageData['domains'][$domain] ?? $stageData['domains']['all'];
    $priority = $domainData['priority'] ?? 'info';
    $sessionKey = 'gct_insight_dismissed_' . $stage . '_' . $domain;
@endphp

<div
    class="analytics-insight-toast-container"
    data-analytics-insight-toast
    data-session-key="{{ $sessionKey }}"
    data-target-selector="{{ $domainData['target'] }}"
    role="region"
    aria-label="Analytics Insight Notification"
>
    <div class="analytics-insight-toast priority-{{ $priority }} stage-{{ $stage }}">
        <div class="toast-header-row">
            <div class="toast-eyebrow">
                <span class="toast-icon-wrap">
                    <i class="{{ $domainData['icon'] }}"></i>
                </span>
                <span class="toast-tag">{{ $stageData['label'] }}</span>
                <span class="toast-priority-pill priority-{{ $priority }}">{{ ucfirst($priority) }}</span>
            </div>

            <button
                type="button"
                class="toast-dismiss-btn"
                aria-label="Dismiss insight notification"
                title="Dismiss"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="toast-body-row">
            <strong class="toast-headline">{{ $domainData['title'] }}</strong>
            <p class="toast-copy">{{ $domainData['message'] }}</p>
        </div>

        <div class="toast-footer-row">
            <span class="toast-metric-chip">
                <i class="fa-solid fa-circle-info"></i>
                {{ $domainData['metric'] }}
            </span>

            <button
                type="button"
                class="toast-action-btn"
                data-toast-action
            >
                <span>{{ $domainData['action'] }}</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        const container = document.querySelector('[data-analytics-insight-toast]');
        if (!container) return;

        const sessionKey = container.dataset.sessionKey;
        if (sessionStorage.getItem(sessionKey) === '1') {
            container.remove();
            return;
        }

        // 1. Notification Bell Animation (runs 2-3s only, then returns to static state)
        const bellBtn = document.querySelector('.icon-btn.notification');
        if (bellBtn) {
            bellBtn.classList.add('is-ringing');
            setTimeout(function () {
                bellBtn.classList.remove('is-ringing');
            }, 2300);

            // Small unread notification indicator badge
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                if (badge.hasAttribute('hidden') || badge.textContent.trim() === '0' || badge.style.display === 'none') {
                    badge.removeAttribute('hidden');
                    badge.style.display = 'inline-flex';
                    badge.textContent = '1';
                }
            }
        }

        // 2. Auto-dismiss timer (10 seconds) with hover pause and resume
        let autoDismissTimer = null;
        let remainingTime = 10000;
        let lastStartTime = Date.now();

        function startTimer() {
            lastStartTime = Date.now();
            autoDismissTimer = setTimeout(function () {
                dismissToast(false);
            }, remainingTime);
        }

        function pauseTimer() {
            if (autoDismissTimer) {
                clearTimeout(autoDismissTimer);
                autoDismissTimer = null;
                remainingTime -= (Date.now() - lastStartTime);
                if (remainingTime < 2000) remainingTime = 2000;
            }
        }

        function dismissToast(userExplicit) {
            if (autoDismissTimer) {
                clearTimeout(autoDismissTimer);
                autoDismissTimer = null;
            }
            if (userExplicit) {
                sessionStorage.setItem(sessionKey, '1');
            }
            const toastEl = container.querySelector('.analytics-insight-toast');
            if (toastEl) {
                toastEl.classList.add('is-dismissing');
            }
            setTimeout(function () {
                container.remove();
            }, 260);
        }

        startTimer();

        container.addEventListener('mouseenter', pauseTimer);
        container.addEventListener('mouseleave', function () {
            startTimer();
        });

        const closeBtn = container.querySelector('.toast-dismiss-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                dismissToast(true);
            });
        }

        // 3. Global & Reusable Target Highlighting Function
        window.gctHighlightTarget = function (targetSelectorOrEl) {
            let targetEl = null;
            if (typeof targetSelectorOrEl === 'string') {
                const selectorList = targetSelectorOrEl.split(',');
                for (let i = 0; i < selectorList.length; i++) {
                    const sel = selectorList[i].trim();
                    if (sel) {
                        const found = document.querySelector(sel);
                        if (found) {
                            targetEl = found;
                            break;
                        }
                    }
                }
            } else if (targetSelectorOrEl instanceof Element) {
                targetEl = targetSelectorOrEl;
            }

            if (!targetEl) return null;

            // Scroll into view smoothly (centered vertically)
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Clear previous highlight timer if user clicked multiple times
            if (targetEl._gctHighlightTimer) {
                clearTimeout(targetEl._gctHighlightTimer);
                targetEl._gctHighlightTimer = null;
            }

            // Remove class and trigger DOM reflow to reliably restart animation
            targetEl.classList.remove('gct-highlight-pulse');
            void targetEl.offsetWidth;
            targetEl.classList.add('gct-highlight-pulse');

            // Keep highlight visible for ~2.6 seconds (within 2-4s range), then smoothly return to normal
            targetEl._gctHighlightTimer = setTimeout(function () {
                targetEl.classList.remove('gct-highlight-pulse');
                targetEl._gctHighlightTimer = null;
            }, 2600);

            return targetEl;
        };

        // 4. "View Details" / "Review Recommendations" click handler
        const actionBtn = container.querySelector('[data-toast-action]');
        if (actionBtn) {
            actionBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const selectorString = container.dataset.targetSelector || '';
                window.gctHighlightTarget(selectorString);

                // Reset timer giving user time to view highlighted entry and click multiple times
                if (autoDismissTimer) {
                    clearTimeout(autoDismissTimer);
                }
                remainingTime = 8000;
                startTimer();
            });
        }
    })();
</script>
