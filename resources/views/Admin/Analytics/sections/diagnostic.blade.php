@php
$diagnostics = $fleet['diagnostics'] ?? null;
@endphp
@if($domain !== 'fuel' && $diagnostics)
    @include('Admin.Analytics.FleetTrip.sections.diagnostic')
@else
    <section class="analytics-domain-section"><div class="analytics-domain-heading"><div><span>Fuel</span><h2>Fuel indicators</h2></div></div><p class="ranking-empty">{{ collect($fuel['reviewUnits'] ?? [])->count() }} fuel units currently have measurable review indicators.</p></section>
@endif
