const SVG_NS = 'http://www.w3.org/2000/svg';

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const buildSmoothPath = (points, tension = 0.16) => {
    if (points.length < 2) {
        return '';
    }

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let index = 0; index < points.length - 1; index += 1) {
        const previous = points[index - 1] || points[index];
        const current = points[index];
        const next = points[index + 1];
        const following = points[index + 2] || next;

        const minY = Math.min(current.y, next.y);
        const maxY = Math.max(current.y, next.y);

        const control1 = {
            x: current.x + ((next.x - previous.x) * tension),
            y: clamp(current.y + ((next.y - previous.y) * tension), minY, maxY),
        };

        const control2 = {
            x: next.x - ((following.x - current.x) * tension),
            y: clamp(next.y - ((following.y - current.y) * tension), minY, maxY),
        };

        d += ` C ${control1.x.toFixed(2)} ${control1.y.toFixed(2)}, ${control2.x.toFixed(2)} ${control2.y.toFixed(2)}, ${next.x} ${next.y}`;
    }

    return d;
};

const ensureSmoothLineStyles = () => {
    if (document.getElementById('analyticsSmoothLineStyles')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'analyticsSmoothLineStyles';
    style.textContent = `
        .fleet-trip-page .reference-line-chart.is-mui-smooth .reference-chart-smooth-line {
            fill: none;
            stroke: #2563eb;
            stroke-width: 2.65;
            stroke-linecap: round;
            stroke-linejoin: round;
            vector-effect: non-scaling-stroke;
            filter: none;
            animation: none !important;
            transition: stroke-width .24s ease, opacity .24s ease;
        }

        .fleet-trip-page .reference-line-chart.is-mui-smooth .reference-chart-dot:not(.is-active) {
            r: 4;
        }

        .fleet-trip-page .reference-line-chart.is-mui-smooth .reference-chart-grid {
            opacity: .62;
        }

        .fleet-trip-page .reference-line-chart.is-mui-smooth .reference-chart-crosshair {
            stroke-dasharray: 3 4;
            opacity: .48;
        }

        @media (prefers-reduced-motion: reduce) {
            .fleet-trip-page .reference-line-chart.is-mui-smooth .reference-chart-smooth-line {
                transition: none;
            }
        }
    `;

    document.head.appendChild(style);
};

const animatePathDraw = (path) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    requestAnimationFrame(() => {
        const length = path.getTotalLength();
        path.style.strokeDasharray = `${length}`;
        path.style.strokeDashoffset = `${length}`;
        path.getBoundingClientRect();
        path.style.transition = 'stroke-dashoffset 1.25s cubic-bezier(.22,.61,.36,1)';
        path.style.strokeDashoffset = '0';

        window.setTimeout(() => {
            path.style.strokeDasharray = '';
            path.style.strokeDashoffset = '';
            path.style.transition = '';
        }, 1350);
    });
};

const enhanceReferenceChart = (chart) => {
    if (chart.dataset.muiSmoothBound === 'true') {
        return;
    }

    const svg = chart.querySelector('svg');
    const originalLine = svg?.querySelector('.reference-chart-line');
    const dots = Array.from(chart.querySelectorAll('.reference-chart-dot'));

    if (!svg || !originalLine || dots.length < 2) {
        return;
    }

    const completedPoints = dots
        .filter((dot) => !dot.classList.contains('is-partial'))
        .map((dot) => ({
            x: Number(dot.getAttribute('cx') || 0),
            y: Number(dot.getAttribute('cy') || 0),
        }));

    if (completedPoints.length < 2) {
        return;
    }

    const pathData = buildSmoothPath(completedPoints);
    if (!pathData) {
        return;
    }

    ensureSmoothLineStyles();

    const smoothLine = document.createElementNS(SVG_NS, 'path');
    smoothLine.setAttribute('d', pathData);
    smoothLine.setAttribute('class', 'reference-chart-line reference-chart-smooth-line');
    smoothLine.setAttribute('aria-hidden', 'true');

    originalLine.replaceWith(smoothLine);
    chart.classList.add('is-mui-smooth');
    chart.dataset.muiSmoothBound = 'true';

    animatePathDraw(smoothLine);
};

const initializeSmoothAnalyticsLines = () => {
    document
        .querySelectorAll('.descriptive-analytics-page .reference-line-chart')
        .forEach(enhanceReferenceChart);
};

document.addEventListener('DOMContentLoaded', initializeSmoothAnalyticsLines);
document.addEventListener('ajax:content-updated', initializeSmoothAnalyticsLines);
