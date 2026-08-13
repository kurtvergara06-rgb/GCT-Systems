@php
$diagnostics = $fleet['diagnostics'] ?? null;
$prediction = $fleet['prediction'] ?? null;
@endphp
@if($domain !== 'fuel' && $diagnostics && $prediction)
@include('Admin.Analytics.FleetTrip.sections.prescriptive')
@else
<section class="analytics-domain-section"><div class="analytics-domain-heading"><div><span>Fuel</span><h2>Fuel advisory actions</h2></div></div><p class="ranking-empty">{{ collect($fuel['recommendations'] ?? [])->count() }} evidence-based fuel actions are available for operator review.</p></section>
@endif