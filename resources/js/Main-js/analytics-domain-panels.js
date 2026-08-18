const parseTripPoints = (chart) => {
    try {
        return JSON.parse(chart.dataset.tripPoints || '[]').map((point) => ({
            label: String(point.label || ''),
            value: Number(point.value || 0),
            partial: Boolean(point.partial),
        }));
    } catch {
        return [];
    }
};

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const drawSmoothCurve = (context, points) => {
    if (points.length < 2) {
        return;
    }

    context.beginPath();
    context.moveTo(points[0].x, points[0].y);

    for (let index = 0; index < points.length - 1; index += 1) {
        const previous = points[index - 1] || points[index];
        const current = points[index];
        const next = points[index + 1];
        const following = points[index + 2] || next;
        const tension = 0.16;
        const minY = Math.min(current.y, next.y);
        const maxY = Math.max(current.y, next.y);
        const control1X = current.x + ((next.x - previous.x) * tension);
        const control1Y = clamp(current.y + ((next.y - previous.y) * tension), minY, maxY);
        const control2X = next.x - ((following.x - current.x) * tension);
        const control2Y = clamp(next.y - ((following.y - current.y) * tension), minY, maxY);

        context.bezierCurveTo(control1X, control1Y, control2X, control2Y, next.x, next.y);
    }
};

const bindTripCanvasChart = (chart) => {
    if (chart.dataset.tripCanvasBound === 'true') {
        return;
    }

    const canvas = chart.querySelector('.trip-canvas');
    const tooltip = chart.querySelector('.trip-canvas-tooltip');
    const tooltipTitle = tooltip?.querySelector('strong');
    const tooltipValue = tooltip?.querySelector('b');
    const data = parseTripPoints(chart);

    if (!canvas || !data.length) {
        return;
    }

    chart.dataset.tripCanvasBound = 'true';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let activeIndex = -1;
    let pointerX = null;
    let animationProgress = reducedMotion ? 1 : 0;
    let animationFrame = null;
    let resizeFrame = null;
    let geometry = [];
    let metrics = null;

    const resizeCanvas = () => {
        const rect = chart.getBoundingClientRect();
        const width = Math.max(320, rect.width);
        const height = Math.max(220, rect.height);
        const ratio = Math.max(1, window.devicePixelRatio || 1);

        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        const context = canvas.getContext('2d');
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        metrics = { context, width, height };
    };

    const render = () => {
        if (!metrics) {
            resizeCanvas();
        }

        const { context, width, height } = metrics;
        const padding = { left: 46, right: 26, top: 20, bottom: 38 };
        const plotWidth = Math.max(1, width - padding.left - padding.right);
        const plotHeight = Math.max(1, height - padding.top - padding.bottom);
        const maxValue = Math.max(1, ...data.map((point) => point.value));
        const stepX = data.length > 1 ? plotWidth / (data.length - 1) : 0;

        geometry = data.map((point, index) => ({
            ...point,
            x: padding.left + (stepX * index),
            y: padding.top + plotHeight - ((point.value / maxValue) * plotHeight),
        }));

        context.clearRect(0, 0, width, height);
        context.font = '600 10px system-ui, sans-serif';
        context.textBaseline = 'middle';
        context.setLineDash([4, 6]);
        context.lineWidth = 1;
        context.strokeStyle = '#dfe7f2';
        context.fillStyle = '#94a3b8';
        context.textAlign = 'left';

        for (let index = 0; index < 5; index += 1) {
            const ratio = index / 4;
            const y = padding.top + (plotHeight * ratio);
            const labelValue = Math.round(maxValue * (1 - ratio));

            context.beginPath();
            context.moveTo(padding.left, y);
            context.lineTo(width - padding.right, y);
            context.stroke();
            context.fillText(String(labelValue), 8, y);
        }

        context.setLineDash([]);
        context.textAlign = 'center';
        context.fillStyle = '#64748b';
        geometry.forEach((point) => {
            context.fillText(`${point.label}${point.partial ? '*' : ''}`, point.x, height - 13);
        });

        const completed = geometry.filter((point) => !point.partial);
        if (completed.length > 1) {
            context.save();
            context.beginPath();
            context.rect(0, 0, padding.left + (plotWidth * animationProgress), height);
            context.clip();
            context.strokeStyle = '#2563eb';
            context.lineWidth = 3;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            drawSmoothCurve(context, completed);
            context.stroke();
            context.restore();
        }

        geometry.forEach((point, index) => {
            if (point.x > padding.left + (plotWidth * animationProgress) + 2 && !point.partial) {
                return;
            }

            const isActive = index === activeIndex;
            const radius = isActive ? 6.5 : 4.5;
            context.save();
            context.beginPath();
            context.arc(point.x, point.y, radius, 0, Math.PI * 2);
            context.fillStyle = point.partial ? '#ffffff' : (isActive ? '#2563eb' : '#ffffff');
            context.fill();
            context.lineWidth = isActive ? 3 : 2.5;
            context.strokeStyle = point.partial ? '#94a3b8' : '#2563eb';
            if (point.partial) {
                context.setLineDash([2, 2]);
            }
            context.stroke();
            context.restore();
        });

        if (activeIndex >= 0 && pointerX !== null) {
            const crosshairX = clamp(pointerX, padding.left, width - padding.right);
            context.save();
            context.setLineDash([3, 4]);
            context.lineWidth = 1;
            context.strokeStyle = 'rgba(100, 116, 139, .55)';
            context.beginPath();
            context.moveTo(crosshairX, padding.top);
            context.lineTo(crosshairX, padding.top + plotHeight);
            context.stroke();
            context.restore();
        }
    };

    const animate = (startTime) => {
        const elapsed = Math.min(1, (performance.now() - startTime) / 950);
        animationProgress = 1 - Math.pow(1 - elapsed, 3);
        render();

        if (elapsed < 1) {
            animationFrame = requestAnimationFrame(() => animate(startTime));
        } else {
            animationFrame = null;
        }
    };

    const showTooltip = (index, localX) => {
        const point = geometry[index];
        if (!point || !tooltip) {
            return;
        }

        activeIndex = index;
        pointerX = localX;
        tooltipTitle.textContent = `${point.label}${point.partial ? ' · Current partial period' : ''}`;
        tooltipValue.textContent = String(point.value);
        tooltip.style.left = `${clamp(localX, 90, chart.clientWidth - 90)}px`;
        tooltip.style.top = `${Math.max(62, point.y)}px`;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', 'false');
        render();
    };

    const hideTooltip = () => {
        activeIndex = -1;
        pointerX = null;
        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
        render();
    };

    canvas.addEventListener('pointermove', (event) => {
        if (!geometry.length) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const localX = event.clientX - rect.left;
        const index = geometry.reduce((closest, point, pointIndex) => {
            const distance = Math.abs(point.x - localX);
            return distance < closest.distance ? { index: pointIndex, distance } : closest;
        }, { index: 0, distance: Number.POSITIVE_INFINITY }).index;

        showTooltip(index, localX);
    });

    canvas.addEventListener('pointerleave', hideTooltip);

    const resizeObserver = new ResizeObserver(() => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => {
            resizeCanvas();
            render();
        });
    });
    resizeObserver.observe(chart);

    resizeCanvas();
    render();
    if (animationProgress < 1) {
        animationFrame = requestAnimationFrame((time) => animate(time));
    }
};

const hexToRgba = (hex, alpha) => {
    const value = hex.replace('#', '');
    const normalized = value.length === 3
        ? value.split('').map((character) => `${character}${character}`).join('')
        : value;
    const numeric = Number.parseInt(normalized, 16);
    const red = (numeric >> 16) & 255;
    const green = (numeric >> 8) & 255;
    const blue = numeric & 255;
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
};

const bindFleetCssDonut = (card) => {
    const donut = card.querySelector('.fleet-css-donut');
    const rows = Array.from(card.querySelectorAll('.availability-row[data-donut-index]'));
    const center = donut?.querySelector('.fleet-css-donut-center');
    const centerValue = center?.querySelector('strong');
    const centerLabel = center?.querySelector('span');
    const tooltip = donut?.querySelector('.fleet-css-donut-tooltip');
    const tooltipLabel = tooltip?.querySelector('strong');
    const tooltipMeta = tooltip?.querySelector('span');

    if (!donut || donut.dataset.cssDonutBound === 'true') {
        return;
    }

    donut.dataset.cssDonutBound = 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const defaultValue = donut.dataset.defaultValue || centerValue?.textContent?.trim() || '0%';
    const defaultLabel = donut.dataset.defaultLabel || centerLabel?.textContent?.trim() || 'Active';
    const colors = ['#16a34a', '#f59e0b', '#94a3b8'];
    let centerTransitionToken = 0;

    const segments = rows.map((row, index) => ({
        row,
        color: colors[index] || '#94a3b8',
        label: row.dataset.label || 'Status',
        value: Number.parseInt(row.dataset.value || '0', 10) || 0,
        percentage: Math.max(0, Number.parseFloat(row.dataset.percentage || '0') || 0),
    }));

    const renderGradient = (activeIndex = null) => {
        let offset = 0;
        const stops = segments.map((segment, index) => {
            const start = offset;
            offset += segment.percentage;
            const color = activeIndex === null || activeIndex === index
                ? segment.color
                : hexToRgba(segment.color, .28);
            return `${color} ${start.toFixed(2)}% ${offset.toFixed(2)}%`;
        });

        donut.style.background = `conic-gradient(from -90deg, ${stops.join(', ')})`;
    };

    const setCenter = (value, label) => {
        const token = ++centerTransitionToken;
        if (!center || reducedMotion) {
            if (centerValue) centerValue.textContent = value;
            if (centerLabel) centerLabel.textContent = label;
            return;
        }

        const fadeOut = center.animate(
            [
                { opacity: 1, transform: 'scale(1)' },
                { opacity: .12, transform: 'scale(.94)' },
            ],
            { duration: 90, easing: 'ease-out', fill: 'forwards' }
        );

        fadeOut.finished.then(() => {
            if (token !== centerTransitionToken) {
                return;
            }
            if (centerValue) centerValue.textContent = value;
            if (centerLabel) centerLabel.textContent = label;
            center.animate(
                [
                    { opacity: .12, transform: 'scale(.94)' },
                    { opacity: 1, transform: 'scale(1)' },
                ],
                { duration: 130, easing: 'cubic-bezier(.22,.61,.36,1)', fill: 'forwards' }
            );
        }).catch(() => {});
    };

    renderGradient();

    const showItem = (index) => {
        const segment = segments[index];
        if (!segment) {
            return;
        }

        rows.forEach((row, rowIndex) => row.classList.toggle('is-donut-active', rowIndex === index));
        donut.classList.add('is-active');
        renderGradient(index);
        setCenter(`${segment.percentage.toFixed(1)}%`, segment.label);

        if (tooltipLabel) tooltipLabel.textContent = segment.label;
        if (tooltipMeta) tooltipMeta.textContent = `${segment.value} bus${segment.value === 1 ? '' : 'es'} · ${segment.percentage.toFixed(1)}%`;
        tooltip?.classList.add('is-visible');
        tooltip?.setAttribute('aria-hidden', 'false');
    };

    const clearItem = () => {
        rows.forEach((row) => row.classList.remove('is-donut-active'));
        donut.classList.remove('is-active');
        renderGradient();
        setCenter(defaultValue, defaultLabel);
        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
    };

    rows.forEach((row, index) => {
        row.setAttribute('tabindex', '0');
        row.addEventListener('pointerenter', () => showItem(index));
        row.addEventListener('pointerleave', clearItem);
        row.addEventListener('focus', () => showItem(index));
        row.addEventListener('blur', clearItem);
    });

    donut.addEventListener('pointerenter', () => donut.classList.add('is-active'));
    donut.addEventListener('pointerleave', () => {
        donut.classList.remove('is-active');
        if (!rows.some((row) => row.matches(':hover') || row === document.activeElement)) {
            renderGradient();
        }
    });
};

const initializeAnalyticsDomainPanels = () => {
    document.querySelectorAll('.analytics-domain-card').forEach((card) => {
        card.classList.add('is-chart-visible');
    });

    document.querySelectorAll('.trip-canvas-chart').forEach(bindTripCanvasChart);
    document.querySelectorAll('.descriptive-availability-card').forEach(bindFleetCssDonut);
};

initializeAnalyticsDomainPanels();
document.addEventListener('DOMContentLoaded', initializeAnalyticsDomainPanels);
document.addEventListener('ajax:content-updated', initializeAnalyticsDomainPanels);
