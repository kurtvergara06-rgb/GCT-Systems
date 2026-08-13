@include('Admin.Analytics.sections.descriptive-fleet-kpis')
<section class="analytics-main-grid analytics-main-grid-balanced">@include('Admin.Analytics.sections.descriptive-chart') @include('Admin.Analytics.sections.descriptive-availability')</section>
<section class="analytics-list-grid">@include('Admin.Analytics.sections.descriptive-routes') @include('Admin.Analytics.sections.descriptive-buses')</section>
@if($domain==='all') @include('Admin.Analytics.sections.descriptive-cross-summary') @endif