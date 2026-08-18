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

const bindSharedLineChart = (chart) => {
    if (!chart || chart.dataset.lineHoverBound === 'true') {
        return;
    }

    const svg = chart.querySelector('svg');
    const points = Array.from(chart.querySelectorAll('.analytics-chart-point'));
    const crosshair = chart.querySelector('.analytics-chart-crosshair');
    const tooltip = chart.querySelector('.analytics-chart-tooltip');
    const tooltipTitle = tooltip?.querySelector('strong');
    const tooltipValue = tooltip?.querySelector('span');

    if (!svg || !points.length || !tooltip) {
        return;
    }

    chart.dataset.lineHoverBound = 'true';

    const viewBox = () => {
        const base = svg.viewBox?.baseVal;
        return {
            width: base?.width || 720,
            height: base?.height || 224,
        };
    };

    const showPoint = (point) => {
        const x = Number(point.dataset.chartX || 0);
        const y = Number(point.dataset.chartY || 0);
        const svgRect = svg.getBoundingClientRect();
        const box = viewBox();
        const scaleX = svgRect.width / Math.max(1, box.width);
        const scaleY = svgRect.height / Math.max(1, box.height);
        const localX = x * scaleX;
        const localY = y * scaleY;

        points.forEach((item) => item.classList.toggle('is-active', item === point));

        if (crosshair) {
            crosshair.hidden = false;
            crosshair.setAttribute('x1', String(x));
            crosshair.setAttribute('x2', String(x));
        }

        if (tooltipTitle) tooltipTitle.textContent = point.dataset.chartLabel || 'Recorded value';
        if (tooltipValue) tooltipValue.textContent = point.dataset.chartValue || '';

        const tooltipHalfWidth = Math.max(70, tooltip.offsetWidth / 2);
        tooltip.style.left = `${Math.max(tooltipHalfWidth + 6, Math.min(chart.clientWidth - tooltipHalfWidth - 6, localX))}px`;
        tooltip.style.top = `${Math.max(18, localY - 8)}px`;
        tooltip.hidden = false;
    };

    const clearPoint = () => {
        points.forEach((point) => point.classList.remove('is-active'));
        if (crosshair) crosshair.hidden = true;
        tooltip.hidden = true;
    };

    points.forEach((point) => {
        point.addEventListener('pointerenter', () => showPoint(point));
        point.addEventListener('focus', () => showPoint(point));
        point.addEventListener('pointerleave', clearPoint);
        point.addEventListener('blur', clearPoint);
    });

    svg.addEventListener('pointermove', (event) => {
        const rect = svg.getBoundingClientRect();
        const box = viewBox();
        const viewX = ((event.clientX - rect.left) / Math.max(1, rect.width)) * box.width;
        const nearest = points.reduce((best, point) => {
            const distance = Math.abs(Number(point.dataset.chartX || 0) - viewX);
            return distance < best.distance ? { point, distance } : best;
        }, { point: points[0], distance: Number.POSITIVE_INFINITY }).point;
        showPoint(nearest);
    });

    svg.addEventListener('pointerleave', clearPoint);
};

const renderFuelEfficiencyDistribution = (page) => {
    const root = page.querySelector('.fuel-efficiency-distribution');
    const table = page.querySelector('[data-fuel-details-table]');
    if (!root || !table || root.dataset.referenceDistributionReady === 'true') return;

    const efficiencyCells = Array.from(table.querySelectorAll('tbody tr')).map((row) => {
        const cell = row.querySelector('td:nth-child(5)');
        const value = Number.parseFloat(cell?.textContent || '');
        return Number.isFinite(value) ? value : 0;
    });
    if (!efficiencyCells.length) return;

    const bands = [
        { key: 'high', label: 'High', range: '(> 5.0 km/L)', color: '#16a34a', match: (value) => value > 5 },
        { key: 'good', label: 'Good', range: '(3.0 – 5.0 km/L)', color: '#84cc16', match: (value) => value >= 3 && value <= 5 },
        { key: 'low', label: 'Low', range: '(1.0 – 3.0 km/L)', color: '#facc15', match: (value) => value >= 1 && value < 3 },
        { key: 'poor', label: 'Poor', range: '(< 1.0 km/L)', color: '#f97316', match: (value) => value > 0 && value < 1 },
        { key: 'no-data', label: 'No Data', range: '(0 km)', color: '#94a3b8', match: (value) => value <= 0 },
    ];

    const total = efficiencyCells.length;
    bands.forEach((band) => {
        band.count = efficiencyCells.filter(band.match).length;
        band.percentage = (band.count / total) * 100;
    });

    const bar = root.querySelector('.fuel-distribution-bar');
    const legend = root.querySelector('.fuel-distribution-legend');
    if (!bar || !legend) return;

    bar.innerHTML = bands
        .map((band) => `<span data-band="${band.key}" style="width:${band.percentage.toFixed(2)}%;background:${band.color}"></span>`)
        .join('');

    legend.style.gridTemplateColumns = 'repeat(5, minmax(0, 1fr))';
    legend.style.gap = '8px';
    legend.innerHTML = bands.map((band) => {
        const percentage = Number.isInteger(band.percentage) ? band.percentage.toFixed(0) : band.percentage.toFixed(1);
        return `<div><strong>${percentage}%</strong><span>${band.label}</span><small style="display:block;margin-top:4px;color:#718096;font-size:9.5px;font-weight:500;line-height:1.25;white-space:nowrap">${band.range}</small></div>`;
    }).join('');

    const card = root.closest('.analytics-card');
    const description = card?.querySelector('.analytics-card-header p');
    if (description) description.textContent = 'Bus-level efficiency bands from the selected fuel records';

    root.dataset.referenceDistributionReady = 'true';
};

const bindFuelTrendWindow = (page) => {
    const card = page.querySelector('.fuel-trend-card');
    const header = card?.querySelector('.analytics-card-header');
    if (!card || !header || card.dataset.trendWindowBound === 'true') return;

    card.dataset.trendWindowBound = 'true';
    const params = new URLSearchParams(window.location.search);
    const selected = ['7-days', '14-days', '30-days'].includes(params.get('fuel_trend'))
        ? params.get('fuel_trend')
        : '7-days';
    const labels = {
        '7-days': 'Last 7 Days',
        '14-days': 'Last 14 Days',
        '30-days': 'Last 30 Days',
    };

    const select = document.createElement('select');
    select.setAttribute('aria-label', 'Fuel trend period');
    select.style.cssText = 'height:36px;padding:0 30px 0 11px;border:1px solid #dce5ef;border-radius:8px;background:#fff;color:#203553;font:600 10.5px Poppins,sans-serif;cursor:pointer;';
    Object.entries(labels).forEach(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.selected = value === selected;
        select.appendChild(option);
    });

    header.appendChild(select);
    const description = header.querySelector('p');
    if (description) description.textContent = `${labels[selected]} · recorded fuel volume by day`;

    select.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('fuel_trend', select.value);
        url.searchParams.set('domain', 'fuel');
        window.location.assign(url.toString());
    });
};

const initializeAnalyticsPage = (page) => {
    renderFuelEfficiencyDistribution(page);
    bindFuelTrendWindow(page);

    if (page.dataset.analyticsMotionInitialized === 'true') {
        page.querySelectorAll('[data-analytics-chart]').forEach(bindSharedLineChart);
        return;
    }

    page.dataset.analyticsMotionInitialized = 'true';
    page.classList.add('analytics-motion-ready');

    const cards = Array.from(page.querySelectorAll('.analytics-card'));
    const isFuelPage = page.classList.contains('fuel-analytics-page');

    if (isFuelPage) {
        bindFuelHoverInteractions(page);
    }

    page.querySelectorAll('[data-analytics-chart]').forEach(bindSharedLineChart);

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
};

initializeAnalyticsChartInteractions();
document.addEventListener('DOMContentLoaded', initializeAnalyticsChartInteractions);
document.addEventListener('ajax:content-updated', initializeAnalyticsChartInteractions);
