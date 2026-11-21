@php
    $m = $maintenanceAnalytics;
    $customRange = request()->filled('from') || request()->filled('to');
    $selectedPeriod = $customRange ? 'custom' : request()->query('period', 'last-30-days');
@endphp
<form method="GET" class="ma-filters ma-filters--compact" aria-label="Maintenance {{ $stage }} analytics filters">
        <div class="ma-compact-field ma-compact-period">
            <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            <span class="sr-only">Reporting period</span>
            <select name="period" aria-label="Reporting period" data-ma-period>
                @foreach(['last-30-days' => 'Last 30 Days', 'this-month' => 'This Month', 'last-3-months' => 'Last 3 Months', 'this-year' => 'This Year', 'custom' => 'Custom Range'] as $periodValue => $periodLabel)
                    <option value="{{ $periodValue === 'custom' ? '' : $periodValue }}" @selected($selectedPeriod === $periodValue)>{{ $periodLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="ma-compact-field ma-compact-bus">
            <i class="fa-solid fa-bus" aria-hidden="true"></i>
            <span class="sr-only">Bus filter</span>
            <select name="bus" aria-label="Filter by bus" data-ma-bus>
                <option value="all">All Buses</option>
                @foreach($m['busOptions'] as $number)
                    <option value="{{ $number }}" @selected($m['bus'] === $number)>{{ $number }}</option>
                @endforeach
            </select>
        </div>
        @if($stage === 'diagnostic')
            <div class="ma-compact-field ma-compact-extra">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                <select name="issue" aria-label="Filter by issue type" data-ma-auto>
                    @foreach(['all'=>'All Issue Types','repair'=>'Repair Delays','parts'=>'Parts-Related Delays','repeat'=>'Recurring Issues','workload'=>'Mechanic Assignment','fuel'=>'Fuel Consumption'] as $key=>$label)
                        <option value="{{ $key }}" @selected($m['issue'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @elseif($stage === 'prescriptive')
            <div class="ma-compact-field ma-compact-extra">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                <select name="category" aria-label="Filter by recommendation category" data-ma-auto>
                    @foreach(['all'=>'All Recommendations','repair'=>'Repair Prioritization','mechanic'=>'Mechanic Assignment','parts'=>'Parts Review','fuel'=>'Fuel Efficiency Review'] as $key=>$label)
                        <option value="{{ $key }}" @selected($m['category'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <a class="ma-button ma-reset-compact" href="{{ $stage === 'descriptive' ? route('analytics.descriptive') : route('analytics.stage', ['stage' => $stage]) }}" aria-label="Reset analytics filters">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset
        </a>
        <div class="ma-custom-range" data-ma-custom-range @if(!$customRange) hidden @endif>
            <label>From <input type="date" name="from" value="{{ $m['from'] }}" @if(!$customRange) disabled @endif required></label>
            <label>To <input type="date" name="to" value="{{ $m['to'] }}" @if(!$customRange) disabled @endif required></label>
            <button type="submit" class="ma-button ma-primary">Apply Dates</button>
        </div>
</form>
