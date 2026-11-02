@props(['domain'])

@php
    $incident = $domain === 'incident';
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
    <div class="analytics-availability-intro" role="status">
        <span class="analytics-availability-intro__icon"><i class="fa-solid {{ $incident ? 'fa-triangle-exclamation' : 'fa-calendar-check' }}" aria-hidden="true"></i></span>
        <div>
            <span class="analytics-availability-intro__eyebrow">Data availability</span>
            <h2>{{ $incident ? 'Incident analytics data is not yet connected' : 'Live scheduling insights are not yet connected' }}</h2>
            <p>{{ $incident ? 'The current diagnostic dataset measures trip delays and idling. Incident analysis requires a verified incident reporting feed connected to this page.' : 'The existing scheduling scenario is illustrative. Operational recommendations require a verified dispatch reporting feed connected to this page.' }}</p>
        </div>
    </div>

    <section class="analytics-availability-grid" aria-label="{{ $incident ? 'Incident' : 'Scheduling' }} reporting sections awaiting data">
        @foreach($panels as [$title, $icon, $description])
            <article class="analytics-card analytics-availability-panel">
                <div class="analytics-availability-panel__heading">
                    <span><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
                    <h3>{{ $title }}</h3>
                </div>
                <p class="analytics-availability-panel__description">{{ $description }}</p>
            </article>
        @endforeach
    </section>
</div>
