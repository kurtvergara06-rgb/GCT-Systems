@props([
  'label',
  'value',
  'description' => '',
  'icon' => 'fa-chart-line',
  'type' => 'average',
])

<article class="fuel-insight-card {{ $type }}">
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