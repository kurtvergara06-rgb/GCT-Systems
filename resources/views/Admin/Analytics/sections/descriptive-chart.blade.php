@php
    $trend = collect($fleet['trend'] ?? []);
    $max = max(1, (int) ($trend->max('count') ?? 0));
    $count = max(1, $trend->count());
    $points = $trend->map(function ($bucket, $index) use ($max, $count) {
        $x = $count > 1 ? 42 + (($index / ($count - 1)) * 636) : 360;
        $y = 194 - (((int) ($bucket->count ?? 0) / $max) * 150);

        return [
            'x' => round($x, 1),
            'y' => round($y, 1),
            'label' => $bucket->label ?? '—',
            'count' => (int) ($bucket->count ?? 0),
        ];
    });
    $polyline = $points->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
@endphp

<article class="analytics-card analytics-reference-chart-card">
    <x-analytics.card-header title="Processed Trip Activity" description="Trip-record volume across the selected analysis window." />
    <div class="reference-line-chart" role="img" aria-label="Processed trip activity trend">
        <svg viewBox="0 0 720 230" preserveAspectRatio="none">
            @foreach([44, 81.5, 119, 156.5, 194] as $y)
                <line x1="42" y1="{{ $y }}" x2="678" y2="{{ $y }}" class="reference-chart-grid" />
            @endforeach
            @if($points->count() > 1)
                <polyline points="{{ $polyline }}" class="reference-chart-line" />
            @endif
            @foreach($points as $point)
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" class="reference-chart-dot" />
                <text x="{{ $point['x'] }}" y="{{ max(18, $point['y'] - 12) }}" text-anchor="middle" class="reference-chart-value">{{ $point['count'] }}</text>
                <text x="{{ $point['x'] }}" y="218" text-anchor="middle" class="reference-chart-label">{{ $point['label'] }}</text>
            @endforeach
        </svg>
    </div>
    <div class="reference-chart-legend"><span><i></i> Trips Processed</span></div>
</article>
