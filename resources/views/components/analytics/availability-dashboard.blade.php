@props(['domain'])

@php
    $incident = $domain === 'incident';
    $metrics = $incident
        ? [
            ['Total Incidents', 'fa-clipboard-list'],
            ['Active Incidents', 'fa-triangle-exclamation'],
            ['Resolved Incidents', 'fa-circle-check'],
            ['Bus Breakdowns', 'fa-bus'],
        ]
        : [
            ['Scheduling Readiness', 'fa-calendar-check'],
            ['Resource Availability', 'fa-bus-simple'],
            ['Assignment Conflicts', 'fa-triangle-exclamation'],
            ['Recommendation Status', 'fa-list-check'],
        ];
    $panels = $incident
        ? [
            ['Incident Trend', 'fa-chart-line', 'Incident counts by reporting period will appear when incident records are connected.'],
            ['Incident Types & Causes', 'fa-chart-pie', 'Recorded type, severity, and cause breakdowns are not available in this Analytics view yet.'],
            ['Replacement Bus Tracking', 'fa-bus', 'Original and replacement bus comparisons require verified linked incident records.'],
            ['Recent Incident Records', 'fa-table-list', 'No incident record feed is connected to this page.'],
        ]
        : [
            ['Scheduling Overview', 'fa-calendar-days', 'A live dispatch readiness summary is not connected to this Analytics view yet.'],
            ['Driver & Bus Availability', 'fa-people-arrows', 'Verified resource availability will appear here when a reporting source is connected.'],
            ['Conflict Monitoring', 'fa-shuffle', 'No live scheduling conflict feed is connected to this page.'],
            ['Assignment Recommendations', 'fa-lightbulb', 'Recommendations will appear only when supported by recorded dispatch evidence.'],
        ];
@endphp

<div class="analytics-availability-dashboard">
    <section class="analytics-kpi-strip" aria-label="{{ $incident ? 'Incident' : 'Scheduling' }} summary">
        @foreach($metrics as [$label, $icon])
            <x-analytics.kpi :label="$label" value="—" description="Data source not connected" :icon="$icon" icon-variant="blue" />
        @endforeach
    </section>

    <div class="analytics-availability-intro" role="status">
        <span class="analytics-availability-intro__icon"><i class="fa-solid {{ $incident ? 'fa-triangle-exclamation' : 'fa-calendar-check' }}" aria-hidden="true"></i></span>
        <div>
            <span class="analytics-availability-intro__eyebrow">Data availability</span>
            <h2>{{ $incident ? 'Incident analytics data is not yet connected' : 'Live scheduling insights are not yet connected' }}</h2>
            <p>{{ $incident ? 'The current diagnostic dataset measures trip delays and idling. This page will show incident analysis only when verified incident records are available.' : 'The existing scheduling scenario is illustrative. This page will show operational recommendations only when verified dispatch records support them.' }}</p>
        </div>
    </div>

    <section class="analytics-availability-grid" aria-label="Planned analytics sections">
        @foreach($panels as [$title, $icon, $description])
            <article class="analytics-card analytics-availability-panel">
                <div class="analytics-availability-panel__heading">
                    <span><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
                    <h3>{{ $title }}</h3>
                </div>
                <div class="analytics-availability-panel__empty">
                    <i class="fa-regular fa-chart-bar" aria-hidden="true"></i>
                    <strong>No data available</strong>
                    <p>{{ $description }}</p>
                </div>
            </article>
        @endforeach
    </section>
</div>
