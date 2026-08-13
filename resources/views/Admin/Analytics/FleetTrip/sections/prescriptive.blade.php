@php
    $predictiveEvidenceReady = $prediction->available && $prediction->predicted_target_count > 0;
    $availableLayers = 2 + ($predictiveEvidenceReady ? 1 : 0);
    $layerCoverage = ($availableLayers / 3) * 100;
    $routeReviewSignals = (int) $diagnostics->delayed_with_slow_movement;
    $scheduleSignals = (int) $prediction->peak_periods->count();
    $primaryPeak = $prediction->peak_periods->first();
@endphp

<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <x-analytics.kpi label="Diagnostic Inputs" :value="number_format($diagnostics->review_count)" small="Trips currently requiring review" icon="fa-magnifying-glass-chart" />
        <x-analytics.kpi label="Predictive Inputs" :value="number_format($prediction->predicted_target_count)" :small="$prediction->available ? 'Upcoming trips with forecasts' : 'Python service unavailable'" icon="fa-chart-line" tone="green" />
        <x-analytics.kpi label="Rule Families" value="4" small="Shuttle, route, schedule, and maintenance decision support" icon="fa-diagram-project" tone="purple" />
        <x-analytics.kpi label="Decision Mode" value="Advisory" small="Operator approval remains required" icon="fa-user-check" tone="yellow" />
    </section>

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card">
            <x-analytics.card-header title="Recommendation Pipeline" description="Planned decision-support outputs and the evidence each rule family will consume." badge="5.4 next phase" />
            <div class="prescriptive-pipeline-list">
                <div class="prescriptive-pipeline-row">
                    <span class="prescriptive-pipeline-icon"><i class="fa-solid fa-bus"></i></span>
                    <div><strong>Shuttle Assignment</strong><small>Active bus pool + recent activity + upcoming workload + maintenance runway.</small></div>
                    <div class="prescriptive-pipeline-status"><span class="issue-pill blue">Rule pending</span><small>{{ number_format($activeBuses) }} active buses in master list</small></div>
                </div>
                <div class="prescriptive-pipeline-row">
                    <span class="prescriptive-pipeline-icon"><i class="fa-solid fa-route"></i></span>
                    <div><strong>Route Adjustment</strong><small>Repeated delay and slow-movement evidence supported by historical route patterns.</small></div>
                    <div class="prescriptive-pipeline-status"><span class="issue-pill {{ $routeReviewSignals > 0 ? 'yellow' : 'green' }}">{{ $routeReviewSignals > 0 ? 'Evidence present' : 'No current signal' }}</span><small>{{ number_format($routeReviewSignals) }} delay + slow records</small></div>
                </div>
                <div class="prescriptive-pipeline-row">
                    <span class="prescriptive-pipeline-icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <div><strong>Schedule Modification</strong><small>Historical slow periods + upcoming delay-risk forecasts for better departure windows.</small></div>
                    <div class="prescriptive-pipeline-status"><span class="issue-pill {{ $scheduleSignals > 0 ? 'yellow' : 'green' }}">{{ $scheduleSignals > 0 ? 'Evidence present' : 'No current signal' }}</span><small>{{ number_format($scheduleSignals) }} historical slow-period indicators</small></div>
                </div>
                <div class="prescriptive-pipeline-row">
                    <span class="prescriptive-pipeline-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                    <div><strong>Maintenance Handoff</strong><small>Mileage + PMS thresholds + recurring maintenance evidence from the Maintenance domain.</small></div>
                    <div class="prescriptive-pipeline-status"><span class="issue-pill purple">Cross-domain</span><small>{{ number_format($underMaintenance) }} buses currently under maintenance</small></div>
                </div>
            </div>
        </article>

        <article class="analytics-card prescriptive-readiness-card">
            <x-analytics.card-header title="Evidence Layers Available" description="Analytics inputs currently available before 5.4 recommendation rules are implemented." :badge="$availableLayers . '/3 layers available'" />
            <div class="analytics-availability-layout">
                <div class="availability-score">
                    <div class="availability-ring" style="--availability-angle: {{ min(360, max(0, $layerCoverage * 3.6)) }}deg;">
                        <div class="availability-ring-center"><strong>{{ $availableLayers }}/3</strong><span>Evidence layers</span></div>
                    </div>
                </div>
                <div class="availability-breakdown">
                    <div class="availability-row"><div><span class="availability-dot operational"></span><span>Descriptive Evidence</span></div><strong>Available</strong></div>
                    <div class="availability-row"><div><span class="availability-dot operational"></span><span>Diagnostic Evidence</span></div><strong>Available</strong></div>
                    <div class="availability-row"><div><span class="availability-dot {{ $predictiveEvidenceReady ? 'operational' : 'maintenance' }}"></span><span>Predictive Evidence</span></div><strong>{{ $predictiveEvidenceReady ? 'Available' : 'Waiting' }}</strong></div>
                </div>
            </div>
            <div class="prescriptive-readiness-note"><i class="fa-solid fa-circle-info"></i> Available evidence does not mean 5.4 is live. Recommendation rules still remain to be implemented and validated.</div>
        </article>
    </section>

    <section class="prescriptive-candidate-grid">
        <article class="prescriptive-candidate-card {{ $routeReviewSignals > 0 ? 'attention' : 'clear' }}">
            <div class="prescriptive-candidate-head"><span><i class="fa-solid fa-route"></i></span><small>Route review signal</small></div>
            <strong>{{ number_format($routeReviewSignals) }}</strong>
            <p>Delayed trips also showing slow movement.</p>
            <span class="prescriptive-candidate-state">{{ $routeReviewSignals > 0 ? 'Candidate evidence available' : 'No current candidate signal' }}</span>
        </article>

        <article class="prescriptive-candidate-card {{ $scheduleSignals > 0 ? 'attention' : 'clear' }}">
            <div class="prescriptive-candidate-head"><span><i class="fa-solid fa-clock"></i></span><small>Schedule-window signal</small></div>
            <strong>{{ number_format($scheduleSignals) }}</strong>
            <p>{{ $primaryPeak ? 'Top historical slow period: ' . $primaryPeak->period . '.' : 'No historical slow period currently meets the rule.' }}</p>
            <span class="prescriptive-candidate-state">Historical evidence only</span>
        </article>

        <article class="prescriptive-candidate-card clear">
            <div class="prescriptive-candidate-head"><span><i class="fa-solid fa-bus-simple"></i></span><small>Active shuttle pool</small></div>
            <strong>{{ number_format($activeBuses) }}</strong>
            <p>Active buses in the Bus Master List before assignment-specific checks.</p>
            <span class="prescriptive-candidate-state">Input available</span>
        </article>

        <article class="prescriptive-candidate-card cross-domain">
            <div class="prescriptive-candidate-head"><span><i class="fa-solid fa-screwdriver-wrench"></i></span><small>Maintenance handoff</small></div>
            <strong>{{ number_format($underMaintenance) }}</strong>
            <p>Buses currently under maintenance; PMS and maintenance rules remain cross-domain.</p>
            <span class="prescriptive-candidate-state">Cross-domain input</span>
        </article>
    </section>

    <section class="analytics-list-grid prescriptive-queue-grid">
        <article class="analytics-card">
            <x-analytics.card-header title="Diagnostic Queue" description="Current evidence that can later trigger recommendation rules." :badge="number_format($diagnostics->review_count) . ' trips'" />
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-clock-rotate-left"></i></span><div><strong>{{ number_format($diagnostics->delay_count) }} delay indicators</strong><small>Trips above the route delay threshold.</small></div><div class="analytics-rank-value">Delay</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-gauge-simple-low"></i></span><div><strong>{{ number_format($diagnostics->slow_movement_count) }} slow-movement records</strong><small>Trips below 80% of their route-speed baseline.</small></div><div class="analytics-rank-value">Slow</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-hourglass-half"></i></span><div><strong>{{ number_format($diagnostics->high_idle_count) }} high-idling records</strong><small>Strict idle-duration and idle-share rule.</small></div><div class="analytics-rank-value">Idle</div></div>
            </div>
        </article>

        <article class="analytics-card">
            <x-analytics.card-header title="Forecast Queue" description="Upcoming trips and historical periods available to future prescriptive rules." :badge="number_format($prediction->predicted_target_count) . ' forecasted'" />
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-bullseye"></i></span><div><strong>{{ number_format($prediction->target_count) }} upcoming targets</strong><small>Scheduled or ready trips reviewed by the prediction service.</small></div><div class="analytics-rank-value">Targets</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-chart-line"></i></span><div><strong>{{ number_format($prediction->predicted_target_count) }} forecastable trips</strong><small>Upcoming trips with enough comparable route history.</small></div><div class="analytics-rank-value">Forecast</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-traffic-light"></i></span><div><strong>{{ number_format($prediction->peak_periods->count()) }} slow-period indicators</strong><small>Historical time blocks outside route-normal performance.</small></div><div class="analytics-rank-value">History</div></div>
            </div>
        </article>
    </section>

    <section class="analytics-card prescriptive-boundary-card">
        <div>
            <span class="section-kicker">Recommendation Boundary</span>
            <h3>Recommendations will support decisions, not automatically execute them.</h3>
            <p>Any future route, schedule, shuttle, PMS, maintenance, or restocking suggestion will remain explainable and require operator review before action.</p>
        </div>
        <a href="{{ route('analytics.recommendations') }}" class="recommendation-link">Open Recommendations <i class="fa-solid fa-arrow-right"></i></a>
    </section>
</section>
