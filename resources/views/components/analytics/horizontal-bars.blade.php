@props(['items' => collect(), 'valueKey' => 'value', 'labelKey' => 'label', 'displayKey' => null, 'metaKey' => null, 'emptyText' => 'No matching records.'])

@php
    $rows = collect($items)->values();
    $max = max(1, (float) ($rows->max(fn ($row) => (float) data_get($row, $valueKey, 0)) ?? 0));
@endphp

@if($rows->isEmpty())
    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>{{ $emptyText }}</span></div>
@else
    <div class="analytics-horizontal-bars" data-analytics-bars>
        @foreach($rows as $row)
            @php
                $value = (float) data_get($row, $valueKey, 0);
                $width = ($value / $max) * 100;
                $display = $displayKey ? data_get($row, $displayKey) : number_format($value, 1);
            @endphp
            <div class="analytics-horizontal-row">
                <div class="analytics-horizontal-copy">
                    <strong>{{ data_get($row, $labelKey, 'Unknown') }}</strong>
                    @if($metaKey && filled(data_get($row, $metaKey)))
                        <span>{{ data_get($row, $metaKey) }}</span>
                    @endif
                </div>
                <div class="analytics-horizontal-value">{{ $display }}</div>
                <div class="analytics-horizontal-track"><span data-width="{{ round($width, 2) }}" style="--bar-width: {{ round($width, 2) }}%"></span></div>
            </div>
        @endforeach
    </div>
@endif
