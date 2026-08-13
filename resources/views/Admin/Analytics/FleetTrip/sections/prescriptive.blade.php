<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <article class="analytics-kpi"><div class="analytics-kpi-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div><div><span>Diagnostic Inputs</span><strong>{{ number_format($diagnostics->review_count) }}</strong><small>Trips currently requiring review</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon green"><i class="fa-solid fa-chart-line"></i></div><div><span>Predictive Inputs</span><strong>{{ number_format($prediction->predicted_target_count) }}</strong><small>{{ $prediction->available ? 'Upcoming trips with forecasts' : 'Python service unavailable' }}</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon purple"><i class="fa-solid fa-route"></i></div><div><span>Route Actions</span><strong>Planned</strong><small>Route and schedule decision support</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon yellow"><i class="fa-solid fa-bus"></i></div><div><span>Shuttle Actions</span><strong>Planned</strong><small>Assignment decision support</small></div></article>
    </section>

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Planned Prescriptive Outputs</h3><p>Decision-support rules to be implemented after the supporting analytics are validated.</p></div><span class="analytics-card-badge">Next Phase</span></div>
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-bus"></i></span><div><strong>Shuttle Assignment</strong><small>Recommend an available shuttle using status, activity, workload, and maintenance runway.</small></div><div class="analytics-rank-value"><span class="issue-pill blue">Planned</span></div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-route"></i></span><div><strong>Route Adjustment</strong><small>Recommend route review when repeated delay or slow-movement patterns are supported by history.</small></div><div class="analytics-rank-value"><span class="issue-pill blue">Planned</span></div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-calendar-check"></i></span><div><strong>Schedule Modification</strong><small>Recommend departure-time changes when slow periods and delay-risk forecasts support a better window.</small></div><div class="analytics-rank-value"><span class="issue-pill blue">Planned</span></div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-screwdriver-wrench"></i></span><div><strong>Maintenance Handoff</strong><small>Later combine mileage, PMS thresholds, and recurring maintenance evidence.</small></div><div class="analytics-rank-value"><span class="issue-pill purple">Cross-domain</span></div></div>
            </div>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Current Readiness</h3><p>What is already available to support future recommendations.</p></div></div>
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-check"></i></span><div><strong>Descriptive Evidence</strong><small>Distance, speed, idling, duration, route performance, and bus activity are live.</small></div><div class="analytics-rank-value"><span class="issue-pill green">Ready</span></div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-check"></i></span><div><strong>Diagnostic Evidence</strong><small>Delay, slow movement, and high idling indicators are available for explainable review.</small></div><div class="analytics-rank-value"><span class="issue-pill green">Ready</span></div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-chart-line"></i></span><div><strong>Predictive Evidence</strong><small>{{ $prediction->available ? number_format($prediction->predicted_target_count) . ' upcoming trips currently have a forecast.' : 'Python forecasting is currently unavailable.' }}</small></div><div class="analytics-rank-value"><span class="issue-pill {{ $prediction->available ? 'green' : 'yellow' }}">{{ $prediction->available ? 'Ready' : 'Offline' }}</span></div></div>
            </div>
        </article>
    </section>

    <section class="analytics-card prescriptive-boundary-card">
        <div>
            <span class="section-kicker">Recommendation Boundary</span>
            <h3>Recommendations will support decisions, not automatically execute them.</h3>
            <p>The operator remains responsible for accepting, modifying, or rejecting a suggested route, schedule, shuttle, PMS, maintenance, or restocking action.</p>
        </div>
        <a href="{{ route('analytics.recommendations') }}" class="recommendation-link">Open Recommendations <i class="fa-solid fa-arrow-right"></i></a>
    </section>
</section>
