@props([
  'id',
  'data' => [],
])

@php
  $chartData = $data;

  /*
   * Fuel Reports uses one analytics payload for two different charts.
   * Keep the efficiency chart focused on the 10 lowest positive KM/L
   * values (maintenance attention), while still including the true Top 10
   * distance buses so the distance/fuel comparison remains accurate.
   * Extra distance-only rows receive efficiency = 0 and are naturally
   * ignored by the efficiency chart's existing filter.
   */
  if ($id === 'fuelAnalyticsData') {
    $labels = collect($data['labels'] ?? [])->values();
    $efficiency = collect($data['efficiency'] ?? [])->values();
    $distance = collect($data['distance'] ?? [])->values();
    $fuel = collect($data['fuel'] ?? [])->values();

    $rows = $labels->map(function ($label, $index) use (
      $efficiency,
      $distance,
      $fuel
    ) {
      return [
        'label' => (string) $label,
        'efficiency' => (float) ($efficiency[$index] ?? 0),
        'distance' => (float) ($distance[$index] ?? 0),
        'fuel' => (float) ($fuel[$index] ?? 0),
      ];
    });

    $attentionRows = $rows
      ->filter(fn ($row) => $row['efficiency'] > 0)
      ->sortBy('efficiency')
      ->take(10)
      ->values();

    $distanceLeaders = $rows
      ->filter(fn ($row) => $row['distance'] > 0 || $row['fuel'] > 0)
      ->sortByDesc('distance')
      ->take(10)
      ->values();

    $attentionLabels = $attentionRows
      ->pluck('label')
      ->flip();

    $combinedRows = $attentionRows
      ->concat($distanceLeaders)
      ->unique('label')
      ->values();

    $chartData = [
      'labels' => $combinedRows
        ->pluck('label')
        ->values(),
      'efficiency' => $combinedRows
        ->map(fn ($row) => $attentionLabels->has($row['label'])
          ? round((float) $row['efficiency'], 2)
          : 0)
        ->values(),
      'distance' => $combinedRows
        ->pluck('distance')
        ->map(fn ($value) => round((float) $value, 2))
        ->values(),
      'fuel' => $combinedRows
        ->pluck('fuel')
        ->map(fn ($value) => round((float) $value, 2))
        ->values(),
      'fleetAverage' => round((float) ($data['fleetAverage'] ?? 0), 2),
    ];
  }
@endphp

<script
  type="application/json"
  id="{{ $id }}"
>
{!! json_encode(
  $chartData,
  JSON_HEX_TAG |
  JSON_HEX_AMP |
  JSON_HEX_APOS |
  JSON_HEX_QUOT
) !!}
</script>