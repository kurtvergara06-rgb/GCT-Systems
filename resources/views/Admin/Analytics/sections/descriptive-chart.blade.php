@include('Admin.Analytics.sections.descriptive-trend-data')
<article class="analytics-card analytics-reference-chart-card">
<x-analytics.card-header title="Processed Trip Activity" description="Trip-record volume across the selected analysis window."/>
<div class="reference-line-chart"><svg viewBox="0 0 720 230" preserveAspectRatio="none">
@foreach([44,81.5,119,156.5,194] as $y)<line x1="42" y1="{{ $y }}" x2="678" y2="{{ $y }}" class="reference-chart-grid"/>@endforeach
@if($points->count()>1)<polyline points="{{ $poly }}" class="reference-chart-line"/>@endif
@foreach($points as $p)<circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="5" class="reference-chart-dot"/><text x="{{ $p['x'] }}" y="{{ max(18,$p['y']-12) }}" text-anchor="middle" class="reference-chart-value">{{ $p['count'] }}</text><text x="{{ $p['x'] }}" y="218" text-anchor="middle" class="reference-chart-label">{{ $p['label'] }}</text>@endforeach
</svg></div><div class="reference-chart-legend"><span><i></i> Trips Processed</span></div></article>