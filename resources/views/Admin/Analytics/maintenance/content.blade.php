@php
    $m = $maintenanceAnalytics;
    $stageNames = ['descriptive'=>'Descriptive Analytics','diagnostic'=>'Diagnostic Analytics','predictive'=>'Predictive Analytics','prescriptive'=>'Prescriptive Analytics'];
    $icons = ['descriptive'=>'fa-chart-column','diagnostic'=>'fa-magnifying-glass-chart','predictive'=>'fa-chart-line','prescriptive'=>'fa-lightbulb'];
    $chartData = match ($stage) {
        'descriptive' => ['monthly'=>$m['monthly'],'statuses'=>$m['statuses']],
        'diagnostic' => ['factors'=>$m['factors'],'comparison'=>$m['fuelComparison']],
        'predictive' => ['repair'=>$m['futureRepair'],'fuelHistory'=>$m['fuelMonthly'],'fuelForecast'=>$m['futureFuel']],
        default => [],
    };
@endphp
<section class="ma-content" data-stage="{{ $stage }}">

<p class="ma-loading" role="status" hidden>Loading verified Maintenance records…</p>
<p class="ma-notice ma-filter-error" role="alert" hidden></p>
@if($errors->any())<p class="ma-notice" role="alert">{{ $errors->first() }}</p>@endif

@if($stage === 'descriptive')
    <section class="ma-kpis" aria-label="Maintenance performance summary">
        @foreach($m['kpis'] as $kpi)
            <article class="ma-kpi" aria-label="{{ $kpi['label'] }}: {{ $kpi['value'] }}">
                <div class="ma-kpi-icon {{ $kpi['color'] }}" aria-hidden="true">
                    <i class="fa-solid {{ $kpi['icon'] }}"></i>
                </div>
                <div class="ma-kpi-content">
                    <span class="ma-kpi-label">{{ $kpi['label'] }}</span>
                    <strong class="ma-kpi-value">{{ $kpi['value'] }}</strong>
                </div>
                <div class="ma-kpi-comparison">
                    @if($kpi['delta'] !== null)
                        <span class="ma-kpi-change" data-direction="{{ $kpi['delta'] > 0 ? 'up' : ($kpi['delta'] < 0 ? 'down' : 'flat') }}">
                            @if($kpi['delta'] > 0)<i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                            @elseif($kpi['delta'] < 0)<i class="fa-solid fa-arrow-down" aria-hidden="true"></i>@endif
                            {{ $kpi['delta'] > 0 ? '+' : '' }}{{ $kpi['delta'] }}%
                        </span>
                        <small>vs. previous period</small>
                    @else
                        <small>No prior-period baseline</small>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
    <section class="ma-grid">
        <x-analytics.card title="Job Orders: Created vs. Completed" description="Created by creation date; completed by actual completion timestamp.">
            <div class="ma-chart"><canvas id="maJobsChart" role="img" aria-label="Job orders created and completed per month"></canvas></div>
        </x-analytics.card>
        <x-analytics.card title="Monthly Fuel Consumption" description="Reported liters within the selected period. Missing months have no reported consumption.">
            <div class="ma-chart"><canvas id="maFuelChart" role="img" aria-label="Reported monthly fuel consumption"></canvas></div>
        </x-analytics.card>
        <x-analytics.card title="Job Order Status Distribution" description="Distribution of recorded Job Orders by current status." class="ma-summary-panel">
            @if($m['createdCount'])
                <div class="ma-distribution">
                    <div class="ma-chart ma-donut">
                        <canvas id="maStatusChart" role="img" aria-label="Job Order status distribution"></canvas>
                        <div class="ma-donut-center" aria-hidden="true">
                            <strong>{{ number_format($m['createdCount']) }}</strong>
                            <span>Job Orders</span>
                        </div>
                    </div>
                    <ul class="ma-legend" aria-label="Job Order status breakdown">
                        @foreach($m['statuses'] as $status)
                            <li>
                                <span class="ma-status-name"><i class="ma-status-dot" style="--ma-status-color: {{ $status['color'] }}" aria-hidden="true"></i>{{ $status['label'] }}</span>
                                <strong>{{ $status['count'] }}</strong>
                                <small>{{ number_format($status['count'] / $m['createdCount'] * 100, 1) }}%</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="ma-empty">No verified Job Orders in this period.</div>
            @endif
        </x-analytics.card>
        <x-analytics.card title="Fuel Consumption by Bus" description="Top buses by reported fuel consumption in the selected period." class="ma-summary-panel">
            @php
                $highestFuelLiters = max(1, (float) collect($m['fuelByBus'])->max('liters'));
            @endphp
            <div class="ma-table-wrap">
                <table class="ma-table">
                    <thead><tr><th scope="col">Rank</th><th scope="col">Bus ID</th><th scope="col">Fuel Consumed</th><th scope="col">% of Total</th></tr></thead>
                    <tbody>
                        @forelse($m['fuelByBus'] as $row)
                            <tr>
                                <td><span class="ma-rank" data-rank="{{ $loop->iteration }}">{{ $loop->iteration }}</span></td>
                                <th scope="row">{{ $row['bus'] }}</th>
                                <td>
                                    <div class="ma-fuel-cell">
                                        <span>{{ number_format($row['liters'], 1) }} L</span>
                                        <div class="ma-meter" aria-hidden="true"><span style="width:{{ round($row['liters'] / $highestFuelLiters * 100, 1) }}%"></span></div>
                                    </div>
                                </td>
                                <td>{{ number_format($row['share'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ma-empty">No fuel reports in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-analytics.card>
    </section>
@elseif($stage === 'diagnostic')
    <section class="ma-grid">
        <x-analytics.card title="Repair Delay Factors" description="Recorded evidence and review signals; factors may overlap and do not prove causation."><div class="ma-chart"><canvas id="maFactorsChart" role="img" aria-label="Recorded repair delay factors"></canvas></div></x-analytics.card>
        <x-analytics.card title="Fuel Consumption Comparison" description="Actual liters versus a distance-adjusted baseline from at least three other-bus reports. Not a same-route comparison.">
            @if(collect($m['fuelComparison'])->contains(fn ($row) => $row['expected'] !== null))
                <div class="ma-chart">
                    <canvas id="maComparisonChart" role="img" aria-label="Actual and expected fuel consumption"></canvas>
                </div>
                <p class="ma-footnote">Comparisons without enough baseline records have no expected value; zero is not substituted.</p>
            @else
                <div class="ma-empty" role="status">
                    <div>
                        <strong>Insufficient Fuel Data</strong>
                        <p>At least three qualifying reports from other buses, with positive fuel consumption and distance, are needed for a comparison in the selected period.</p>
                    </div>
                </div>
            @endif
        </x-analytics.card>
    </section>
    <x-analytics.card title="Key Findings" description="Maintenance and fuel evidence requiring investigation.">
        @include('Admin.Analytics.maintenance.findings',['rows'=>$m['findings'],'recommendations'=>false])
        <p class="ma-notice"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Fuel differences are investigation signals, not proof of mechanical problems or fuel misuse. Verify records, route/load differences, and operating conditions.</p>
    </x-analytics.card>
@elseif($stage === 'predictive')


    <section class="ma-forecasts">
        @foreach([
            ['title'=>'Repair Completion Forecast','icon'=>'fa-clock','value'=>$m['repairEstimate'] === null ? '—' : $m['repairEstimate'].' hrs','ready'=>$m['repairEstimate'] !== null,'count'=>$m['historicalDurations']->count(),'description'=>'Mean of valid completed repair durations in the previous 90 days. At least 3 measurements required.'],
            ['title'=>'Maintenance Risk Review','icon'=>'fa-bus','value'=>$m['riskReady'] ? $m['riskBuses']->count().($m['riskBuses']->count() === 1 ? ' bus' : ' buses') : '—','ready'=>$m['riskReady'],'count'=>$m['riskSampleCount'],'description'=>'Review buses with at least 3 recorded Job Orders in 60 days. A frequency signal, not a breakdown probability.'],
            ['title'=>'Fuel Consumption Forecast','icon'=>'fa-gas-pump','value'=>$m['fuelEstimate'] === null ? '—' : number_format($m['fuelEstimate'],1).' L','ready'=>$m['fuelEstimate'] !== null,'count'=>collect($m['fuelMonthly'])->whereNotNull('value')->count(),'description'=>'Mean reported consumption across the previous 3 complete calendar months. Missing months prevent an estimate.']
        ] as $forecast)
            <x-analytics.card :title="$forecast['title']" :description="$forecast['description']" class="ma-forecast">
                <span class="ma-badge" data-level="{{ $forecast['ready'] ? 'Ready' : 'Medium' }}">{{ $forecast['ready'] ? 'Baseline available' : 'Insufficient historical data' }}</span>
                <div class="ma-forecast-value"><i class="fa-solid {{ $forecast['icon'] }}" aria-hidden="true"></i><strong>{{ $forecast['value'] }}</strong></div>
                <dl class="ma-model-meta"><div><dt>Prediction type</dt><dd>Rule-Based Estimate</dd></div><div><dt>Available evidence</dt><dd>{{ $forecast['count'] }} {{ $loop->last ? 'recorded months' : 'records' }}</dd></div><div><dt>ML model</dt><dd>Not connected for this output</dd></div></dl>
            </x-analytics.card>
        @endforeach
    </section>
    <section class="ma-grid">
        <x-analytics.card title="Repair Duration Forecast" description="Flat historical mean for the next four weeks; actual future repair durations are unknown.">
            @if($m['repairEstimate'] !== null)<div class="ma-chart"><canvas id="maRepairForecastChart" role="img" aria-label="Rule-based repair-duration estimate"></canvas></div>@else<div class="ma-empty">At least 3 valid completed repair durations are required. No forecast is fabricated.</div>@endif
        </x-analytics.card>
        <x-analytics.card title="Fuel Consumption Forecast" description="Previous complete months and a flat three-month historical-mean estimate. This is not an ML forecast.">
            <div class="ma-chart"><canvas id="maFuelForecastChart" role="img" aria-label="Historical fuel and rule-based estimate"></canvas></div>
            @if($m['fuelEstimate'] === null)<p class="ma-footnote">Forecast unavailable: all 3 previous complete months must contain actual fuel reports.</p>@endif
        </x-analytics.card>
    </section>
@else
    <section class="ma-kpis" aria-label="Recommendation categories">
        @foreach(['repair'=>['Repair Prioritization','fa-screwdriver-wrench','red'],'mechanic'=>['Mechanic Assignment','fa-users','green'],'parts'=>['Parts Procurement Review','fa-box','blue'],'fuel'=>['Fuel Efficiency Review','fa-gas-pump','purple']] as $key=>$definition)
            <x-ui.summary-card :label="$definition[0]" :value="$m['categoryCounts'][$key]" small="Review recommendations" :icon="$definition[1]" :color="$definition[2]" class="ma-kpi" :data-tone="$definition[2]" />
        @endforeach
    </section>
    <x-analytics.card title="Recommended Actions" :description="'For Maintenance Head review · '.$m['recommendations']->count().' matching recommendations'">
        @include('Admin.Analytics.maintenance.findings',['rows'=>$m['recommendations'],'recommendations'=>true])
        <p class="ma-notice"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Recommendations require human review and do not automatically approve Job Orders, assign mechanics, purchase parts, or change inventory.</p>
    </x-analytics.card>
@endif
<script type="application/json" id="maintenanceAnalyticsData">{!! json_encode(['stage'=>$stage] + $chartData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>

</section>
