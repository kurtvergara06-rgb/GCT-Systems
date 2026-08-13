@props([
  'title',
  'description' => '',
  'chartId',
  'icon' => 'fa-chart-column',
  'iconColor' => 'blue',
  'height' => '310px',
  'tag' => null,
])

@php
  $displayDescription = $description;
  $displayTag = $tag;

  if ($chartId === 'fuelEfficiencyChart') {
    $displayDescription = '10 lowest-efficiency buses that need attention. Fleet average is shown as a reference.';
    $displayTag = 'Needs Attention';
  }
@endphp

<article class="fuel-chart-card">
  <div class="fuel-chart-header">
    <div class="fuel-chart-heading">
      <h2>{{ $title }}</h2>

      @if($displayDescription)
        <p>{{ $displayDescription }}</p>
      @endif
    </div>

    <div class="fuel-chart-header-actions">
      @if($displayTag)
        <span class="fuel-chart-tag">{{ $displayTag }}</span>
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

@if($chartId === 'fuelEfficiencyChart')
  <style>
    .fuel-page .fuel-analytics-grid {
      align-items: stretch;
      gap: 16px;
    }

    .fuel-page .fuel-chart-card {
      min-width: 0;
      overflow: hidden;
      border: 1px solid #dbe4f0;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 8px 24px rgba(6, 31, 61, 0.055);
    }

    .fuel-page .fuel-chart-header {
      min-height: 70px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eef3f8;
    }

    .fuel-page .fuel-chart-heading h2 {
      color: #061f3d;
      font-size: 16px;
      line-height: 1.25;
      font-weight: 800;
    }

    .fuel-page .fuel-chart-heading p {
      max-width: 520px;
      margin-top: 4px;
      color: #64748b;
      font-size: 11px;
      line-height: 1.45;
    }

    .fuel-page .fuel-chart-header-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .fuel-page .fuel-chart-tag {
      min-height: 30px;
      padding: 0 10px;
      border: 1px solid #d6e0ee;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #f8fbff;
      color: #163b6c;
      font-family: "Poppins", sans-serif;
      font-size: 10px;
      font-weight: 700;
      line-height: 1;
      white-space: nowrap;
    }

    .fuel-page .fuel-chart-container {
      height: 290px !important;
      min-height: 290px !important;
      padding: 8px 2px 0;
      overflow: hidden !important;
    }

    .fuel-page .fuel-chart-container canvas {
      max-width: 100%;
    }

    @media (max-width: 1100px) {
      .fuel-page .fuel-chart-container {
        height: 275px !important;
        min-height: 275px !important;
      }
    }

    @media (max-width: 900px) {
      .fuel-page .fuel-chart-tag {
        display: none;
      }
    }
  </style>
@endif