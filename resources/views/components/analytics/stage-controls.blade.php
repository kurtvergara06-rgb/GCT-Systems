@props([
    'url',
    'tabs',
    'active',
    'filterKey' => 'domain',
    'period',
    'periodOptions',
    'busOptions' => [],
    'selectedBus' => 'all',
    'showBus' => true,
    'dateLabel' => null,
    'periodLabel' => 'Period',
])

@php
    $currentFilters = request()->only(['period', 'bus', 'demo']);
    $selectedBusNumber = strtoupper(trim((string) $selectedBus));
@endphp

<div class="analytics-stage-controls">
    <nav class="analytics-stage-controls__tabs" aria-label="{{ ucfirst($filterKey === 'view' ? 'descriptive summary' : 'analytics domain') }} views">
        @foreach($tabs as $key => $tab)
            <a href="{{ $url . '?' . http_build_query(array_merge($currentFilters, [$filterKey => $key])) }}"
               class="{{ $active === $key ? 'is-current' : '' }}"
               @if($active === $key) aria-current="page" @endif>
                <i class="fa-solid {{ $tab[1] }}" aria-hidden="true"></i>
                <span>{{ $tab[0] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ $url }}" class="analytics-stage-controls__filters">
        <input type="hidden" name="{{ $filterKey }}" value="{{ $active }}">
        @if(request()->has('demo'))
            <input type="hidden" name="demo" value="{{ request('demo') }}">
        @endif

        <label class="analytics-stage-controls__field">
            <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ $periodLabel }}</span>
            <select name="period" onchange="this.form.requestSubmit()">
                @foreach($periodOptions as $value => $label)
                    <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if($showBus)
            <label class="analytics-stage-controls__field">
                <span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Bus</span>
                <select name="bus" onchange="this.form.requestSubmit()">
                    <option value="all" @selected($selectedBusNumber === 'ALL')>All Fleet Buses</option>
                    @foreach($busOptions as $bus)
                        @php($busNo = data_get($bus, 'bus_no') ?? (string) $bus)
                        <option value="{{ $busNo }}" @selected($selectedBusNumber === strtoupper((string) $busNo))>{{ $busNo }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if($dateLabel)
            <span class="analytics-stage-controls__date"><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $dateLabel }}</span>
        @endif
    </form>
</div>
