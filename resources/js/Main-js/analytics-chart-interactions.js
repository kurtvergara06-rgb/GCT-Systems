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

const ensureReferenceChartStyles = () => {
    if (document.getElementById('referenceChartNuxtStyles')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'referenceChartNuxtStyles';
    style.textContent = `
        .fleet-trip-page .reference-line-chart.is-nuxt-inspired {
            position: relative;
            cursor: default;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-grid {
            stroke: #dce5f0;
            stroke-width: 1;
            stroke-dasharray: 5 6;
            opacity: .9;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-area,
        .fleet-trip-page .reference-line-chart.is-nuxt-inspired polygon {
            opacity: 0 !important;
            pointer-events: none;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-line,
        .fleet-trip-page .reference-line-chart.is-nuxt-inspired:hover .reference-chart-line {
            stroke-width: 2.6 !important;
            filter: none !important;
            animation-iteration-count: 1 !important;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-value {
            opacity: 0 !important;
            pointer-events: none;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-dot {
            fill: #ffffff;
            stroke: #2563eb;
            stroke-width: 2.5;
            transform-box: fill-box;
            transform-origin: center;
            transition: transform .16s ease, fill .16s ease, stroke-width .16s ease, opacity .16s ease;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired:hover .reference-chart-dot {
            transform: none;
            filter: none;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-dot.is-active {
            fill: #2563eb;
            stroke: #ffffff;
            stroke-width: 3;
            transform: scale(1.45) !important;
            filter: drop-shadow(0 2px 4px rgba(37, 99, 235, .26));
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-dot.is-partial {
            stroke: #94a3b8;
            stroke-dasharray: 2 2;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-label {
            fill: #64748b;
            font-size: 10.5px;
            font-weight: 650;
        }

        .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-crosshair {
            stroke: #64748b;
            stroke-width: 1;
            opacity: .72;
            pointer-events: none;
        }

        .reference-chart-hover-tooltip {
            position: absolute;
            z-index: 30;
            min-width: 154px;
            padding: 10px 12px;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 10px;
            background: rgba(15, 23, 42, .96);
            color: #f8fafc;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .2);
            pointer-events: none;
            transform: translate(-50%, calc(-100% - 14px));
            opacity: 0;
            visibility: hidden;
            transition: opacity .12s ease, visibility .12s ease;
        }

        .reference-chart-hover-tooltip.is-visible {
            opacity: 1;
            visibility: visible;
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
            .fleet-trip-page .reference-line-chart.is-nuxt-inspired .reference-chart-dot {
                transition: none;
            }
        }
    `;

    document.head.appendChild(style);
};

const bindReferenceLineChart = (chart) => {
    if (chart.dataset.nuxtChartBound === 'true') {
        return;
    }

    const svg = chart.querySelector('svg');
    const dots = Array.from(chart.querySelectorAll('.reference-chart-dot'));
    const labels = Array.from(chart.querySelectorAll('.reference-chart-label'));
    const values = Array.from(chart.querySelectorAll('.reference-chart-value'));

    if (!svg || !dots.length) {
        return;
    }

    chart.dataset.nuxtChartBound = 'true';
    chart.classList.add('is-nuxt-inspired');
    ensureReferenceChartStyles();

    const namespace = 'http://www.w3.org/2000/svg';
    const crosshair = document.createElementNS(namespace, 'line');
    crosshair.setAttribute('class', 'reference-chart-crosshair');
    crosshair.setAttribute('y1', '28');
    crosshair.setAttribute('y2', '194');
    crosshair.style.display = 'none';

    const line = svg.querySelector('.reference-chart-line');
    if (line) {
        svg.insertBefore(crosshair, line);
    } else {
        svg.appendChild(crosshair);
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'reference-chart-hover-tooltip';
    tooltip.setAttribute('role', 'status');
    tooltip.setAttribute('aria-live', 'polite');
    chart.appendChild(tooltip);

    const points = dots.map((dot, index) => ({
        dot,
        label: (labels[index]?.textContent || '').trim(),
        value: (values[index]?.textContent || '0').trim(),
        x: Number(dot.getAttribute('cx') || 0),
        y: Number(dot.getAttribute('cy') || 0),
    }));

    let activeIndex = -1;

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
        crosshair.setAttribute('x1', String(point.x));
        crosshair.setAttribute('x2', String(point.x));
        crosshair.style.display = '';

        const cleanLabel = point.label.replace(/\*$/, '');
        const partialText = point.label.endsWith('*') ? ' (partial)' : '';
        tooltip.innerHTML = `
            <strong>${cleanLabel}${partialText}</strong>
            <div class="reference-chart-tooltip-row">
                <span class="reference-chart-tooltip-series"><i class="reference-chart-tooltip-dot"></i>Trips Processed</span>
                <b>${point.value}</b>
            </div>
        `;

        const chartRect = chart.getBoundingClientRect();
        const svgRect = svg.getBoundingClientRect();
        const viewBox = svg.viewBox.baseVal;
        const scaleX = svgRect.width / (viewBox.width || 720);
        const scaleY = svgRect.height / (viewBox.height || 230);
        const left = (svgRect.left - chartRect.left) + (point.x * scaleX);
        const top = (svgRect.top - chartRect.top) + (point.y * scaleY);

        tooltip.style.left = `${Math.max(82, Math.min(chartRect.width - 82, left))}px`;
        tooltip.style.top = `${Math.max(68, top)}px`;
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
        showPoint(nearestPointIndex(event.clientX));
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

document.addEventListener('DOMContentLoaded', initializeAnalyticsChartInteractions);
document.addEventListener('ajax:content-updated', initializeAnalyticsChartInteractions);
