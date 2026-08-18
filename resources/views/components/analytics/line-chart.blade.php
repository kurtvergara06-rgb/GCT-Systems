@props(['items' => collect(), 'valueKey' => 'value', 'labelKey' => 'label', 'suffix' => '', 'partialLast' => false, 'emptyText' => 'No recorded values for the selected filters.'])

@php
    $rows = collect($items)->values();
    $max = max(1, (float) ($rows->max(fn ($row) => (float) data_get($row, $valueKey, 0)) ?? 0));
    $count = $rows->count();
    $points = $rows->map(function ($row, $index) use ($count, $max, $valueKey, $labelKey, $partialLast) {
        $x = $count > 1 ? 72 + (($index / ($count - 1)) * 576) : 360;
        $value = (float) data_get($row, $valueKey, 0);
        return [
            'x' => round($x, 1),
            'y' => round(184 - (($value / $max) * 132), 1),
            'value' => $value,
            'label' => (string) data_get($row, $labelKey, ''),
            'partial' => $partialLast && $index === $count - 1,
        ];
    });
    $completed = $points->reject(fn ($point) => $point['partial'])->values();
    $polyline = $completed->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
    $area = $completed->count() > 1 ? $polyline . ' ' . $completed->last()['x'] . ',184 ' . $completed->first()['x'] . ',184' : '';
    $gridRows = collect([0, 1, 2, 3, 4])->map(function ($index) use ($max) {
        $ratio = $index / 4;
        return [
            'y' => round(52 + (132 * $ratio), 1),
            'value' => $max * (1 - $ratio),
        ];
    });
@endphp

@if($rows->isEmpty())
    <div class="analytics-compact-empty"><i class="fa-regular fa-chart-bar"></i><span>{{ $emptyText }}</span></div>
@else
    <div class="analytics-line-chart" data-analytics-chart>
        <svg viewBox="0 0 720 224" preserveAspectRatio="none" role="img">
            @foreach($gridRows as $grid)
                <line x1="72" y1="{{ $grid['y'] }}" x2="648" y2="{{ $grid['y'] }}" class="analytics-chart-grid-line" stroke-dasharray="4 6" />
                <text x="60" y="{{ $grid['y'] + 3 }}" text-anchor="end" class="analytics-chart-y-label">{{ number_format($grid['value'], $grid['value'] >= 100 ? 0 : 1) }}{{ $suffix }}</text>
            @endforeach
            @if($area !== '')<polygon points="{{ $area }}" class="analytics-chart-area" />@endif
            @if($completed->count() > 1)<polyline points="{{ $polyline }}" class="analytics-chart-line" />@endif
            <line x1="72" y1="52" x2="72" y2="184" class="analytics-chart-crosshair" hidden />
            @foreach($points as $point)
                <g class="analytics-chart-point{{ $point['partial'] ? ' is-partial' : '' }}" data-chart-label="{{ $point['label'] }}" data-chart-value="{{ number_format($point['value'], 1) }}{{ $suffix }}" data-chart-x="{{ $point['x'] }}" data-chart-y="{{ $point['y'] }}" tabindex="0">
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" />
                    @if($count <= 4)
                        <text x="{{ $point['x'] }}" y="{{ max(24, $point['y'] - 12) }}" text-anchor="middle" class="analytics-chart-value">{{ number_format($point['value'], $point['value'] == round($point['value']) ? 0 : 1) }}</text>
                    @endif
                    <text x="{{ $point['x'] }}" y="214" text-anchor="middle" class="analytics-chart-label">{{ $point['label'] }}{{ $point['partial'] ? '*' : '' }}</text>
                </g>
            @endforeach
        </svg>
        <div class="analytics-chart-tooltip" hidden><strong></strong><span></span></div>
    </div>
@endif
