@props([
  'label' => 'Label',
  'value' => '0',
  'small' => '',
  'icon' => 'fa-chart-line',
  'color' => 'blue',
  'href' => null,
])

@if($href)
  <a href="{{ $href }}" class="stat-card stat-card-link">
@else
  <div class="stat-card">
@endif

    <div class="stat-icon {{ $color }}">
      <i class="fa-solid {{ $icon }}"></i>
    </div>

    <div class="stat-card-content">
      <p>{{ $label }}</p>
      <h2>{{ $value }}</h2>

      @if($small)
        <small>{{ $small }}</small>
      @endif
    </div>

@if($href)
  </a>
@else
  </div>
@endif