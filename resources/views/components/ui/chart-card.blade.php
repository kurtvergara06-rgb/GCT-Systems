@props([
  'title',
  'description' => '',
  'chartId',
  'icon' => 'fa-chart-column',
  'iconColor' => 'blue',
  'height' => '310px',
  'tag' => null,
])

<article {{ $attributes->class(['fuel-chart-card']) }} data-ui-component="chart-card" data-chart-id="{{ $chartId }}">
  <div class="fuel-chart-header">
    <div class="fuel-chart-heading">
      <h2>{{ $title }}</h2>

      @if($description)
        <p>{{ $description }}</p>
      @endif
    </div>

    <div class="fuel-chart-header-actions">
      @if($tag)
        <span class="fuel-chart-tag">{{ $tag }}</span>
      @endif

      <div class="fuel-chart-icon {{ $iconColor }}">
        <i class="fa-solid {{ $icon }}"></i>
      </div>
    </div>
  </div>

  <div
    class="fuel-chart-container"
    style="height: {{ $height }};"
  >
    <canvas id="{{ $chartId }}"></canvas>
  </div>
</article>