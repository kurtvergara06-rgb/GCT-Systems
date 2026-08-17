const animateFuelCard = (card) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    card.querySelectorAll('.fuel-bar-fill').forEach((bar, index) => {
        bar.animate(
            [
                { transform: 'scaleY(0)', opacity: 0.35 },
                { transform: 'scaleY(1)', opacity: 1 },
            ],
            {
                duration: 720,
                delay: 80 + (index * 90),
                easing: 'cubic-bezier(.22,.61,.36,1)',
                fill: 'both',
            }
        );
    });

    card.querySelectorAll('.fuel-efficiency-ring, .fuel-readiness-ring').forEach((ring) => {
        ring.animate(
            [
                { transform: 'scale(.78) rotate(-18deg)', opacity: 0 },
                { transform: 'scale(1) rotate(0deg)', opacity: 1 },
            ],
            {
                duration: 760,
                easing: 'cubic-bezier(.22,.61,.36,1)',
                fill: 'both',
            }
        );
    });

    card.querySelectorAll('.fuel-review-row, .fuel-recommendation-card, .fuel-rule-list > div').forEach((row, index) => {
        row.animate(
            [
                { transform: 'translateY(10px)', opacity: 0 },
                { transform: 'translateY(0)', opacity: 1 },
            ],
            {
                duration: 380,
                delay: 80 + (index * 55),
                easing: 'ease-out',
                fill: 'both',
            }
        );
    });
};

const bindFuelHoverInteractions = (page) => {
    page.querySelectorAll('.fuel-bar-column').forEach((column) => {
        const bar = column.querySelector('.fuel-bar-fill');
        const value = column.querySelector('.fuel-bar-value');

        if (!bar || column.dataset.fuelHoverBound === 'true') {
            return;
        }

        column.dataset.fuelHoverBound = 'true';

        column.addEventListener('mouseenter', () => {
            bar.animate(
                [
                    { transform: 'translateY(0) scaleX(1)' },
                    { transform: 'translateY(-8px) scaleX(1.12)' },
                ],
                { duration: 180, easing: 'ease-out', fill: 'forwards' }
            );

            if (value) {
                value.animate(
                    [
                        { transform: 'scale(1)', opacity: 0.82 },
                        { transform: 'scale(1.12)', opacity: 1 },
                    ],
                    { duration: 180, easing: 'ease-out', fill: 'forwards' }
                );
            }
        });

        column.addEventListener('mouseleave', () => {
            bar.animate(
                [
                    { transform: 'translateY(-8px) scaleX(1.12)' },
                    { transform: 'translateY(0) scaleX(1)' },
                ],
                { duration: 180, easing: 'ease-out', fill: 'forwards' }
            );

            if (value) {
                value.animate(
                    [
                        { transform: 'scale(1.12)', opacity: 1 },
                        { transform: 'scale(1)', opacity: 0.82 },
                    ],
                    { duration: 180, easing: 'ease-out', fill: 'forwards' }
                );
            }
        });
    });

    page.querySelectorAll('.fuel-efficiency-ring, .fuel-readiness-ring').forEach((ring) => {
        if (ring.dataset.fuelHoverBound === 'true') {
            return;
        }

        ring.dataset.fuelHoverBound = 'true';
        ring.addEventListener('mouseenter', () => ring.animate(
            [{ transform: 'scale(1)' }, { transform: 'scale(1.055) rotate(2deg)' }],
            { duration: 190, easing: 'ease-out', fill: 'forwards' }
        ));
        ring.addEventListener('mouseleave', () => ring.animate(
            [{ transform: 'scale(1.055) rotate(2deg)' }, { transform: 'scale(1) rotate(0deg)' }],
            { duration: 190, easing: 'ease-out', fill: 'forwards' }
        ));
    });
};

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const buildSmoothPath = (points, tension = 0.16) => {
    if (points.length < 2) {
        return '';
    }

    let path = `M ${points[0].x} ${points[0].y}`;

    for (let index = 0; index < points.length - 1; index += 1) {
        const previous = points[index - 1] || points[index];
        const current = points[index];
        const next = points[index + 1];
        const following = points[index + 2] || next;
        const minY = Math.min(current.y, next.y);
        const maxY = Math.max(current.y, next.y);

        const control1X = current.x + ((next.x - previous.x) * tension);
        const control1Y = clamp(current.y + ((next.y - previous.y) * tension), minY, maxY);
        const control2X = next.x - ((following.x - current.x) * tension);
        const control2Y = clamp(next.y - ((following.y - current.y) * tension), minY, maxY);

        path += ` C ${control1X.toFixed(2)} ${control1Y.toFixed(2)}, ${control2X.toFixed(2)} ${control2Y.toFixed(2)}, ${next.x} ${next.y}`;
    }

    return path;
};

const ensureReferenceChartStyles = () => {
    if (document.getElementById('referenceChartStyles')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'referenceChartStyles';
    style.textContent = `
        .fleet-trip-page .reference-line-chart.is-enhanced {
            position: relative;
            cursor: default;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-grid {
            stroke: #dce5f0;
            stroke-width: 1;
            stroke-dasharray: 4 7;
            opacity: .68;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-area,
        .fleet-trip-page .reference-line-chart.is-enhanced polygon {
            display: none !important;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-line {
            fill: none;
            stroke: #2563eb;
            stroke-width: 2.7 !important;
            stroke-linecap: round;
            stroke-linejoin: round;
            vector-effect: non-scaling-stroke;
            filter: none !important;
            animation: none !important;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-value {
            opacity: 0 !important;
            pointer-events: none;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-dot {
            fill: #ffffff;
            stroke: #2563eb;
            stroke-width: 2.35;
            transform-box: fill-box;
            transform-origin: center;
            transition: transform .24s cubic-bezier(.22,.61,.36,1), fill .2s ease, stroke-width .2s ease, opacity .2s ease, filter .2s ease;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-dot.is-active {
            fill: #2563eb;
            stroke: #ffffff;
            stroke-width: 3;
            transform: scale(1.48) !important;
            filter: drop-shadow(0 3px 6px rgba(37, 99, 235, .25));
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-dot.is-partial {
            fill: #ffffff;
            stroke: #94a3b8;
            stroke-dasharray: 2 2;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-dot.is-partial.is-active {
            fill: #94a3b8;
            stroke: #ffffff;
            stroke-dasharray: none;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-label {
            fill: #64748b;
            font-size: 10.5px;
            font-weight: 650;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-y-label {
            fill: #94a3b8;
            font-size: 9px;
            font-weight: 600;
        }

        .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-crosshair {
            stroke: #64748b;
            stroke-width: 1;
            stroke-dasharray: 3 4;
            opacity: .48;
            pointer-events: none;
        }

        .reference-chart-hover-tooltip {
            position: absolute;
            z-index: 30;
            min-width: 156px;
            padding: 10px 12px;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 10px;
            background: rgba(15, 23, 42, .96);
            color: #f8fafc;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .2);
            pointer-events: none;
            transform: translate(-50%, calc(-100% - 14px)) translateY(5px) scale(.985);
            opacity: 0;
            visibility: hidden;
            transition: left .22s cubic-bezier(.22,.61,.36,1), top .22s cubic-bezier(.22,.61,.36,1), opacity .16s ease, transform .18s ease, visibility .16s ease;
            will-change: left, top, transform, opacity;
        }

        .reference-chart-hover-tooltip.is-visible {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, calc(-100% - 14px)) translateY(0) scale(1);
        }

        .reference-chart-hover-tooltip strong {
            display: block;
            margin-bottom: 7px;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
        }

        .reference-chart-tooltip-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            color: #cbd5e1;
            font-size: 10px;
        }

        .reference-chart-tooltip-series {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }

        .reference-chart-tooltip-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .2);
        }

        .reference-chart-tooltip-row b {
            color: #ffffff;
            font-size: 11px;
        }

        @media (prefers-reduced-motion: reduce) {
            .reference-chart-hover-tooltip,
            .fleet-trip-page .reference-line-chart.is-enhanced .reference-chart-dot {
                transition: none;
            }
        }
    `;

    document.head.appendChild(style);
};

const animateReferencePath = (path) => {
    if (!path || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const length = path.getTotalLength();
    path.style.strokeDasharray = `${length}`;
    path.style.strokeDashoffset = `${length}`;

    requestAnimationFrame(() => {
        path.style.transition = 'stroke-dashoffset 1.1s cubic-bezier(.22,.61,.36,1)';
        path.style.strokeDashoffset = '0';

        window.setTimeout(() => {
            path.style.strokeDasharray = '';
            path.style.strokeDashoffset = '';
            path.style.transition = '';
        }, 1200);
    });
};

const bindReferenceLineChart = (chart) => {
    if (chart.dataset.analyticsChartBound === 'true') {
        return;
    }

    const svg = chart.querySelector('svg');
    const dots = Array.from(chart.querySelectorAll('.reference-chart-dot'));
    const labels = Array.from(chart.querySelectorAll('.reference-chart-label'));
    const values = Array.from(chart.querySelectorAll('.reference-chart-value'));
    const originalLine = svg?.querySelector('.reference-chart-line');

    if (!svg || !dots.length || !originalLine) {
        return;
    }

    const namespace = 'http://www.w3.org/2000/svg';
    const points = dots.map((dot, index) => ({
        dot,
        label: (labels[index]?.textContent || '').trim(),
        value: (values[index]?.textContent || '0').trim(),
        numericValue: Number((values[index]?.textContent || '0').trim()) || 0,
        partial: dot.classList.contains('is-partial'),
        x: Number(dot.getAttribute('cx') || 0),
        y: Number(dot.getAttribute('cy') || 0),
    }));

    const completedPoints = points.filter((point) => !point.partial);
    const smoothPathData = buildSmoothPath(completedPoints);

    if (smoothPathData) {
        const smoothPath = document.createElementNS(namespace, 'path');
        smoothPath.setAttribute('d', smoothPathData);
        smoothPath.setAttribute('class', 'reference-chart-line');
        smoothPath.setAttribute('aria-hidden', 'true');
        originalLine.replaceWith(smoothPath);
        animateReferencePath(smoothPath);
    }

    chart.dataset.analyticsChartBound = 'true';
    chart.classList.add('is-enhanced');
    ensureReferenceChartStyles();

    const gridY = [44, 81.5, 119, 156.5, 194];
    const maxValue = Math.max(1, ...points.map((point) => point.numericValue));

    gridY.forEach((y, index) => {
        const axisLabel = document.createElementNS(namespace, 'text');
        const ratio = 1 - (index / (gridY.length - 1));
        axisLabel.setAttribute('x', '32');
        axisLabel.setAttribute('y', String(y + 3));
        axisLabel.setAttribute('text-anchor', 'end');
        axisLabel.setAttribute('class', 'reference-chart-y-label');
        axisLabel.textContent = String(Math.round(maxValue * ratio));
        svg.insertBefore(axisLabel, svg.firstChild);
    });

    const crosshair = document.createElementNS(namespace, 'line');
    crosshair.setAttribute('class', 'reference-chart-crosshair');
    crosshair.setAttribute('x1', String(points[0].x));
    crosshair.setAttribute('x2', String(points[0].x));
    crosshair.setAttribute('y1', '34');
    crosshair.setAttribute('y2', '194');
    crosshair.style.display = 'none';

    const renderedLine = svg.querySelector('.reference-chart-line');
    if (renderedLine) {
        svg.insertBefore(crosshair, renderedLine);
    } else {
        svg.appendChild(crosshair);
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'reference-chart-hover-tooltip';
    tooltip.setAttribute('role', 'status');
    tooltip.setAttribute('aria-live', 'polite');

    const tooltipTitle = document.createElement('strong');
    const tooltipRow = document.createElement('div');
    tooltipRow.className = 'reference-chart-tooltip-row';
    const tooltipSeries = document.createElement('span');
    tooltipSeries.className = 'reference-chart-tooltip-series';
    const tooltipDot = document.createElement('i');
    tooltipDot.className = 'reference-chart-tooltip-dot';
    const tooltipSeriesText = document.createTextNode('Trips Processed');
    const tooltipValue = document.createElement('b');

    tooltipSeries.append(tooltipDot, tooltipSeriesText);
    tooltipRow.append(tooltipSeries, tooltipValue);
    tooltip.append(tooltipTitle, tooltipRow);
    chart.appendChild(tooltip);

    dots.forEach((dot, index) => {
        dot.style.animationDelay = `${0.38 + (index * 0.06)}s`;
        if (labels[index]) {
            labels[index].style.animationDelay = `${0.48 + (index * 0.05)}s`;
        }
    });

    let activeIndex = -1;
    let currentCrosshairX = points[0].x;
    let targetCrosshairX = points[0].x;
    let crosshairFrame = null;

    const animateCrosshair = () => {
        const delta = targetCrosshairX - currentCrosshairX;
        currentCrosshairX += delta * 0.22;

        if (Math.abs(delta) < 0.08) {
            currentCrosshairX = targetCrosshairX;
        }

        crosshair.setAttribute('x1', currentCrosshairX.toFixed(2));
        crosshair.setAttribute('x2', currentCrosshairX.toFixed(2));

        if (currentCrosshairX !== targetCrosshairX) {
            crosshairFrame = requestAnimationFrame(animateCrosshair);
        } else {
            crosshairFrame = null;
        }
    };

    const moveCrosshair = (x) => {
        targetCrosshairX = x;
        if (!crosshairFrame) {
            crosshairFrame = requestAnimationFrame(animateCrosshair);
        }
    };

    const hideActivePoint = () => {
        if (activeIndex >= 0) {
            points[activeIndex]?.dot.classList.remove('is-active');
        }
        activeIndex = -1;
        crosshair.style.display = 'none';
        tooltip.classList.remove('is-visible');
    };

    const showPoint = (index) => {
        const point = points[index];
        if (!point) {
            return;
        }

        if (activeIndex >= 0 && activeIndex !== index) {
            points[activeIndex]?.dot.classList.remove('is-active');
        }

        activeIndex = index;
        point.dot.classList.add('is-active');
        crosshair.style.display = '';
        moveCrosshair(point.x);

        const cleanLabel = point.label.replace(/\*$/, '');
        const partialText = point.label.endsWith('*') ? ' (partial)' : '';
        tooltipTitle.textContent = `${cleanLabel}${partialText}`;
        tooltipValue.textContent = point.value;

        const chartRect = chart.getBoundingClientRect();
        const svgRect = svg.getBoundingClientRect();
        const viewBox = svg.viewBox.baseVal;
        const scaleX = svgRect.width / (viewBox.width || 720);
        const scaleY = svgRect.height / (viewBox.height || 230);
        const left = (svgRect.left - chartRect.left) + (point.x * scaleX);
        const top = (svgRect.top - chartRect.top) + (point.y * scaleY);

        tooltip.style.left = `${Math.max(84, Math.min(chartRect.width - 84, left))}px`;
        tooltip.style.top = `${Math.max(70, top)}px`;
        tooltip.classList.add('is-visible');
    };

    const nearestPointIndex = (clientX) => {
        const svgRect = svg.getBoundingClientRect();
        const viewBox = svg.viewBox.baseVal;
        const localX = ((clientX - svgRect.left) / Math.max(1, svgRect.width)) * (viewBox.width || 720);

        return points.reduce((closest, point, index) => {
            const distance = Math.abs(point.x - localX);
            return distance < closest.distance ? { index, distance } : closest;
        }, { index: 0, distance: Number.POSITIVE_INFINITY }).index;
    };

    svg.addEventListener('pointermove', (event) => {
        const nextIndex = nearestPointIndex(event.clientX);
        if (nextIndex !== activeIndex) {
            showPoint(nextIndex);
        }
    });

    svg.addEventListener('pointerleave', hideActivePoint);

    dots.forEach((dot, index) => {
        dot.setAttribute('tabindex', '0');
        dot.setAttribute('role', 'img');
        dot.setAttribute('aria-label', `${points[index].label}: ${points[index].value} trips processed`);
        dot.addEventListener('focus', () => showPoint(index));
        dot.addEventListener('blur', hideActivePoint);
    });
};

const initializeAnalyticsPage = (page) => {
    if (page.dataset.analyticsMotionInitialized === 'true') {
        return;
    }

    page.dataset.analyticsMotionInitialized = 'true';
    page.classList.add('analytics-motion-ready');

    const cards = Array.from(page.querySelectorAll('.analytics-card'));
    const isFuelPage = page.classList.contains('fuel-analytics-page');

    if (isFuelPage) {
        bindFuelHoverInteractions(page);
    }

    if (!cards.length) {
        return;
    }

    const revealCard = (card) => {
        if (card.classList.contains('is-chart-visible')) {
            return;
        }

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                card.classList.add('is-chart-visible');
                if (isFuelPage) {
                    animateFuelCard(card);
                }
            });
        });
    };

    if (!('IntersectionObserver' in window)) {
        cards.forEach(revealCard);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            revealCard(entry.target);
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.18,
        rootMargin: '0px 0px -8% 0px',
    });

    cards.forEach((card) => observer.observe(card));
};

const initializeAnalyticsChartInteractions = () => {
    document
        .querySelectorAll('.fleet-trip-page, .fuel-analytics-page')
        .forEach(initializeAnalyticsPage);

    document
        .querySelectorAll('.reference-line-chart')
        .forEach(bindReferenceLineChart);
};

initializeAnalyticsChartInteractions();
document.addEventListener('DOMContentLoaded', initializeAnalyticsChartInteractions);
document.addEventListener('ajax:content-updated', initializeAnalyticsChartInteractions);
