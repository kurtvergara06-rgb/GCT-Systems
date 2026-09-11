@props([
  'label',
  'value',
  'description' => '',
  'icon' => 'fa-chart-line',
  'type' => 'average',
])

<article {{ $attributes->class(['fuel-insight-card', 'analytics-insight-card', $type]) }} data-ui-component="analytics-insight">
  <div class="fuel-insight-icon">
    <i class="fa-solid {{ $icon }}"></i>
  </div>

  <div class="fuel-insight-content">
    <span>{{ $label }}</span>

    <strong>{{ $value }}</strong>

    @if($description)
      <small>{{ $description }}</small>
    @endif
  </div>
</article>