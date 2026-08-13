@props(['items' => collect(), 'valueKey' => 'value', 'labelKey' => 'label', 'suffix' => '', 'partialLast' => false, 'emptyText' => 'No recorded values for the selected filters.'])

@php
    $rows = collect($items)->values();
    $max = max(1, (float) ($rows->max(fn ($row) => (float) data_get($row, $valueKey, 0)) ?? 0));
    $count = $rows->count();
    $points = $rows->map(function ($row, $index) use ($count, $max, $valueKey, $labelKey, $partialLast) {
        $x = $count > 1 ? 44 + (($index / ($count - 1)) * 632) : 360;
        $value = (float) data_get($row, $valueKey, 0);
        return [
            'x' => round($x, 1),
            'y' => round(190 - (($value / $max) * 142), 1),
            'value' => $value,
            'label' => (string) data_get($row, $labelKey, ''),
            'partial' => $partialLast && $index === $count - 1,
        ];
    });
    $completed = $points->reject(fn ($point) => $point['partial'])->values();
    $polyline = $completed->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
    $area = $completed->count() > 1 ? $polyline . ' ' . $completed->last()['x'] . ',190 ' . $completed->first()['x'] . ',190' : '';
@endphp

@if($rows->isEmpty())
    <div class="analytics-compact-empty"><i class="fa-regular fa-chart-bar"></i><span>{{ $emptyText }}</span></div>
@else
    <div class="analytics-line-chart" data-analytics-chart>
        <svg viewBox="0 0 720 224" preserveAspectRatio="none" role="img">
            @foreach([48, 83.5, 119, 154.5, 190] as $y)
                <line x1="44" y1="{{ $y }}" x2="676" y2="{{ $y }}" class="analytics-chart-grid-line" />
            @endforeach
            @if($area !== '')<polygon points="{{ $area }}" class="analytics-chart-area" />@endif
            @if($completed->count() > 1)<polyline points="{{ $polyline }}" class="analytics-chart-line" />@endif
            @foreach($points as $point)
                <g class="analytics-chart-point{{ $point['partial'] ? ' is-partial' : '' }}" data-chart-label="{{ $point['label'] }}" data-chart-value="{{ number_format($point['value'], 1) }}{{ $suffix }}">
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" />
                    <text x="{{ $point['x'] }}" y="{{ max(20, $point['y'] - 12) }}" text-anchor="middle" class="analytics-chart-value">{{ number_format($point['value'], $point['value'] == round($point['value']) ? 0 : 1) }}</text>
                    <text x="{{ $point['x'] }}" y="215" text-anchor="middle" class="analytics-chart-label">{{ $point['label'] }}{{ $point['partial'] ? '*' : '' }}</text>
                </g>
            @endforeach
        </svg>
        <div class="analytics-chart-tooltip" hidden></div>
    </div>
@endif
