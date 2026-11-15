@props([
    'filename' => '',
    'type' => '',
    'width' => 34,
    'height' => 42,
])

@php
    $rawExt = '';
    if (!empty($filename)) {
        $rawExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    if (empty($rawExt) && !empty($type)) {
        $rawExt = strtolower(trim($type));
    }
    if (empty($rawExt)) {
        $rawExt = 'pdf';
    }

    // Determine format, color palette and label
    switch ($rawExt) {
        case 'pdf':
            $format = 'pdf';
            $badge = 'PDF';
            $colorBase = '#DC2626'; // Vibrant Red
            $colorFold = '#991B1B';
            break;

        case 'csv':
            $format = 'csv';
            $badge = 'CSV';
            $colorBase = '#059669'; // Emerald / Spreadsheet Green
            $colorFold = '#047857';
            break;

        case 'xls':
        case 'xlsx':
        case 'xlsm':
            $format = 'excel';
            $badge = $rawExt === 'xlsx' ? 'XLSX' : 'XLS';
            $colorBase = '#107C41'; // Microsoft Excel Green
            $colorFold = '#0B5A2F';
            break;

        case 'json':
            $format = 'json';
            $badge = 'JSON';
            $colorBase = '#EA580C'; // Code Amber / Orange
            $colorFold = '#C2410C';
            break;

        case 'txt':
        case 'text':
        case 'log':
            $format = 'txt';
            $badge = 'TXT';
            $colorBase = '#475569'; // Slate Blue-Gray
            $colorFold = '#334155';
            break;

        default:
            $format = 'generic';
            $badge = strtoupper(substr($rawExt, 0, 4));
            $colorBase = '#2563EB'; // System Blue
            $colorFold = '#1D4ED8';
            break;
    }
@endphp

<div {{ $attributes->merge(['class' => 'file-thumbnail file-thumbnail-' . $format]) }} title="{{ $badge }} document" aria-label="{{ $badge }} document">
    <svg
        viewBox="0 0 34 42"
        width="{{ $width }}"
        height="{{ $height }}"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        focusable="false"
    >
        <defs>
            <linearGradient id="docGrad_{{ $format }}" x1="0" y1="0" x2="34" y2="42" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="{{ $colorBase }}" />
                <stop offset="100%" stop-color="{{ $colorFold }}" stop-opacity="0.95" />
            </linearGradient>
            <filter id="foldShadow_{{ $format }}" x="21" y="0" width="13" height="14" filterUnits="userSpaceOnUse">
                <feDropShadow dx="-0.5" dy="1" stdDeviation="0.8" flood-color="#000000" flood-opacity="0.25" />
            </filter>
        </defs>

        <!-- Base Document Paper with Folded Top-Right Corner -->
        <path
            d="M 4 1 L 24 1 L 33 10 L 33 38 A 3 3 0 0 1 30 41 L 4 41 A 3 3 0 0 1 1 38 L 1 4 A 3 3 0 0 1 4 1 Z"
            fill="url(#docGrad_{{ $format }})"
        />

        <!-- Subtle Document Outline -->
        <path
            d="M 4 1 L 24 1 L 33 10 L 33 38 A 3 3 0 0 1 30 41 L 4 41 A 3 3 0 0 1 1 38 L 1 4 A 3 3 0 0 1 4 1 Z"
            stroke="#ffffff"
            stroke-width="0.75"
            stroke-opacity="0.2"
        />

        <!-- Fold Corner Shadow -->
        <path
            d="M 24 1 L 24 10 L 33 10 Z"
            fill="#000000"
            fill-opacity="0.18"
            filter="url(#foldShadow_{{ $format }})"
        />

        <!-- Folded Dog-Ear Flap -->
        <path
            d="M 24 1 L 24 8 A 2 2 0 0 0 26 10 L 33 10 Z"
            fill="{{ $colorFold }}"
        />
        <path
            d="M 24 1 L 24 8 A 2 2 0 0 0 26 10 L 33 10 Z"
            stroke="#ffffff"
            stroke-width="0.5"
            stroke-opacity="0.3"
        />

        @if($format === 'pdf')
            <!-- PDF Document Lines & Badge -->
            <text
                x="17"
                y="24"
                text-anchor="middle"
                fill="#ffffff"
                font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
                font-size="9.5"
                font-weight="900"
                letter-spacing="0.4"
            >PDF</text>
            <rect x="6" y="29" width="22" height="2" rx="1" fill="#ffffff" fill-opacity="0.55" />
            <rect x="6" y="33" width="14" height="2" rx="1" fill="#ffffff" fill-opacity="0.35" />

        @elseif($format === 'csv')
            <!-- CSV Spreadsheet Grid & Badge -->
            <rect x="6" y="7.5" width="14" height="8" rx="1" fill="#ffffff" fill-opacity="0.22" />
            <path d="M 6 11.5 H 20 M 13 7.5 V 15.5" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.85" />
            <text
                x="17"
                y="26.5"
                text-anchor="middle"
                fill="#ffffff"
                font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
                font-size="8.5"
                font-weight="900"
                letter-spacing="0.3"
            >CSV</text>
            <rect x="6" y="32.5" width="22" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.45" />

        @elseif($format === 'excel')
            <!-- Excel Spreadsheet Grid & Badge -->
            <rect x="6" y="7.5" width="15" height="8" rx="1" fill="#ffffff" fill-opacity="0.22" />
            <path d="M 6 11.5 H 21 M 11 7.5 V 15.5 M 16 7.5 V 15.5" stroke="#ffffff" stroke-width="0.75" stroke-opacity="0.85" />
            <text
                x="17"
                y="26.5"
                text-anchor="middle"
                fill="#ffffff"
                font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
                font-size="{{ strlen($badge) > 3 ? '7' : '8.5' }}"
                font-weight="900"
                letter-spacing="{{ strlen($badge) > 3 ? '0.1' : '0.3' }}"
            >{{ $badge }}</text>
            <rect x="6" y="32.5" width="22" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.45" />

        @elseif($format === 'json')
            <!-- JSON Code Braces & Syntax -->
            <text
                x="9"
                y="13.5"
                text-anchor="middle"
                fill="#ffffff"
                fill-opacity="0.85"
                font-family="ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
                font-size="7.5"
                font-weight="800"
            >{</text>
            <line x1="12" y1="11.5" x2="17" y2="11.5" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.55" />
            <text
                x="20.5"
                y="13.5"
                text-anchor="middle"
                fill="#ffffff"
                fill-opacity="0.85"
                font-family="ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
                font-size="7.5"
                font-weight="800"
            >}</text>
            <text
                x="17"
                y="25.5"
                text-anchor="middle"
                fill="#ffffff"
                font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
                font-size="8"
                font-weight="900"
                letter-spacing="0.3"
            >JSON</text>
            <rect x="6" y="30" width="11" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.5" />
            <rect x="6" y="33.5" width="17" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.35" />

        @elseif($format === 'txt')
            <!-- TXT Document Lines & Badge -->
            <rect x="6" y="8" width="11" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.65" />
            <rect x="6" y="12" width="17" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.45" />
            <rect x="6" y="16" width="13" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.35" />
            <text
                x="17"
                y="27.5"
                text-anchor="middle"
                fill="#ffffff"
                font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
                font-size="9"
                font-weight="900"
                letter-spacing="0.4"
            >TXT</text>
            <rect x="6" y="33.5" width="22" height="1.8" rx="0.9" fill="#ffffff" fill-opacity="0.4" />

        @else
            <!-- Generic Document -->
            <rect x="6" y="9" width="13" height="2" rx="1" fill="#ffffff" fill-opacity="0.5" />
            <text
                x="17"
                y="25.5"
                text-anchor="middle"
                fill="#ffffff"
                font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
                font-size="7.5"
                font-weight="900"
                letter-spacing="0.2"
            >{{ $badge }}</text>
            <rect x="6" y="31.5" width="22" height="2" rx="1" fill="#ffffff" fill-opacity="0.45" />
        @endif
    </svg>
</div>

