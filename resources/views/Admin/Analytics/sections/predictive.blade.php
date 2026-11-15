@php
$prediction = $fleet['prediction'] ?? null;
@endphp
@if($domain !== 'fuel' && $prediction)
    @include('Admin.Analytics.FleetTrip.sections.predictive')
@else
    <section class="analytics-domain-section"><div class="analytics-domain-heading"><div><span>Fuel</span><h2>Short-term fuel demand</h2></div></div><p class="ranking-empty">@if(($fuel['forecast']??null)?->available) Projected 7-day fuel: {{ number_format($fuel['forecast']->projected_liters,1) }} L. @else More recorded days are required before a fuel forecast is shown. @endif</p></section>
@endif
