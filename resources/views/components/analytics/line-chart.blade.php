@props(['items' => collect(), 'valueKey' => 'value', 'labelKey' => 'label', 'suffix' => '', 'partialLast' => false, 'emptyText' => 'No recorded values for the selected filters.'])

@php
    $rows = collect($items)->values();
    $rawMax = max(0, (float) ($rows->max(fn ($row) => (float) data_get($row, $valueKey, 0)) ?? 0));

    if ($rawMax <= 0) {
        $axisMax = 1.0;
    } else {
        $magnitude = pow(10, floor(log10($rawMax)));
        $normalized = $rawMax / $magnitude;
        $niceNormalized = match (true) {
            $normalized <= 1 => 1,
            $normalized <= 2 => 2,
            $normalized <= 2.5 => 2.5,
            $normalized <= 5 => 5,
            default => 10,
        };
        $axisMax = $niceNormalized * $magnitude;
        if ($axisMax <= $rawMax) {
            $axisMax += $magnitude;
        }
    }

    $count = $rows->count();
    $labelStep = $count > 14 ? (int) ceil($count / 7) : ($count > 8 ? 2 : 1);
    $plotLeft = 72.0;
    $plotRight = 648.0;
    $plotTop = 42.0;
    $plotBottom = 182.0;
    $plotWidth = $plotRight - $plotLeft;
    $plotHeight = $plotBottom - $plotTop;

    $points = $rows->map(function ($row, $index) use ($count, $axisMax, $valueKey, $labelKey, $partialLast, $plotLeft, $plotWidth, $plotTop, $plotHeight) {
        $x = $count > 1 ? $plotLeft + (($index / ($count - 1)) * $plotWidth) : $plotLeft + ($plotWidth / 2);
        $value = max(0, (float) data_get($row, $valueKey, 0));
        $y = $plotTop + $plotHeight - (($value / max(1, $axisMax)) * $plotHeight);

        return [
            'x' => round($x, 1),
            'y' => round($y, 1),
            'value' => $value,
            'label' => (string) data_get($row, $labelKey, ''),
            'partial' => $partialLast && $index === $count - 1,
        ];
    });

    $completed = $points->reject(fn ($point) => $point['partial'])->values();
    $smoothPath = '';

    if ($completed->isNotEmpty()) {
        $first = $completed->first();
        $smoothPath = 'M ' . $first['x'] . ' ' . $first['y'];

        for ($index = 0; $index < $completed->count() - 1; $index++) {
            $p0 = $completed->get(max(0, $index - 1));
            $p1 = $completed->get($index);
            $p2 = $completed->get($index + 1);
            $p3 = $completed->get(min($completed->count() - 1, $index + 2));

            $cp1x = $p1['x'] + (($p2['x'] - $p0['x']) / 6);
            $cp1y = $p1['y'] + (($p2['y'] - $p0['y']) / 6);
            $cp2x = $p2['x'] - (($p3['x'] - $p1['x']) / 6);
            $cp2y = $p2['y'] - (($p3['y'] - $p1['y']) / 6);

            $smoothPath .= sprintf(' C %.1f %.1f, %.1f %.1f, %.1f %.1f', $cp1x, $cp1y, $cp2x, $cp2y, $p2['x'], $p2['y']);
        }
    }

    $areaPath = $completed->count() > 1
        ? $smoothPath . ' L ' . $completed->last()['x'] . ' ' . $plotBottom . ' L ' . $completed->first()['x'] . ' ' . $plotBottom . ' Z'
        : '';

    $gridRows = collect([0, 1, 2, 3, 4])->map(function ($index) use ($axisMax, $plotTop, $plotHeight) {
        $ratio = $index / 4;
        return [
            'y' => round($plotTop + ($plotHeight * $ratio), 1),
            'value' => $axisMax * (1 - $ratio),
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
                <text x="58" y="{{ $grid['y'] + 3 }}" text-anchor="end" class="analytics-chart-label">{{ number_format($grid['value'], $grid['value'] >= 100 ? 0 : 1) }}{{ $suffix }}</text>
            @endforeach

            @if($areaPath !== '')
                <path d="{{ $areaPath }}" class="analytics-chart-area" />
            @endif

            @if($completed->count() > 1)
                <path d="{{ $smoothPath }}" class="analytics-chart-line" />
            @endif

            <line x1="72" y1="42" x2="72" y2="182" class="analytics-chart-crosshair" hidden />

            @foreach($points as $point)
                <g class="analytics-chart-point{{ $point['partial'] ? ' is-partial' : '' }}" data-chart-label="{{ $point['label'] }}" data-chart-value="{{ number_format($point['value'], 1) }}{{ $suffix }}" data-chart-x="{{ $point['x'] }}" data-chart-y="{{ $point['y'] }}" tabindex="0">
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4.5" />
                    @if(($loop->index % $labelStep) === 0 || $loop->last)
                        <text x="{{ $point['x'] }}" y="212" text-anchor="middle" class="analytics-chart-label">{{ $point['label'] }}{{ $point['partial'] ? '*' : '' }}</text>
                    @endif
                </g>
            @endforeach
        </svg>
        <div class="analytics-chart-tooltip" hidden><strong></strong><span></span></div>
        @if($suffix === ' L')
            <div class="trip-canvas-legend"><span><i></i> Fuel Used (L)</span></div>
        @endif
    </div>
@endif
